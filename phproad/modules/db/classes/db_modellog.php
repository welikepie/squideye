<?php

	/**
	 * Adds logging functionality to ActiveRecord-based model class.
	 * Use backend_logbehavior to display database record change log on your pages.
	 * This class logs only fields defined with ActiveRecord define_column method.
	 */
	class Db_ModelLog extends Phpr_Extension
	{
		const typeCreate = 'create';
		const typeUpdate = 'update';
		const typeDelete = 'delete';
		const typeCustom = 'custom';
		
		protected $_model;
		protected $_loadedData =  array();
		
		public function __construct($model)
		{
			$this->_model = $model;
			$this->_model->addEvent('onAfterCreate', $this, 'modelLogOnModelCreated');
			$this->_model->addEvent('onAfterLoad', $this, 'modelLogOnModelLoaded');
			$this->_model->addEvent('onAfterUpdate', $this, 'modelLogOnModelUpdated');
			$this->_model->addEvent('onAfterDelete', $this, 'modelLogOnModelDeleted');
		}
		
		public function modelLogOnModelCreated()
		{
			$dom = new DOMDocument('1.0', 'utf-8');
			$record = new DOMElement('record');
			$dom->appendChild($record);

			$newModel = $this->getReloadedModel();
			$newValues = $newModel ? $this->getDisplayValues($newModel) : null;

			foreach ($newValues as $dbName=>$value)
			{
				if (!strlen($value))
					continue;
				
				$displayName = $dbName;
				$type = db_text;
				$this->getFieldNameAndType($dbName, $type, $displayName);

				$fieldNode = new DOMElement('field');
				$record->appendChild($fieldNode);
				$fieldNode->setAttribute('name', $dbName);
				$fieldNode->setAttribute('displayName', $displayName);
				$fieldNode->setAttribute('type', $type);

				$new = new DOMElement('new', $value);
				$fieldNode->appendChild($new);
			}
			
			$this->createLogRecord(self::typeCreate, $dom->saveXML());
		}
		
		public function modelLogOnModelUpdated()
		{
			$dom = new DOMDocument('1.0', 'utf-8');
			$record = new DOMElement('record');
			$dom->appendChild($record);

			$newModel = $this->getReloadedModel();
			$newValues = $newModel ? $this->getDisplayValues($newModel) : null;
			$fieldsAdded = 0;

			foreach ($this->_loadedData as $dbName=>$value)
			{
				$newValue = null;
				if (array_key_exists($dbName, $newValues))
					$newValue = $newValues[$dbName];
					
				if (strcmp($value, $newValue) != 0)
				{
					$displayName = $dbName;
					$type = db_text;
					$this->getFieldNameAndType($dbName, $type, $displayName);

					$fieldNode = new DOMElement('field');
					$record->appendChild($fieldNode);
					$fieldNode->setAttribute('name', $dbName);
					$fieldNode->setAttribute('displayName', $displayName);
					$fieldNode->setAttribute('type', $type);

					$old = new DOMElement('old', $value);
					$fieldNode->appendChild($old);

					$new = new DOMElement('new', $newValue);
					$fieldNode->appendChild($new);
					$fieldsAdded++;
				}
			}

			if ($fieldsAdded)
				$this->createLogRecord(self::typeUpdate, $dom->saveXML());
		}
		
		public function modelLogOnModelLoaded()
		{
			$this->_loadedData = $this->getDisplayValues();
		}
		
		private function getDisplayValues($model = null)
		{
			$model = $model ? $model : $this->_model; 
			$skipFields = array_merge(
				$model->auto_create_timestamps, 
				$model->auto_update_timestamps,
				array('created_user_id', 'updated_user_id'));
			
			$result = array();
			$fields = $model->get_column_definitions();
			foreach ($fields as $dbName=>$definition)
			{
				if (!$definition->log)
				{
					if ($definition->isCalculated || $definition->isCustom || in_array($dbName, $skipFields) || $definition->noLog)
						continue;
				}

				$result[$dbName] = $model->displayField($dbName);
			}
				
			return $result;
		}

		private function getFieldNameAndType($dbName, &$type, &$displayName)
		{
			$fields = $this->_model->get_column_definitions();

			if (array_key_exists($dbName, $fields))
			{
				$displayName = $fields[$dbName]->displayName;
				$type = $fields[$dbName]->type;
				
				if ($fields[$dbName]->isReference)
				{
					if  ($fields[$dbName]->referenceType == 'has_many' || $fields[$dbName]->referenceType == 'has_and_belongs_to_many')
						$type = 'list';
					elseif ($type == db_text || $type == db_varchar)
						$type = null;
				}
			}
		}

		private function getReloadedModel()
		{
			$modelClass = get_class($this->_model);
			$newModel = new $modelClass();
			$newModel->simpleCaching = false;
			$primaryKey = $newModel->primary_key;
			return $newModel->find($this->_model->$primaryKey);
		}

		private function createLogRecord($type, $content)
		{
			$primaryKey = $this->_model->primary_key;
			$record = new Core_ModelLogRecord();
			$record->model_class = get_class($this->_model);
			$record->model_record_id = $this->_model->$primaryKey;
			$record->content = $content;
			$record->type = $type;
			$record->save();
		}

		public function modelLogOnModelDeleted()
		{
			$this->createLogRecord(self::typeDelete, null);
		}
		
		public function modelLogCustomEvent($description)
		{
			$this->createLogRecord(self::typeCustom, $description);
		}
	}

?>