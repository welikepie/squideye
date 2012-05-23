<?php

	class Db_DeferredBinding extends Db_ActiveRecord 
	{
		public $table_name = 'db_deferred_bindings';
		public $simpleCaching = true;

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function before_validation_on_create($deferred_session_key = null)
		{
			if ($this->is_bind)
			{
				/* 
				 * Skip repeating bindings 
				 */
				$obj = $this->findBindingRecord(1);
				if ($obj)
					return false;
			} else {
				/* 
				 * Remove add-delete pairs
				 */
				$obj = $this->findBindingRecord(1);
				if ($obj)
				{
					$obj->delete_cancel();
					return false;
				}
			}
		}
		
		protected function findBindingRecord($isBind)
		{
			$obj = self::create();
			$obj->where('master_class_name=?', $this->master_class_name);
			$obj->where('detail_class_name=?', $this->detail_class_name);
			$obj->where('master_relation_name=?', $this->master_relation_name);
			$obj->where('is_bind=?', $isBind);
			$obj->where('detail_key_value=?', $this->detail_key_value);
			$obj->where('session_key=?', $this->session_key);
			
			return $obj->find();
		}

		public static function cancelDeferredActions($masterClassName, $sessionKey)
		{
			$records = self::create()->
				where('master_class_name=?', $masterClassName)->
				where('session_key=?', $sessionKey)->
				find_all();
				
			foreach ($records as $record)
				$record->delete_cancel();
		}
		
		public function delete_cancel()
		{
			$this->delete_detail_record();
			$this->delete();
		}
		
		public static function cleanUp($days = 5)
		{
			$thisDate = Phpr_DateTime::now();
			
			$records = self::create()->where('ADDDATE(created_at, INTERVAL :days DAY) < :thisDate', array('days'=>$days, 'thisDate'=>$thisDate))->find_all();
			foreach ($records as $record)
				$record->delete_cancel();
		}
		
		protected function delete_detail_record()
		{
			/*
			 * Try to delete unbound has_one records from the details table
			 */
			try
			{
				if (!$this->is_bind)
					return;

				$masterClassName = $this->master_class_name;
				$master_object = new $masterClassName();
				$master_object->define_columns();

				if (!array_key_exists($this->master_relation_name, $master_object->has_models))
					return;

				if (($type = $master_object->has_models[$this->master_relation_name]) !== 'has_many')
					return;

				$related = $master_object->related($this->master_relation_name);
				$relatedObj  = $related->find($this->detail_key_value);
				if (!$relatedObj)
					return;

				$has_primary_key = false;
				$has_foreign_key = false;
				$options = $master_object->get_relation_options($type, $this->master_relation_name, $has_primary_key, $has_foreign_key);

				if (!array_key_exists('delete', $options) || !$options['delete'])
					return;
				
				if (!$has_foreign_key)
					$options['foreign_key'] = Phpr_Inflector::foreign_key($master_object->table_name, $relatedObj->primary_key);

				if (!$relatedObj->{$options['foreign_key']})
					$relatedObj->delete();
			}
			catch (exception $ex){}
		}
		
		public static function reset_object_field_bindings($master, $detail, $relation_name, $session_key)
		{
			$master_class_name = get_class($master);
			$detail_class_name = get_class($detail);
			$detail_key_value = $detail->get_primary_key_value();
			
			Db_DbHelper::query(
				'delete 
					from db_deferred_bindings 
				where
					master_class_name=:master_class_name and
					detail_class_name=:detail_class_name and
					master_relation_name=:master_relation_name and
					detail_key_value=:detail_key_value and
					session_key=:session_key
				',
			array(
				'master_class_name'=>$master_class_name,
				'detail_class_name'=>$detail_class_name,
				'master_relation_name'=>$relation_name,
				'detail_key_value'=>$detail_key_value,
				'session_key'=>$session_key
			));
		}
	}

?>