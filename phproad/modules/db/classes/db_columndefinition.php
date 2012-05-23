<?php

	/**
	 * Column definition class. 
	 * Objects of this class are used for defining presentation field properties in models.
	 * List behavior and form behavior are use data from these objects to output correct 
	 * field display names and format field data.
	 *
	 * Important note about date and datetime fields.
	 * Date fields are NOT converted to GMT during saving to the database 
	 * and displayValue method always returns the field value as is.
	 *
	 * Datetime fields are CONVERTED to GMT during saving and displayValue returns value converted
	 * back to a time zone specified in the configuration file.
	 */
	class Db_ColumnDefinition
	{
		public $dbName;
		public $displayName;
		public $defaultOrder = null;
		public $type;
		public $isCalculated;
		public $isCustom;
		public $isReference;
		public $referenceType = null;
		public $referenceValueExpr;
		public $relationName;
		public $referenceForeignKey;
		public $referenceClassName;
		public $visible = true;
		public $defaultVisible = true;
		public $listTitle = null;
		public $listNoTitle = false;
		public $noLog = false;
		public $log = false;
		public $dateAsIs = false;
		public $currency = false;
		public $noSorting = false;
		
		private $_model;
		private $_columnInfo;
		private $_calculated_column_name;
		private $_validationObj = null;
		
		private static $_relation_joins = array();
		private static $_cached_models = array();
		private static $_cached_class_instances = array();
		
		public $index;

		/**
		 * Date/time display format
		 * @var string
		 */
		private $_dateFormat = '%x';
		
		/**
		 * Floating point numbers display precision.
		 * @var int
		 */
		private $_precision = 2;
		
		/**
		 * Text display length
		 */
		private $_length = null;

		public function __construct($model, $dbName, $displayName, $type=null, $relationName=null, $valueExpression=null)
		{
			// traceLog('Column definition for '.get_class($model).':'.$dbName.' #'.$model->id);
			$this->dbName = $dbName;
			$this->displayName = $displayName;
			$this->_model = $model;
			$this->isReference = strlen($relationName);
			$this->relationName = $relationName;

			if (!$this->isReference)
			{
				$this->_columnInfo = $this->_model->column($dbName);
				if ($this->_columnInfo)
					$this->type = $this->_columnInfo->type;

				if ($this->_columnInfo)
				{
					$this->isCalculated = $this->_columnInfo->calculated;
					$this->isCustom = $this->_columnInfo->custom;
				}
			} else
			{
				$this->type = $type;
				
				if (strlen($valueExpression))
				{
					$this->referenceValueExpr = $valueExpression;
					$this->defineReferenceColumn();
				}
			}
			
			if ($this->type == db_date)
				$this->validation();
		}
		
		public function extendModel($model)
		{
			$this->setContext($model);

			if ($this->isReference && strlen($this->referenceValueExpr))
				$this->defineReferenceColumn();
				
			return $this;
		}

		/*
		 *
		 * Common column properties
		 *
		 */

		public function type($typeName)
		{
			$validTypes = array(db_varchar, db_number, db_float, db_bool, db_datetime, db_date, db_time, db_text);
			if (!in_array($typeName, $validTypes))
				throw new Phpr_SystemException('Invalid database type: '.$typeName);
				
			$this->type = $typeName;
			$this->_columnInfo = null;
			
			return $this;
		}
		
		public function dateFormat($displayFormat)
		{
			if ($this->type == db_datetime || $this->type == db_date || $this->type == db_time)
				$this->_dateFormat = $displayFormat;
			else 
				throw new Phpr_SystemException('Error in column definition for: '.$this->dbName.' column. Method "dateFormat" is applicable only for date or time fields.');
				
			return $this;
		}
		
		public function dateAsIs()
		{
			$this->dateAsIs = true;
			return $this;
		}
		
		public function precision($precision)
		{
			if ($this->type == db_float)
				$this->_precision = $precision;
			else 
				throw new Phpr_SystemException('Error in column definition for: '.$this->dbName.' column. Method "precision" is applicable only for floating point number fields.');
				
			return $this;
		}
		
		public function length($_length)
		{
			if ($this->type == db_varchar || $this->type == db_text)
				$this->_length = $_length;
			else 
				throw new Phpr_SystemException('Error in column definition for: '.$this->dbName.' column. Method "length" is applicable only for varchar or text fields.');

			return $this;
		}
		
		/**
		 * Hides the column from lists. 
		 */
		public function invisible()
		{
			$this->visible = false;
			return $this;
		}
		
		/**
		 * Hides the column from lists with default list settings
		 */
		public function defaultInvisible()
		{
			$this->defaultVisible = false;
			return $this;
		}
		
		/**
		 * Sets a title to display in list columns.
		 */
		public function listTitle($title)
		{
			$this->listTitle = $title;
			return $this;
		}
		
		/**
		 * Hides the list column title.
		 */
		public function listNoTitle($value = true)
		{
			$this->listNoTitle = $value;
			return $this;
		}
		
		/**
		 * Do not log changes of the column.
		 */
		public function noLog()
		{
			$this->noLog = true;
			return $this;
		}
		
		/**
		 * Disables or enables sorting for the column.
		 */
		public function noSorting($value = true)
		{
			$this->noSorting = true;
			return $this;
		}
		
		/**
		 * Log changes of the column. By default changes are not logged for calculated and custom columns.
		 */
		public function log()
		{
			$this->log = true;
			return $this;
		}
		
		/**
		 * Indicates that the column should be used for sorting the list 
		 * in case if a user have not selected other sorting column.
		 * @param string $direction Specifies an order direction - 'asc' or 'desc'
		 */
		public function order($directon = 'asc')
		{
			$this->defaultOrder = $directon;
			
			return $this;
		}
		
		/**
		 * Indicates that the column value should be formatted as currency
		 * in case if a user have not selected other sorting column.
		 * @param string $value Pass the TRUE value if the currency formatting should be applied
		 */
		public function currency($value)
		{
			$this->currency = $value;
			return $this;
		}

		public function validation($customFormatMessage = null)
		{
			if (!strlen($this->type))
				throw new Phpr_SystemException('Error applying validation to '.$this->dbName.' column. Column type is unknown. Probably this is a calculated column. Please call the "type" method to set the column type.');
				
			if ($this->_validationObj)
				return $this->_validationObj;

			$dbName = $this->isReference ? $this->referenceForeignKey : $this->dbName;

			$rule = $this->_model->validation->add($dbName, $this->displayName);
			if ($this->type == db_date)
				$rule->date($this->_dateFormat, $customFormatMessage);
			elseif ($this->type == db_datetime)
				$rule->dateTime($this->_dateFormat, $customFormatMessage);
			elseif ($this->type == db_float)
				$rule->float($customFormatMessage);
			elseif ($this->type == db_number)
				$rule->numeric($customFormatMessage);
				
			return $this->_validationObj = $rule;
		}
		
		/*
		 *
		 * Internal methods - used by the framework
		 *
		 */
		
		public function getColumnInfo()
		{
			return $this->_columnInfo;
		}
		
		public function displayValue($media)
		{
			$dbName = $this->dbName;

			if (!$this->isReference)
				$value = $this->_model->$dbName;
			else
			{
				$columName = $this->_calculated_column_name;
				$value = $this->_model->$columName;
			}

			switch ($this->type)
			{
				case db_varchar:
				case db_text:
					if ($media == 'form' || $this->_length === null)
						return $value;
					
					return Phpr_Html::strTrim($value, $this->_length);
				case db_number:
				case db_bool:
					return $value;
				case db_float:
					if ($media != 'form')
					{
						if ($this->currency)
							return format_currency($value);

						return Phpr::$lang->num($value, $this->_precision);
					}
					else
						return $value;
				case db_date:
					return $value ? $value->format($this->_dateFormat) : null;
				case db_datetime:
					if (!$this->dateAsIs)
						return Phpr_Date::display($value, $this->_dateFormat);
					else
						return $value ? $value->format($this->_dateFormat) : null;
				case db_time:
					return Phpr_Date::display($value, $this->_dateFormat);
				default:
					return $value;
			}
		}
		
		public function getSortingColumnName()
		{
			if (!$this->isReference)
				return $this->dbName;

			return $this->_calculated_column_name;
		}

		protected function defineReferenceColumn()
		{
			if (!array_key_exists($this->relationName, $this->_model->has_models))
				throw new Phpr_SystemException('Error defining reference "'.$this->relationName.'". Relation '.$this->relationName.' is not found in model '.get_class($this->_model));

			$relationType = $this->_model->has_models[$this->relationName];

			$has_primary_key = $has_foreign_key = false;
			$options = $this->_model->get_relation_options($relationType, $this->relationName, $has_primary_key, $has_foreign_key);

			if (!is_null($options['finder_sql'])) 
				throw new Phpr_SystemException('Error defining reference "'.$this->relationName.'". Relation finder_sql option is not supported.');

			$this->referenceType = $relationType;
			
			$columnName = $this->_calculated_column_name = $this->dbName.'_calculated';
			
			$colDefinition = array();
			$colDefinition['type'] = $this->type;
			
			$this->referenceClassName = $options['class_name'];

			if (!array_key_exists($options['class_name'], self::$_cached_class_instances))
			{
				$object = new $options['class_name'](null, array('no_column_init'=>true, 'no_validation'=>true));
				self::$_cached_class_instances[$options['class_name']] = $object;
			}
			
			$object = self::$_cached_class_instances[$options['class_name']];
			
			if ($relationType == 'has_one' || $relationType == 'belongs_to')
			{
				$objectTableName = $this->relationName.'_calculated_join';
				$colDefinition['sql'] = str_replace('@', $objectTableName.'.', $this->referenceValueExpr);

				$joinExists = isset(self::$_relation_joins[$this->_model->objectId][$this->relationName]);

				if (!$joinExists)
				{
					switch($relationType) 
					{
						case 'has_one' : 
							if (!$has_foreign_key)
								$options['foreign_key'] = Phpr_Inflector::foreign_key($this->_model->table_name, $object->primary_key);

							$this->referenceForeignKey = $options['foreign_key'];
							$condition = "{$objectTableName}.{$options['foreign_key']} = {$this->_model->table_name}.{$options['primary_key']}";
							$colDefinition['join'] = array("{$object->table_name} as {$objectTableName}"=>$condition);
						break;
						case 'belongs_to' : 
							$condition = "{$objectTableName}.{$options['primary_key']} = {$this->_model->table_name}.{$options['foreign_key']}";
							$this->referenceForeignKey = $options['foreign_key'];
							$colDefinition['join'] = array("{$object->table_name} as {$objectTableName}"=>$condition);

						break;
					}
					self::$_relation_joins[$this->_model->objectId][$this->relationName] = $this->referenceForeignKey;
				} else
					$this->referenceForeignKey = self::$_relation_joins[$this->_model->objectId][$this->relationName];
			} else
			{
				$this->referenceForeignKey = $this->relationName;

				switch($relationType) 
				{
					case 'has_many' :
						$valueExpr = str_replace('@', $object->table_name.'.', $this->referenceValueExpr);
						$colDefinition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name} where
							{$object->table_name}.{$options['foreign_key']} = {$this->_model->table_name}.{$options['primary_key']}";
							
						if ($options['conditions'])
							$colDefinition['sql'] .= " and ({$options['conditions']})";
							
					break;
					case 'has_and_belongs_to_many':
						$valueExpr = str_replace('@', $object->table_name.'.', $this->referenceValueExpr);

						if (!isset($options['join_table']))
							$options['join_table'] = $this->_model->get_join_table_name($this->_model->table_name, $object->table_name);

						if (!$has_primary_key)
							$options['primary_key'] = Phpr_Inflector::foreign_key($this->_model->table_name, $this->_model->primary_key);

						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($object->table_name, $object->primary_key);

						$colDefinition['sql'] = "select group_concat($valueExpr ORDER BY 1 SEPARATOR ', ') from {$object->table_name}, {$options['join_table']} where
							{$object->table_name}.{$object->primary_key}={$options['join_table']}.{$options['foreign_key']} and
							{$options['join_table']}.{$options['primary_key']}={$this->_model->table_name}.{$this->_model->primary_key}";
						
						if ($options['conditions'])
							$colDefinition['sql'] .= " and ({$options['conditions']})";
					break;
				}
			}

			$this->_model->calculated_columns[$columnName] = $colDefinition;
		}
		
		public function setContext($model)
		{		
			$this->_model = $model;
			return $this;
		}
	}

?>