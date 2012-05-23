<?
	define ('db_varchar', 'varchar');
	define ('db_number', 'number');
	define ('db_float', 'float');
	define ('db_bool', 'bool');
	define ('db_datetime', 'datetime');
	define ('db_date', 'date');
	define ('db_time', 'time');
	define ('db_text', 'text');
	
	$activerecord_no_columns_info = false;

	class Db_ActiveRecord extends Db_SqlBase implements IteratorAggregate
	{
		const stateCreating = 'creating';
		const stateCreated = 'created';
		const stateSaving = 'saving';
		const stateSaved = 'saved';

		const opUpdated = 'updated';
		const opCreated = 'created';
		const opDeleted = 'deleted';
		
		/**
		 * Table name
		 *
		 * @var string
		 */
		public $table_name;

		/**
		 * Primary key name
		 *
		 * @var string
		 */
		public $primary_key = 'id';

		/**
		 * Default ORDER BY
		 *
		 * @var string
		 */
		public $default_sort = '';

		public $has_one;

		public $has_many;

		public $has_and_belongs_to_many;

		public $belongs_to;
		
		protected $added_relations = array();
		
		/*
		 * Calculated fields definition. Example: 
		 * public $calculated_columns = array( 
		 * 	'comment_num'=>'select count(*) from comments where post_id=post.id',
		 * 	'disk_file_name'=>array('sql'=>'files.file_create_date', 'join'=>array('files'=>'files.post_id=post.id'), 'type'=>db_date)
		 * );
		 *
		 * @var array
		 */
		public $calculated_columns = array();
		
		/*
		 * Custom fields definition. Custom column values are evaluated ba calling custom model methods during filling the model object. Example
		 * public $custom_columns = array('record_status'=>db_number, 'record_css_class'=>db_text).
		 * For each column in the example should be defined corresponding methods:
		 * public function eval_record_status(), public function eval_record_css_class()
		 *
		 * @var array
		 */
		public $custom_columns = array();
		
		/*
		 * A list of columns to encrypt
		 */
		public $encrypted_columns = array();
		
		/*
		 * Caching
		 */
		
		/**
		 * Enable cross-instance simple caching by the identifier
		 *
		 * @var bool
		 */
		public $simpleCaching = false; 
		
		protected static $simpleCache = array();
		
		protected $className;

		/**
		 * Strict mode, fill only defined properties
		 *
		 * @var bool
		 */
		public $strict = false;

		/**
		 *	Names of automatic create timestamp columns
		 *
		 *	@var string[]
		 */
		public $auto_create_timestamps = array("created_at", "created_on");

		/**
		 *	Names of automatic update timestamp columns
		 *
		 *	@var string[]
		 */
		public $auto_update_timestamps = array("updated_at", "updated_on");

		/**
		 *	Whether to automatically update timestamps in certain columns
		 *
		 *	@var boolean
		 */
		public $auto_timestamps = true;

		/**
		 * List of datetime fields
		 *
		 * @var string[]
		 */
		protected $datetime_fields = array();
		
		/**
		 * Whether to automatically update created_user_id and updated_user_id columns
		 */
		protected $auto_footprints = true;
	
		/**
		 * New record flag
		 *
		 * @var boolean
		 */
		protected $new_record = true;

		/**
		 * SQL aggregate functions that may be applied to the associated table.
		 * 
		 * SQL defines aggregate functions AVG, COUNT, MAX, MIN and SUM.
		 * Not all of these functions are implemented by all DBMS's
		 *
		 * @var string[]
		 */
		protected $aggregations = array("count", "sum", "avg", "max", "min");
	
		/**
		 * Cache DESCRIBE in session
		 *
		 * @var boolean
		 */
		public static $cache_describe = true;
	
		protected static $describe_cache = array();

		/**
		 * Serialize associations
		 *
		 * @var boolean
		 */
		public $serialize_associations = false;

		public $fetched = array();

		public $has_models = array();
	
		protected $changed_relations = array();
	
		protected $calc_rows = false;
		
		/**
		 * Use the legacy pagination mechanism (manual limiting the row count)
		 * @var boolean
		 */
		protected $legacy_pagination = true;
	
		public $found_rows = 0;
	
		private $__locked = false;
		
		protected $modelState;
		
		public $objectId;
		
		public $_columns_def = null;
		
		/**
		 * A list of extensions
		 */
		
		public $implement = '';
		
		protected static $column_cache_disabled = array();
		protected $form_field_columns_initialized = false;
	
		/**
		 * Visual representation and validation - column definition feature
		 */
		protected $column_definitions = array();
		protected static $cached_column_definitions = array();
		public $form_elements = array();
		public $formTabIds = array();
		public $formTabVisibility = array();
		public $formTabCssClasses = array();
		public $validation;
		
		protected $columns_loaded = false;
		protected static $relations_cache = array();
		protected $fields_cache = null;
		protected $column_definition_context = null;
		public static $execution_context = null;

		protected $defined_column_list = array();
		
		public static $object_counter = 0;
		
		/**
		 * Memory management 
		 */
		
		public $model_options = array();
		
		/*
		 * Specifies a class name of a controller responsible for rendering forms and lists of models of this class.
		 */
		public $native_controller = null;
		
		public function __construct($values = null, $options = array())
		{
			$this->modelState = self::stateCreating;
			$this->model_options = $options;

			$this->implement = Phpr_Util::splat($this->implement, true);
			array_unshift($this->implement, 'Phpr_Events');
			parent::__construct();

			$this->initialize();
			self::$object_counter++;
			$this->objectId = 'ac_obj_'.self::$object_counter;

			// Fill with data
			if ($values !== null) 
			{
				$this->fill($values);
				$this->fill_relations($values);
			}

			if (!$this->get_model_option('no_validation'))
			{
				$this->validation = new Phpr_Validation($this);
				$this->validation->focusPrefix = get_class($this)."_";
			}

			$this->modelState = self::stateCreated;
//			$this->define_columns();
		}
		
		public function __destruct() 
		{
			foreach ($this->column_definitions as $id=>$def)
				unset($this->column_definitions[$id]);

			foreach ($this->form_elements as $id=>$def)
				unset($this->form_elements[$id]);

			foreach ($this->formTabIds as $id=>$def)
				unset($this->formTabIds[$id]);

			unset($this->validation);
			unset($this);
		}
	
		protected function initialize()
		{
			$this->datetime_fields = array_merge(
				$this->datetime_fields,
				$this->auto_create_timestamps,
				$this->auto_update_timestamps
			);

			if (!isset($this->table_name))
				$this->table_name = Phpr_Inflector::tableize(get_class($this));
		
			$fields = array_keys($this->fields());
			if ($this->auto_timestamps && !$this->get_model_option('no_timestamps'))
			{
				foreach($this->auto_create_timestamps as $field)
				{
					if (in_array($field, $fields))
						$this->{$field} = Phpr_DateTime::now();
				}
			}

			$this->className = get_class($this);

			$this->load_relations();
		}
		
		protected function get_model_option($name, $default = null)
		{
			return array_key_exists($name, $this->model_options) ? $this->model_options[$name] : $default;
		}

		/* Find methods */

		protected function _find_fill($data, $form_context = null)
		{
			if ($this->calc_rows)
				$this->found_rows = Db::sql()->fetchOne('SELECT FOUND_ROWS()');

			$class_name = get_class($this);
			$result = new Db_DataCollection();
			$result->parent = $this;
			foreach($data as $row) 
			{
				$result[] = $o = new $class_name(null, $this->model_options);
				
				foreach ($this->added_relations as $relation_info)
					$o->add_relation($relation_info[0], $relation_info[1], $relation_info[2]);

				$o->before_fetch($row);
				$o->fill($row, true, $form_context);
				$o->new_record = false;
				$o->after_fetch();
			}

			if ($this->legacy_pagination && $this->get_limit() > 1)
				return $result;

			if ($this->get_limit() == 1)
				return $result[0];
			else
				return $result;
		}
	
		public function find($id = null, $include = array(), $form_context = null)
		{
			//if (!Cms_Controller::get_instance())
				$this->init_columns_info($form_context);
//			$this->column_definition_context = $form_context;
			
			$this->limit(1);
			$this->calc_rows = false;
			if (is_array($id))
				$id = array_shift($id);

			return $this->find_all_internal($id, $include, $form_context);
		}

		protected function find_all_internal($id = null, $include = array(), $form_context = null)
		{
			//if (!Cms_Controller::get_instance())
				$this->init_columns_info($form_context);
//			$this->column_definition_context = $form_context;

			$cachingCase = false;

			if ($id instanceof WhereBase)
				$this->where($id);
			elseif (is_array($id))
				$this->where($this->primary_key . ' IN (?)', $id);
			elseif ($id !== null)
			{
				if ($this->get_limit() == 1 && $this->simpleCaching)
				{
					$cachingCase = true;
					
					if ( ($obj = self::load_cached($this->className, $this->primary_key, $id)) !== -1 )
						return $obj;
				}
					
				$this->where($this->primary_key . ' = ?', $id);
			}

			if (!$this->has_order() && (trim($this->default_sort) != ''))
			{
				$prefix = '';
				if (strpos($this->default_sort, '.') === false && strpos($this->default_sort, ',') === false)
					$prefix = $this->table_name . '.';

				$this->order($prefix . $this->default_sort);
			}

			$this->applyCalculatedColumns();
			// TODO: handle $include (eager associations)
	
			$data = $this->fetchAll($this->build_sql());
			$result = $this->_find_fill($data, $form_context);

			if ($cachingCase)
				self::cache_instance($this->className, $this->primary_key, $id, $result);
				
			return $result;
		}
		
		public function find_all($id = null, $include = array(), $form_context = null)
		{
			$result = $this->find_all_internal($id, $include, $form_context);
			if ($result instanceof Db_ActiveRecord)
			 	$result = new Db_DataCollection(array($result));

			return $result;
		}
		
		public function applyCalculatedColumns()
		{
			if (count($this->calculated_columns))
			{
				foreach ($this->calculated_columns as $name=>$definition)
				{
					if (is_string($definition))
						$this->addColumn('('.$definition.') as '.$name );
					elseif (is_array($definition))
					{
						if (!isset($definition['sql']))
							throw new Phpr_SystemException('Invalid calculated column definition - no SQL clause for '.$name.' column in class '.$this->className);

						if (isset($definition['join']))
						{
							foreach ($definition['join'] as $table=>$conditions)
								$this->join($table, $conditions);
						}

						$this->addColumn('('.$definition['sql'].') as '.$name);
					}
				}
			}
		}

		public function find_by_sql($sql, $include = array()) 
		{
			if ($sql instanceof Db_SqlBase)
				$sql = $sql->build_sql();
		
			// TODO: handle $include (eager associations)
		
			$data = $this->fetchAll($sql);
			return $this->_find_fill($data);
		}

		public function find_by($field, $value, $include = array()) 
		{
			$this->limit(1);
			$this->calc_rows = false;
			return $this->find_all_by($field, $value, $include);
		}

		public function find_all_by($field, $value, $include = array()) 
		{
			$this->where($field . ' = ?', $value);
			return $this->find_all_internal(null, $include);
		}

		public function find_related($relation, $params = null) 
		{
			return $this->load_relation($relation, $params);
		}
		
		/**
		 * Returns related records, including deferred relations
		 */
		public function list_related_records_deferred($name, $deferred_session_key)
		{
			$object = $this->get_related_records_deferred_obj($name, $deferred_session_key);

			$data = $object->find_all_internal();
			$data->relation = $name;
			$data->parent = $this;
			return $data;
		}
		
		public function get_related_records_deferred_obj($name, $deferred_session_key)
		{
			if (!isset($this->has_models[$name])) 
				throw new Phpr_SystemException("Relation $name is not found in the model ".$this->className);

			$type = $this->has_models[$name];
			if ($type != 'has_many' && $type != 'has_and_belongs_to_many')
				throw new Phpr_SystemException('list_related_records_deferred supports only has_many and has_and_belongs_to_many relations');

			$has_primary_key = false;
			$has_foreign_key = false;
			$options = $this->get_relation_options($type, $name, $has_primary_key, $has_foreign_key);

			$object = new $options['class_name']();
			if (is_null($options['order']) && ($object->default_sort != ''))
				$options['order'] = $object->default_sort;

			if (!$has_foreign_key)
				$options['foreign_key'] = Phpr_Inflector::foreign_key($this->table_name, $object->primary_key);

			$foreignKey = $options['foreign_key'];

			$deffered_where = "(exists 
				(select * from db_deferred_bindings where 
					detail_key_value={$object->table_name}.{$object->primary_key} 
					and master_relation_name=:relation_name and master_class_name='{$this->className}' 
					and is_bind=1 and session_key=:session_key))";
					
			$deffered_deletion_where = "(exists 
					(select * from db_deferred_bindings where 
						detail_key_value={$object->table_name}.{$object->primary_key} 
						and master_relation_name=:relation_name and master_class_name='{$this->className}' 
						and is_bind=0 and session_key=:session_key
						and id > ifnull((select max(id) from db_deferred_bindings where 
							detail_key_value={$object->table_name}.{$object->primary_key} 
							and master_relation_name=:relation_name and master_class_name='{$this->className}' 
							and is_bind=1 and session_key=:session_key), 0)
						))";

			$bind = array(
				'foreign_key'=>$this->{$options['primary_key']}, 
				'bind'=>1,
				'session_key'=>$deferred_session_key,
				'relation_name'=>$name);

			if ( !$this->is_new_record() )
			{
				if ($type == 'has_many')
					$object->where("({$object->table_name}.{$foreignKey}=:foreign_key) or ($deffered_where)", $bind);
				else 
				{
					$this_key = $this->get_primary_key_value();
					$existing_m2m_records = "(exists (select * from {$options['join_table']} where {$options['primary_key']}='{$this_key}' and {$options['foreign_key']}={$object->table_name}.{$object->primary_key}))";

					$object->where("({$existing_m2m_records}) or ($deffered_where)", $bind);
				}
			}
			else
				$object->where("($deffered_where)", $bind);
			
			$object->where("(not ($deffered_deletion_where))", $bind);
			$object->where($options['conditions']);
			
			if (strlen($options['order']))
				$object->order($options['order']);

			return $object;
		}
		
		
		/**
		 * Returns a column value, taking into account possible deferred bindings. 
		 * This method is used by the validation framework.
		 */
		public function getDeferredValue($column, $deferred_session_key)
		{
			if (isset($this->has_models[$column]) && $this->has_models[$column] == 'has_many') 
				return $this->list_related_records_deferred($column, $deferred_session_key);
				
			return $this->$column;
		}
		
		/**
		 * Sets a column value, taking into account possible deferred bindings.
		 * This method is used by the validation framework.
		 */
		public function setDeferredValue($column, $value, $deferred_session_key)
		{
			if (isset($this->has_models[$column])) 
				return;
				
			return $this->$column = $value;
		}
	
		/* Save methods */
	
		public function update($values) 
		{
			$this->before_fill($values);
			$this->fill($values);
			$this->fill_relations($values);
			return $this;
		}

		/**
		 * Performs data validation. Do not use this method
		 * if you are going to save the model, because save() method
		 * performs validation before saving data.
		 * @param mixed[] $values
		 * @return Db_ActiveRecord
		 */
		public function validate_data($values, $deferred_session_key = null)
		{
			$this->modelState = self::stateSaving;
			$this->update($values)->valid($deferred_session_key); 
			$this->modelState = self::stateSaved;
			return $this;
		}

		/**
		 * Set the record field values. Do not use this method
		 * if you are going to save the model, because save() method
		 * performs validation before saving data.
		 * @param mixed[] $values
		 * @return Db_ActiveRecord
		 */
		public function set_data($values)
		{
			$this->modelState = self::stateSaving;
			$this->update($values); 
			$this->modelState = self::stateSaved;
			return $this;
		}
	
		/**
		 * Save data
		 *
		 * @param mixed[] $values
		 * @param string $deferred_session_key An edit session key for deferred bindings
		 * @return Db_ActiveRecord
		 */
		public function save($values = null, $deferred_session_key = null) 
		{
			$this->modelState = self::stateSaving;

			if ($values !== null) 
			{
				$this->before_fill($values);
				$this->fill($values);
				$this->fill_relations($values);
			}

			if (!$this->valid($deferred_session_key)) 
				return false;

			$this->before_save($deferred_session_key);

			if ($this->new_record)
				$this->before_create($deferred_session_key);
			else
				$this->before_update($deferred_session_key);

			// Fill record to save
			$record = array();
			$fields = array_keys($this->fields());
			$dataUpdated = false;
			$newRecord = $this->new_record;
			$reflection = new ReflectionObject($this);
			
			foreach($reflection->getProperties() as $property) 
			{
				if (!in_array($property->name, $fields)) 
					continue;

				$val = $property->getValue($this);
			
				// convert datetime
				if (in_array($property->name, $this->datetime_fields))
					$val = $this->type_cast_date($val, $property->name);
					
				// encrypt
				if (in_array($property->name, $this->encrypted_columns))
					$val = base64_encode(Phpr_SecurityFramework::create()->encrypt($val));

				// Set value
				$record[$property->name] = $val;
			}

			if ($this->new_record) 
			{
				if (isset($record[$this->primary_key]) && ($record[$this->primary_key] === 0))
					unset($record[$this->primary_key]);
					
				$this->create_footprints($record);

				$this->sql_insert($this->table_name, $record);
				$key = $this->primary_key;
				$this->$key = $this->last_insert_id($this->table_name, $this->primary_key);
				$this->new_record = false;
				$this->after_create();
			} else {
				if (!isset($record[$this->primary_key]))
					throw new Phpr_SystemException('Primary key can not be null: '.$this->table_name);

				$key = $this->primary_key;
				if (isset($record[$this->primary_key]) && ($record[$this->primary_key] === 0))
					unset($record[$this->primary_key]);

				$this->unset_unchanged($record);
					
				if (count($record) > 0)
				{
					$this->update_footprints($record);
					$this->sql_update($this->table_name, $record, Db::where($this->primary_key . ' = ?', $this->{$key}));
					$dataUpdated = true;
				}

				$this->after_update();
			}

			$relationsUpdated = $this->apply_relations_changes($deferred_session_key);

			if ($newRecord)
			{
				$this->fireEvent('onAfterCreate');
				$this->after_create_saved();
			}
			elseif ($relationsUpdated || $dataUpdated)
				$this->fireEvent('onAfterUpdate');
		
			$this->after_save();
			
			if ($newRecord)
				$this->after_modify(self::opCreated, $deferred_session_key);
			else
				$this->after_modify(self::opUpdated, $deferred_session_key);
			
			$this->modelState = self::stateSaved;

			return $this;
		}
		
		/**
		 * Duplicates a record, but not saves it.
		 * Doesn't duplicate any relations.
		 * @return mixed Returns the new object.
		 */
		public function duplicate()
		{
			$obj = clone $this;
			
			$primary_key = $this->primary_key;
			
			$obj->new_record = true;
			$obj->$primary_key = null;
			
			return $obj;
		}
	
		 /* Delete methods */
	
		/**
		 * Deletes the record with the given id.
		 * If an array of ids is provided, all of them are deleted.
		 *
		 * @param mixed $id
		 */
		public function delete($id = null) 
		{
			if (is_null($id))
				$id = $this->{$this->primary_key};

			$this->before_delete($id);
			$this->delete_all(Db::where($this->primary_key . ' IN (?)', Phpr_Util::splat($id)));
			$this->after_delete();
			
			$this->after_modify(self::opDeleted, null);
			
			$this->fireEvent('onAfterDelete');
		}

		/**
		 * Deletes all the records that match the condition.
		 *
		 * @param string|WhereBase $conditions
		 */
		public function delete_all($conditions = null) 
		{
			global $activerecord_no_columns_info;
			$prev_no_columns_info_value = $activerecord_no_columns_info;
			$activerecord_no_columns_info = true;

			// Delete related
			foreach($this->has_models as $name => $type)
			{
				$relation_info = $this->{$type}[$name];
				if (!is_array($relation_info) || !isset($relation_info['delete']) || !$relation_info['delete']) 
					continue;
					
				switch ($type) 
				{
					case 'has_one':
						$related = $this->{$name};
						if (isset($related))
							$related->delete();
					break;
					case 'has_many':
						$related = $this->{$name};
						foreach($related as $item) 
							$item->delete();
					break;
					case 'has_and_belongs_to_many':
						if (!is_array($relation_info)) 
						{
							$relation_info = array(
							'class_name' => Phpr_Inflector::classify($relation_info)
							);
						} elseif (!isset($relation_info['class_name']))
							$relation_info['class_name'] = Phpr_Inflector::classify($name);

						// Create model
						$object = new $relation_info['class_name']();
						if (is_null($object))
							throw new Phpr_SystemException('Class not found: '.$relation_info['class_name']);

						$options = array_merge(array(
							'join_table' => $this->get_join_table_name($this->table_name, $object->table_name),
							'primary_key' => Phpr_Inflector::foreign_key($this->table_name, $this->primary_key),
							'foreign_key' => Phpr_Inflector::foreign_key($object->table_name, $object->primary_key)
							), Phpr_Util::splat($relation_info));

						DB::select()->sql_delete($options['join_table'], DB::where($options['join_table'] . '.' . $options['primary_key'] . ' = ?', $this->{$this->primary_key}));
						break;
				}

			}
				
			$this->sql_delete($this->table_name, $conditions);
			
			$activerecord_no_columns_info = $prev_no_columns_info_value;
		}

		/* Data processing routines */
	
		public function fill($row, $save_fetched = false, $form_context = null) 
		{
			//if (!Cms_Controller::get_instance())
			$this->init_columns_info($form_context);
//			$this->column_definition_context = $form_context;
			
			if ($row === null) return;

			// fill model with record
			if ($save_fetched)
				$this->fetched = array();

			foreach($row as $name => $val) 
			{
				if ($this->strict && !isset($this->{$name})) 
					continue;
					
				if (array_key_exists($name, $this->has_models))
				 	continue;

				if ($this->modelState != self::stateSaving)
				{
					if (in_array($name, $this->encrypted_columns) && strlen($val))
					{
						try
						{
							$val = Phpr_SecurityFramework::create()->decrypt(base64_decode($val));
						} catch (Exception $ex)
						{
							$val = null;
						}
					}
				}

				// Store unchanged values
				if ($save_fetched)
					$this->fetched[$name] = $this->type_cast_field($name, $val);

				// typecasting
				$val = $this->type_cast_field($name, $val);
//				if (!is_null($val))
					$this->{$name} = $val;
			}

			if ($save_fetched)
				$this->fireEvent('onAfterLoad');
		}
		
		public function fill_external($row, $form_context = null)
		{
			$this->before_fetch($row);
			$this->fill($row, true, $form_context);
			$this->new_record = false;
			$this->after_fetch();
		}
		
		public function eval_custom_columns()
		{
			foreach ($this->custom_columns as $column=>$type)
			{
				$methodName = 'eval_'.$column;
				if (method_exists($this, $methodName))
					$this->{$column} = $this->$methodName();
			}
		}
	
		protected function fill_relations($values)
		{
			foreach($values as $name => $value) 
			{
				if (!array_key_exists($name, $this->has_models)) 
					continue;
					
				$this->__set($name, $value);
			}
		}
	
		/* Typecasting */
	
		protected function type_cast_field($field, $value) 
		{
			$field_info = $this->field($field);
			if (!isset($field_info['type']))
			{
				if (array_key_exists($field, $this->calculated_columns) && isset($this->calculated_columns[$field]['type']))
					$field_info = array('type'=>$this->calculated_columns[$field]['type']);
			}

			// convert datetime
			// if (in_array($field, $this->datetime_fields) ||
			// 		(isset($field_info['type']) && in_array($field_info['type'], array('datetime', 'date', 'time')))
			// 	) 
			if ((isset($field_info['type']) && ($field_info['type'] == 'datetime' || $field_info['type'] == 'date' || $field_info['type'] == 'time'))) 
			{
				$value = $this->type_cast_date($value, $field);
			} 
			elseif (isset($field_info['type'])) 
			{
				switch($field_info['type']) 
				{
					case 'decimal':
					case 'int':
					//case 'tinyint':
					case 'smallint':
					case 'mediumint':
					case 'bigint':
					case 'double':
					case 'float':
						$value = $value;
						break;
					case 'bool':
					case 'tinyint':
						$value = $value;
						break;
					case 'datetime':
					case 'date':
					case 'time':
						$value = $this->type_cast_date($value);

						break;
				}
			}

			return $value;
		}
		
		protected function type_cast_date($value, $field)
		{
			$isObject = is_object($value);
			
			if (!$isObject)
			{
				$len = strlen($value);
				if (!$len)
					return null;
				if ($len <= 10)
					$value .= ' 00:00:00';

				/*
				 * Do not convert dates to object during saving for validatable fields. The Validation object
				 * will process dates instead of model.
				 */
				if ($this->modelState == self::stateSaving && $this->validation->hasRuleFor($field))
					return $value;

				return new Phpr_Datetime($value);
			}
			elseif ($value instanceof Phpr_DateTime) 
				return $value->toSqlDateTime();
				
			return null;
		}
	
		/* Triggers */

		/**
		 * Is called before fill() && fill_relations() on existing objects that has a record
		 */
		public function before_fill(&$new_values) 
		{
		}

		/**
		 * Is called before save() on new objects that havent been saved yet (no record exists)
		 */
		public function before_create($deferred_session_key = null) 
		{
		}

		/**
		 * Is called after save() on new objects that havent been saved yet (no record exists)
		 */
		public function after_create() 
		{
		}
		
		/**
		 * Is called after save() on new objects after all relations have been saved
		 */
		public function after_create_saved()
		{
		}

		/**
		 * Is called before save() on existing objects that has a record
		 */
		public function before_update($session_key = null) 
		{
		}

		/**
		 * Is called after save() on existing objects that has a record
		 */
		public function after_update() 
		{
		}

		/**
		 * Is called before save() (regardless of whether its a create or update save)
		 */
		public function before_save($deferred_session_key = null) 
		{
		}

		/**
		 * Is called after save() (regardless of whether its a create or update save)
		 */
		public function after_save()
		{
		}

		/**
		 * Is called before delete()
		 */
		public function before_delete($id=null) 
		{
		}
		
		/**
		 * Is called after delete()
		 */
		public function after_delete()
		{
		}
		
		/**
		 * Is called on any record update: crate, update, delete
		 * The first parameter is the operation flag - one of the Db_ActiveRecord:op.. constants
		 */
		public function after_modify($operation, $deferred_session_key)
		{
		}

		/**
		 * Is called before fetch row(s) from database
		 */
		public function before_fetch($data)
		{
		}

		/**
		 * Is called after a has-many relation item has been bound to the model
		 */
		public function after_has_many_bind($obj, $relation_name)
		{
		}

		/**
		 * Is called after a has-many relation item has been unbound from the model
		 */
		public function after_has_many_unbind($obj, $relation_name)
		{
		}
		
		/**
		 * Is called after fetch() on existing objects that has a record
		 */
		protected function after_fetch()
		{
		}

		/* Service methods */

		protected function field($name)
		{
			$fields = $this->fields();
			return isset($fields[$name]) ? $fields[$name] : array();
		}
	
		public function fields() 
		{
			if ($this->fields_cache)
				return $this->fields_cache;

			if (isset(self::$describe_cache[$this->table_name])) 
				return self::$describe_cache[$this->table_name];

			if (self::$cache_describe && Phpr::$config->get('ALLOW_DB_DESCRIBE_CACHE')) 
			{
				$cache = Core_CacheBase::create();
				
				$descriptions = $cache->get('phpr_table_descriptions');
				if (!$descriptions || !is_array($descriptions))
					$descriptions = array();

				try
				{
					if (is_array($descriptions) && array_key_exists($this->table_name, $descriptions))
						return self::$describe_cache[$this->table_name] = $descriptions[$this->table_name];
				} catch (exception $ex)
				{}

				// DESCRIBE and save cache
				$describe = $this->describe_table($this->table_name);
				self::$describe_cache[$this->table_name] = $describe;

				$descriptions[$this->table_name] = $describe;
				$cache->set('phpr_table_descriptions', $descriptions);
				return $describe;
			}

			return $this->fields_cache = self::$describe_cache[$this->table_name] = $this->describe_table($this->table_name);
		}
		
		public static function clear_describe_cache()
		{
			Phpr::$session->set('phpr_table_descriptions', array());
			
			if (Phpr::$config->get('ALLOW_DB_DESCRIBE_CACHE'))
			{
				$cache = Core_CacheBase::create();
				$cache->set('phpr_table_descriptions', false);
			}
		}
		
		protected function create_footprints(&$new_values)
		{
			if ($this->auto_footprints && $this->field('created_user_id'))
			{
				$user = Phpr::$security->getUser();
				if ($user)
					$new_values['created_user_id'] = $this->created_user_id = $user->id;
			}
		}
		
		protected function update_footprints(&$new_values)
		{
			// set $auto_update_timestamps
			if ($this->auto_timestamps)
			{
				$fields = array_keys($this->fields());

				foreach($this->auto_update_timestamps as $field) 
				{
					if (in_array($field, $fields))
						$new_values[$field] = $this->{$field} = Phpr_DateTime::now();
				}
			}

			// update updated_user_id column
			if ($this->auto_footprints && !($this instanceof Phpr_User) && $this->field('updated_user_id'))
			{

				$user = Phpr::$security->getUser();
				if ($user)
					$new_values['updated_user_id'] = $this->updated_user_id = $user->id;
			}
		}
		
		protected function unset_unchanged(&$new_values)
		{
			// Unset unmodified fields
			foreach($this->fetched as $key => $value) 
			{
				if (array_key_exists($key, $new_values))
				{
					$equal = false;
					
					$new_value = $new_values[$key];
					
					if (is_object($value) && $value instanceof Phpr_DateTime && !is_object($new_value))
						$new_value = $this->type_cast_date($new_value, $key);

					if (is_object($value) && is_object($new_value))
					{
						if ($value instanceof Phpr_DateTime && $new_value instanceof Phpr_DateTime)
							$equal = $value->equals($new_value);
					}
					else
					{
						$equal = (string)$new_value === (string)$value;
					}
					
					if ($equal)
						unset($new_values[$key]);
				}
			}
		}

		public function has_column($field) 
		{
			return ($this->field($field) !== array());
		}
	
		public function column($field) 
		{
			$columns = $this->columns();
			$column = $columns->find($field, 'name');

			if (isset($column))
				return $column;
			else
				return null;
		}
	
		public function columns() 
		{
			if (isset($this->_columns_def))
				return $this->_columns_def;
				
			$columns = array();
			$fields = $this->fields();

			foreach($fields as $info) 
				$columns[] = new Db_ActiveRecordColumn($info);
				
			foreach($this->calculated_columns as $name=>$data)
			{
				$type = (is_array($data) && isset($data['type'])) ? $data['type'] : db_text;
				$info = array('calculated'=>true, 'name'=>$name, 'type'=> $type);

				$columns[] = new Db_ActiveRecordColumn($info);
			}

			foreach($this->custom_columns as $name=>$type)
			{
				$info = array('custom'=>true, 'name'=>$name, 'type'=> $type);
				$columns[] = new Db_ActiveRecordColumn($info);
			}

			return $this->_columns_def = new Db_DataCollection($columns);
		}
		
		public function get_primary_key_value()
		{
			return $this->{$this->primary_key};
		}

		public function is_new_record()
		{
			return $this->new_record;
		}

		/* Internal methods */
	
		public function build_sql() 
		{
			if (count($this->parts['from']) == 0)
				$this->from($this->table_name);

			if ($this->calc_rows)
				$this->use_calc_rows();

			return parent::build_sql();
		}

		public function limit($count = null, $offset = null) 
		{
			if (!$this->legacy_pagination)
				$this->calc_rows = true;
				
			return parent::limit($count, $offset);
		}

		public function limitPage($page, $rowCount) 
		{
			$this->calc_rows = true;
			return parent::limitPage($page, $rowCount);
		}
	
		public function requestRowCount()
		{
			$obj = clone $this;
			self::$object_counter++;
			$obj->objectId = 'ac_obj_'.self::$object_counter;

			$obj->init_columns_info(null, true);
			$obj->applyCalculatedColumns();

			if (count($obj->parts['from']) == 0)
				$obj->from($obj->table_name);

			$obj->parts['order'] = array();

			if (!$obj->has_group())
			{
				$obj->parts['fields'] = array('count(*)');
				$sql = $obj->build_sql();

				return Db_Sql::create()->fetchOne($sql); 
			} else
			{
				$obj->use_calc_rows();
				$sql = $obj->build_sql();
				Db_DbHelper::query($sql);
				return Db_DbHelper::scalar('SELECT FOUND_ROWS()');
			}
		}
	
		/* Interface methods */
	
		/**
		 * Return iterator object for ActiveRecord
		 *
		 * @return Db_ActiveRecordIterator
		 * @internal For internal use only
		 */
		function getIterator() 
		{
			return new Db_ActiveRecordIterator($this);
		}

		/* Magic */
	
		/**
		 * Override call() to dynamically call the database associations
		 *
		 * @param string $method_name
		 * @param mixed $parameters
		 */

		function __call($method_name, $parameters = null) 
		{
			if (method_exists($this, $method_name)) 
				// If the method exists, just call it
				return call_user_func_array(array($this, $method_name), $parameters);
				
			// ... otherwise, check to see if the method call is one of our
			// special ActiveRecord methods ...
			if (count($parameters) && is_array($parameters[0]))
				$parameters = $parameters[0];

			// ... first check for method names that match any of our explicitly
			// declared associations for this model ( e.g. public $has_many = "movies" ) ...
			if (in_array($method_name, array_keys($this->has_models)))
				return call_user_func_array(array($this, 'find_related'), array_merge(array($method_name), $parameters));
		
			// check for the [count,sum,avg,etc...]_all magic functions
			if (substr($method_name, -4) == "_all" && in_array(substr($method_name, 0, -4), $this->aggregations))
				return $this->aggregate_all(substr($method_name, 0, -4), $parameters);
			else
			{
				// check for the find_all_by_* magic functions
				if (strlen($method_name) > 11 && substr($method_name, 0, 11) == "find_all_by") 
					//$result = $this->find_all_by(substr($method_name, 12), $parameters);
					return call_user_func_array(array($this, 'find_all_by'), array_merge(array(substr($method_name, 12)), $parameters));

				// check for the find_by_* magic functions
				if (strlen($method_name) > 7 && substr($method_name, 0, 7) == "find_by") 
					//$result = $this->find_by(substr($method_name, 8), $parameters);
					return call_user_func_array(array($this, 'find_by'), array_merge(array(substr($method_name, 8)), $parameters));
			}

			//return call_user_func_array(array($this, $method_name), $parameters);
			return parent::__call($method_name, $parameters);
		}
		
		function __get($name) 
		{
			if (isset($this->$name)) 
				return $this->$name;

			// Evaluate custom column values
			if (array_key_exists($name, $this->custom_columns))
			{
				$methodName = 'eval_'.$name;
				if (method_exists($this, $methodName))
					return $this->{$name} = $this->$methodName();
			}
			
			if (array_key_exists($name, $this->has_models)) 
			{
				$this->__lock();
				$this->$name = $this->load_relation($name);
				$this->__unlock();
			}

			if (substr($name, -5) == '_list' && array_key_exists(substr($name, 0, -5), $this->has_models)) 
				return $this->prepare_relation_object(substr($name, 0, -5));

			if (!property_exists($this, $name))
				return parent::__get($name);
				
			return $this->$name;
		
			/*
			if (!isset($this->$name))
				return null;
			else
				return $this->$name;
			*/
		}

		public function __lock()
		{
			if (!$this->__locked)
				$this->__locked = true;
		}

		public function __unlock() 
		{
			if ($this->__locked)
				$this->__locked = false;
		}

		function __set($name, $value) 
		{
			if(!$name) return;
			
			if (!$this->__locked) 
			{
				// this if checks if first its an object if its parent is ActiveRecord
				$is_object = is_object($value);

				if ($is_object && ($value instanceof Db_ActiveRecord)) 
				{
					if (!is_null($this->has_one) && array_key_exists($name, $this->has_one)) 
					{
						$primary_key = $value->primary_key;
						if (isset($this->has_one[$name]['foreign_key']))
							$foreign_key = $this->has_one[$name]['foreign_key'];
						else
							$foreign_key = Phpr_Inflector::singularize($value->table_name) . "_" . $primary_key;

						$this->$foreign_key = $value->$primary_key;
					}

					if (!is_null($this->belongs_to) && array_key_exists($name, $this->belongs_to)) 
					{
						$primary_key = $this->primary_key;
						if (isset($this->belongs_to[$name]['foreign_key']))
							$foreign_key = $this->belongs_to[$name]['foreign_key'];
						else
							$foreign_key = Phpr_Inflector::singularize($this->table_name) . "_" . $primary_key;
						
						$has_primary_key = $has_foreign_key = false;
						$options = $this->get_relation_options('belongs_to', $name, $has_primary_key, $has_foreign_key);
						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($value->table_name, $this->primary_key);

						$this->{$options['foreign_key']} = $value->{$options['primary_key']};
					}
					// this elseif checks if its an array of objects and if its parent is ActiveRecord
				} elseif (is_array($value) || ($is_object && ($value instanceof Db_DataCollection))) 
				{
					// update (replace) related records
					if (isset($this->has_models[$name])) 
					{
						$type = $this->has_models[$name];
						if (!in_array($type, array('has_many', 'has_and_belongs_to_many'))) 
							return;
					
						$this->unbind_all($name);
						if ($value instanceof ActiveRecord)
							$this->bind($name, $value);
						elseif (($value instanceof Db_DataCollection) || is_array($value)) 
						{
							foreach($value as $record)
								$this->bind($name, $record);
						}
					}
				}
			}

			// Assignment to something else, do it
			$this->$name = $value;
		}
	
		/* Relations */
	
		/**
		 * This method parses all the class properties to find relationships
		 */
		protected function load_relations() 
		{
			$this->has_one = Phpr_Util::splat_keys($this->has_one);
			$this->has_many = Phpr_Util::splat_keys($this->has_many);
			$this->has_and_belongs_to_many = Phpr_Util::splat_keys($this->has_and_belongs_to_many);
			$this->belongs_to = Phpr_Util::splat_keys($this->belongs_to);
			
			if (array_key_exists($this->className, self::$relations_cache))
			{
				$this->has_models = self::$relations_cache[$this->className];
				return;
			}

			/*
			$reflection = new ReflectionObject($this);
			
			foreach($reflection->getProperties() as $prop) 
			{
				// ignore private properties so we don't need to parse every single variable
				if ($prop->isPublic() || $prop->isProtected()) 
				{
					if (preg_match("/^(has_many|has_one|belongs_to|has_and_belongs_to_many)_(.+)/", $prop->name, $found)) 
					{
						$relationship = $found[1];
						$model = $found[2];
						$params = (array) $prop->getValue($this);

						$this->{$relationship}[$model] = $params;
					}
				}
			}
			*/

			// merge models
			// and add itself to the list of models
			$this->has_models = array_merge(
				Phpr_Util::indexing(Phpr_Util::splat($this->has_one), 'has_one'),
				Phpr_Util::indexing(Phpr_Util::splat($this->has_many), 'has_many'),
				Phpr_Util::indexing(Phpr_Util::splat($this->has_and_belongs_to_many), 'has_and_belongs_to_many'),
				Phpr_Util::indexing(Phpr_Util::splat($this->belongs_to), 'belongs_to')
			);
			
      	self::$relations_cache[$this->className] = $this->has_models;
		}

		/**
		 * Returns a the name of the join table that would be used for the two
		 * tables.	The join table name is decided from the alphabetical order
		 * of the two tables.	e.g. "genres_movies" because "g" comes before "m"
		 *
		 * @param string $first_table
		 * @param string $second_table
		 * @return string
		 */
		public function get_join_table_name($first_table, $second_table) 
		{
			$tables = array($first_table, $second_table);
			sort($tables);
			return implode('_', $tables);
		}
	
		/**
		 * Returns a related class name
		 */
		public function get_related($relation) 
		{
			if (!isset($this->has_models[$relation])) 
				return null;
				
			$relation_type = $this->has_models[$relation];
			$relation = $this->{$relation_type}[$relation];
		
			$class_name = (is_array($relation) && isset($relation['class_name'])) ? $relation['class_name'] : Phpr_Inflector::classify($relation);
			return $class_name;
		}
		
		/**
		 * Create related class instance
		 *
		 * @param string $relation
		 * @return Db_ActiveRecord
		 */
		public function related($relation) 
		{
			$class_name = $this->get_related($relation);
			
			if (class_exists($class_name))
				return new $class_name();
				
			return null;
		}
		
		protected function prepare_relation_object($name, $params = null)
		{
			if (!isset($this->has_models[$name])) 
				return null;
		
			$type = $this->has_models[$name];
			
			$has_primary_key = false;
			$has_foreign_key = false;
			$options = $this->get_relation_options($type, $name, $has_primary_key, $has_foreign_key);
			
			// Create model
			$object = new $options['class_name']();
			if (is_null($object))
				throw new Phpr_SystemException('Class not found: '.$options['class_name']);
				
			if (is_null($options['order']) && ($object->default_sort != ''))
				$options['order'] = $object->default_sort;

			// Apply params filter
			if (!is_null($params)) 
			{
				if ($params instanceof WhereBase)
					$object->where($params);
				elseif (is_array($params))
					$object->where($object->primary_key . ' IN (?)', $params);
				else
					$object->where($object->primary_key . ' = ?', $params);
			}
		
			if (!is_null($options['finder_sql'])) 
			{
				if (in_array($type, array('has_one', 'belongs_to')))
				{
					$object->limit(1);
					$object->calc_rows = false;
				}

				return $object->find_by_sql($options['finder_sql']);
			} else 
			{
				switch($type) 
				{
					case 'has_one':
						//$object->where($object->primary_key . ' = ?', $this->{$options['foreign_key']});
						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($this->table_name, $object->primary_key);
						
						$object->where($options['foreign_key'] . ' = ?', $this->{$options['primary_key']});
						break;
					case 'has_many':
						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($this->table_name, $object->primary_key);

						if (!$has_primary_key)
							$options['primary_key'] = Phpr_Inflector::foreign_key($this->table_name, $this->primary_key);

						$object->where($options['foreign_key'] . ' = ?', $this->get_primary_key_value());
						break;
					case 'has_and_belongs_to_many':
						if (!isset($options['join_table']))
							$options['join_table'] = $this->get_join_table_name($this->table_name, $object->table_name);

						if (!$has_primary_key)
							$options['primary_key'] = Phpr_Inflector::foreign_key($this->table_name, $this->primary_key);
							
						if (isset($options['join_primary_key']))
							$options['primary_key'] = $options['join_primary_key'];

						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($object->table_name, $object->primary_key);

						$object->join($options['join_table'], $object->table_name . '.' . $object->primary_key . ' = ' . $options['join_table'] . '.' . $options['foreign_key'])->where($options['join_table'] . '.' . $options['primary_key'] . ' = ?', $this->{$this->primary_key});
						break;
					case 'belongs_to':
						if (!$has_foreign_key)
							$options['foreign_key'] = Phpr_Inflector::foreign_key($object->table_name, $this->primary_key);

						$object->where($options['primary_key'] . ' = ?', $this->{$options['foreign_key']});
						//$object->where($options['primary_key'] . ' = ?', $this->{$options['foreign_key']});
						break;
				}
			}

			$object->where($options['conditions'])->limit($options['limit']);
			$object->calc_rows = false;

			if ($options['order'] !== false)
				$object->order($options['order']);

			return $object;
		}
	
		protected function load_relation($name, $params = null) 
		{
			$object = $this->prepare_relation_object($name, $params);
			if (!$object) 
				return null;

			$type = $this->has_models[$name];
			if (in_array($type, array('has_one', 'belongs_to')))
				return $object->find();

			$data = $object->find_all_internal();

			$data->relation = $name;
			$data->parent = $this;
			return $data;
		}
		
		public function reset_relations()
		{
			foreach ($this->has_models as $name=>$settings)
			{
				if (isset($this->$name)) 
					unset($this->$name);
			}
			
			$this->reset_custom_columns();
		}
		
		public function reset_custom_columns()
		{
			foreach ($this->custom_columns as $name=>$settings)
			{
				if (isset($this->$name)) 
					unset($this->$name);
			}
		}
		
		public function get_relation_options($type, $name, &$has_primary_key, &$has_foreign_key)
		{
			$default_options = array(
				'class_name' => Phpr_Inflector::classify($name),
				'primary_key' => $this->primary_key,
				'foreign_key' => Phpr_Inflector::foreign_key($name, $this->primary_key),
				'conditions' => null,
				'order' => null,
				'limit' => null,
				'finder_sql' => null
			);
			
			$has_primary_key = false;
			$has_foreign_key = false;

			$relation = $this->$type;
			if (isset($relation) && isset($relation[$name])) {
				if (is_string($relation[$name]))
					$relation[$name] = array('class_name' => Phpr_Inflector::classify($relation[$name]));

				$has_primary_key = isset($relation[$name]['primary_key']);
				$has_foreign_key = isset($relation[$name]['foreign_key']);
				
				return array_merge($default_options, $relation[$name]);
			}
			
			return $default_options;
		}

		protected function change_relation($relation, $record, $action) 
		{
			if (!isset($this->has_models[$relation])) 
				return $this;

			$name = $relation;
			$type = $this->has_models[$name];
		
			if (!in_array($type, array('has_many', 'has_and_belongs_to_many'))) 
				return $this;
		
			$relations = $this->$type;
			$relation = $relations[$name];
		
			if ($record instanceof Db_ActiveRecord)
				$record = $record->{$record->primary_key};
		
			if (!isset($this->changed_relations[$action]))
				$this->changed_relations[$action] = array();

			if (!isset($this->changed_relations[$action][$name])) 
			{
				$this->changed_relations[$action][$name] = array(
					'values' => array(),
					'type' => $type,
					'relation' => $relation
				);
			}

			$this->changed_relations[$action][$name]['values'][] = $record;
			return $this;
		}
		
		protected function apply_deferred_relation_changes($deferred_session_key)
		{
			if ($deferred_session_key)
			{
				$bindings = Db_DeferredBinding::create();
				$bindings->where('master_class_name=?', $this->className);
				$bindings->where('session_key=?', $deferred_session_key);
			
				$bindings = $bindings->find_all_internal();
				foreach ($bindings as $binding)
				{
					$action = $binding->is_bind ? 'bind' : 'unbind';
					$this->change_relation($binding->master_relation_name, $binding->detail_key_value, $action);
					$binding->delete();
				}
			}
		}
		
		protected function find_related_record($relation, $record)
		{
			$keyValue = is_object($record) ? $record->get_primary_key_value() : $record;
			$related_records = $this->{$relation};

			foreach ($related_records as $obj)
			{
				if ($obj->get_primary_key_value() == $keyValue)
					return $obj;
			}
			
			return null;
		}

		/**
		 * Applies relation changes. Returns true in case if any relation has been changed.
		 */
		protected function apply_relations_changes($deferred_session_key) 
		{
			$result = false;
			$this->apply_deferred_relation_changes($deferred_session_key);
			
			// sort by action desc to unbind first
			krsort($this->changed_relations);

			$this->custom_relation_save();

			foreach($this->changed_relations as $action => $relation) 
			{
				foreach($relation as $name => $info) 
				{
					switch($info['type']) 
					{
						case 'has_many':
							$defaults = array(
								'class_name' => Phpr_Inflector::classify($name),
								'foreign_key' => Phpr_Inflector::foreign_key($this->table_name, $this->primary_key)
							);

							if (is_array($info['relation']))
								$options = array_merge($defaults, $info['relation']);
							else
								$options = array_merge($defaults, array('class_name' => Phpr_Inflector::classify($info['relation'])));

							// Create model
							$object = new $options['class_name']();
							if (is_null($object))
								throw new Phpr_SystemException('Class not found: '.$options['class_name']);

							foreach ($info['values'] as $record)
							{
								$related_record = $this->find_related_record($name, $record);

								if(isset($info['relation']['primary_key']))
									$primary_key = $this->{$info['relation']['primary_key']};
								else
									$primary_key = $this->{$this->primary_key};

								if ($action == 'bind')
								{
									if (!$related_record)
									{
										$this->sql_update($object->table_name, array($options['foreign_key'] => $primary_key), DB::where($object->primary_key . ' IN (?)', $record));
										$this->after_has_many_bind($record, $name);
										$result = true;
									}
								}
								elseif ($action == 'unbind')
								{
									if ($related_record)
									{
										$this->sql_update($object->table_name, array($options['foreign_key'] => null), DB::where($object->primary_key . ' IN (?)', $record));
										$this->after_has_many_unbind($related_record, $name);

										if (array_key_exists('delete', $info['relation']) && $info['relation']['delete'])
											$related_record->delete();
										$result = true;
									}
								}
							}

							break;
						case 'has_and_belongs_to_many':
							$defaults = array(
								'class_name' => Phpr_Inflector::classify($name)
							);
							if (is_array($info['relation']))
								$options = array_merge($defaults, $info['relation']);
							else
								$options = array_merge($defaults, array('class_name' => Phpr_Inflector::classify($info['relation'])));

							// Create model
							$object = new $options['class_name']();
							if (is_null($object))
								throw new Phpr_SystemException('Class not found: '.$options['class_name']);

							if (!isset($options['primary_key']))
								$options['primary_key'] = Phpr_Inflector::foreign_key($this->table_name, $this->primary_key);

							if (!isset($options['foreign_key']))
								$options['foreign_key'] = Phpr_Inflector::foreign_key($object->table_name, $object->primary_key);

							if (!isset($options['join_table']))
								$options['join_table'] = $this->get_join_table_name($this->table_name, $object->table_name);

							if ($action == 'bind')
							{
								$this->sql_insert($options['join_table'], array($options['primary_key'], $options['foreign_key']), Phpr_Util::pairs($this->{$this->primary_key}, $info['values']));
								$result = true;
							}
							elseif ($action == 'unbind')
							{
								$this->sql_delete($options['join_table'], Db::where($options['primary_key'] . ' = ?', $this->{$this->primary_key})->where($options['foreign_key'] . ' IN (?)', array($info['values'])));
								$result = true;
							}
							break;
					}
				}
			}
			return $result;
		}

		/**
		 * Dynamically adds a new relation to the model. 
		 * @param string $type Specifies a relation type: 'has_one', 'has_many', 'has_and_belongs_to_many', 'belongs_to'
		 * @param string $name Specifies a model field name to assign the relation to
		 * @param array $options Specifies a relation options: array('class_name'=>'Related_Class', 'delete'=>true)
		 */
		public function add_relation($type, $name, $options)
		{
			$this->{$type}[$name] = $options;
			$this->has_models[$name] = $type;
			$this->added_relations[$name] = array($type, $name, $options);
		}

		/**
		 * Bind related record
		 *
		 * @param string $relation
		 * @param mixed|ActiveRecord $record
		 * @param string $deferred_session_key An edit session key for deferred bindings
		 * @return Db_ActiveRecord
		 */
		public function bind($relation, $record, $deferred_session_key = null) 
		{
			if (!$record)
				throw new Phpr_SystemException('Binding failed: the record passed to the bind method is NULL.');
			
			if ($deferred_session_key === null)
				return $this->change_relation($relation, $record, 'bind');
			else
			{
				$binding = Db_DeferredBinding::create();
				$binding->master_class_name = $this->className;
				$binding->detail_class_name = get_class($record);
				$binding->master_relation_name = $relation;
				$binding->is_bind = 1;
				$binding->detail_key_value = $record->get_primary_key_value();
				$binding->session_key = $deferred_session_key;
				$binding->save();
			}
			
			return $this;
		}
		
		/**
		 * Allows to implement custom relation saving method
		 * The method must remove processed relations from the $changed_relations collection
		 */
		protected function custom_relation_save()
		{
		}

		/**
		 * Unbind related record
		 *
		 * @param string $relation
		 * @param mixed|ActiveRecord $record
		 * @param string $deferred_session_key An edit session key for deferred bindings
		 * @return Db_ActiveRecord
		 */
		public function unbind($relation, $record, $deferred_session_key = null) 
		{
			if ($deferred_session_key === null)
				return $this->change_relation($relation, $record, 'unbind');
			else
			{
				$binding = Db_DeferredBinding::create();
				$binding->master_class_name = $this->className;
				$binding->detail_class_name = get_class($record);
				$binding->master_relation_name = $relation;
				$binding->is_bind = 0;
				$binding->detail_key_value = $record->get_primary_key_value();
				$binding->session_key = $deferred_session_key;
				$binding->save();
			}
			
			return $this;
		}

		/**
		 * Cancels all deferred bindings added during an edit session
		 * @param string $deferred_session_key An edit session key
		 * @return Db_ActiveRecord
		 */
		public function cancelDeferredBindings($deferred_session_key)
		{
			Db_DeferredBinding::cancelDeferredActions($this->className, $deferred_session_key);
			return $this;
		}

		/**
		 * Unbind all related records
		 *
		 * @param string $relation
		 * @param string $deferred_session_key An edit session key for deferred bindings
		 * @return Db_ActiveRecord
		 */
		public function unbind_all($relation, $deferred_session_key = null) 
		{
			if (!isset($this->has_models[$relation])) 
				return $this;
				
			$name = $relation;
			$type = $this->has_models[$name];
		
			if (!in_array($type, array('has_many', 'has_and_belongs_to_many'))) 
				return $this;

			foreach($this->{$name} as $record)
				$this->unbind($name, $record, $deferred_session_key);
		
			return $this;
		}

	
		/* Aggregation */

		/**
		 * Implement *_all() functions (SQL aggregate functions)
		 *
		 * @param string $operation
		 * @param string[] $parameters
		 * @return mixed
		 */
		protected function aggregate_all($operation, $parameters = null) 
		{
			if (count($parameters) && $parameters[0]) 
			{
				$field = $parameters[0];
				if ((strpos($field, '.') === false) && (strpos($field, '(') === false)) 
					$field = $this->table_name . '.' . $field;
			} else
				$field = $this->table_name . '.*';

			$field = $operation . '(' . $field . ') as ' . $operation . '_result';
		
			if (!count($this->parts['from']))
				$this->from($this->table_name, $field);

			$this->parts['fields'] = array($field);
			return $this->fetchOne($this->build_sql());
		}

		public function count() 
		{
			if ($this->calc_rows)
				return $this->found_rows;
			else
				return (int)$this->aggregate_all('count', array($this->primary_key));
		}

		/* Serialization */
	
		public function serialize($serialize_relations = true) 
		{
			// Serialize fields
			$record = array('fields' => array());
			$fields = array_keys($this->fields());
			$reflection = new ReflectionObject($this);
			
			foreach($reflection->getProperties() as $property) 
			{
				if (!in_array($property->name, $fields)) 
					continue;
					
				$record['fields'][$property->name] = $this->{$property->name};
			}
		
			if (is_string($serialize_relations)) 
				$serialize_relations = preg_split('/[\s,;]+/', $serialize_relations, -1, PREG_SPLIT_NO_EMPTY);

			// Serialize column_info
			if (self::$cache_describe) 
			{
				// If already loaded - return
				if (isset(self::$describe_cache[$this->table_name]))
					$record['describe_cache'] = self::$describe_cache[$this->table_name];
			}
		
			// Serialize relations
			if (($serialize_relations === true) || is_array($serialize_relations)) 
			{
				foreach($this->has_models as $name => $relation) 
				{
					if (!isset($this->{$name})) 
						continue;
						
					if (is_array($serialize_relations) && !in_array($serialize_relations)) 
						continue;
						
					if (count($this->{$name}) == 0) 
						continue;
						
					if ($this->{$name} instanceof ActiveRecord)
						$record['relations'][$name] = $this->{$name}->serialize($serialize_associations);
					elseif ($this->{$name} instanceof DataCollection) 
					{
						$record['relations'][$name] = array();
						foreach($this->{$name} as $item)
							$record['relations'][$name][] = $record->serialize($serialize_associations);
					}
				}
			}		

			return $record;
		}

		public function unserialize($data) 
		{
			if (!is_array($data)) 
				return null;
		
			$fields = array_keys($this->fields());
			if (!count($fields)) 
				return null;

			if (self::$cache_describe && isset($data['describe_cache']))
				self::$describe_cache[$this->table_name] = $data['describe_cache'];

			if (isset($data['fields']))
			{
				foreach($data['fields'] as $key => $value) 
				{
					if (!in_array($key, $fields)) 
						continue;
						
					$this->$key = $value;
				}
			}

			$relations = array_keys($this->has_models);
			if (isset($data['relations']))
			{
				foreach($data['relations'] as $key => $value)
				{
					if (!in_array($key, $relations))
						continue;
				
					if (isset($this->has_models[$key]['class_name']))
						$classname = $this->has_models[$key]['class_name'];
					else
						$classname = Phpr_Inflector::classify($key);

					$childs = array();
					foreach($value['records'] as $record) 
					{
						$childs[] = $child = unserialize($record);
						$child->after_fetch(true);
					}
					$this->$key = new Db_DataCollection($childs);
				}
			}

			$this->after_fetch(true);
			return $this;
		}

		public function __sleep() 
		{
			$this->serialized = $this->serialize($this->serialize_associations);
			return array('serialized');
		}
	
		public function __wakeup()
		{
			if (isset($this->serialized))
			{
				$this->initialize();
				$this->unserialize($this->serialized);
				unset($this->serialized);
			}
		}

		public function save_in_session($key)
		{
			$list = array();
			
			if (Phpr::$session->has('active_record_store'))
				$list = Phpr::$session->get('active_record_store');

			if (in_array($key, $list))
				Phpr::$session->remove($key);
			else
				$list[] = $key;

			Phpr::$session->set($key, serialize($this));
			Phpr::$session->set('active_record_store', $list);
		}

		public static function load_from_session($key) 
		{
			if (Phpr::$session->has($key))
				return unserialize(Phpr::$session->get($key));

			return null;
		}
		
		protected static function cache_instance($modelClass, $keyName, $keyValue, $obj)
		{
			if ( !isset(self::$simpleCache[$modelClass]) )
				self::$simpleCache[$modelClass] = array();

			if ( !isset(self::$simpleCache[$modelClass][$keyName]) )
				self::$simpleCache[$modelClass][$keyName] = array();
				
			self::$simpleCache[$modelClass][$keyName][$keyValue] = $obj;
		}
		
		protected static function load_cached($modelClass, $keyName, $keyValue)
		{
			if (!array_key_exists($modelClass, self::$simpleCache))
				return -1;

			if (!array_key_exists($keyName, self::$simpleCache[$modelClass]))
				return -1;

			if (!array_key_exists($keyValue, self::$simpleCache[$modelClass][$keyName]))
				return -1;

			return self::$simpleCache[$modelClass][$keyName][$keyValue];
		}
		
		public function reset_simple_cache()
		{
			self::$simpleCache[$this->className] = array();
		}
		
		public function paginate($page_index, $records_per_page)
		{
			$pagination = new Phpr_Pagination($records_per_page);
			$pagination->setRowCount($this->requestRowCount());
			$pagination->setCurrentPageIndex($page_index);
			$pagination->limitActiveRecord($this);

			return $pagination;
		}

		/*
		 * Validation
		 */

		public function valid($deferred_session_key = null) 
		{
			if ($this->before_validation($deferred_session_key) === false)
				return false;
			
			if ($this->new_record) 
			{
				if ($this->before_validation_on_create($deferred_session_key) === false)
					return false;
					
				if ($this->validate($deferred_session_key) === false)
					return false;
					
				if ($this->after_validation_on_create($deferred_session_key) === false)
					return false;
			} else {
				if ($this->before_validation_on_update($deferred_session_key) === false)
					return false;
					
				if ($this->validate($deferred_session_key) === false)
					return false;
				if ($this->after_validation_on_update($deferred_session_key) === false)
					return false;
			}

			if ($this->after_validation($deferred_session_key) === false)
				return false;
				
			return true;
		}

		public function validate($deferred_session_key = null) 
		{
			if ($this->validation && !$this->validation->validate($this, $deferred_session_key))
				$this->validation->throwException();
		}

		public function before_validation($deferred_session_key = null) 
		{
		}

		public function after_validation($deferred_session_key = null) 
		{
		}

		public function before_validation_on_create($deferred_session_key = null) 
		{
		}

		public function after_validation_on_create($deferred_session_key = null) 
		{
		}

		public function before_validation_on_update($deferred_session_key = null) 
		{
		}

		public function after_validation_on_update($deferred_session_key = null) 
		{
		}
		
		/**
		 * Visual representation and validation - column definition feature
		 */
		
		public function init_columns_info($context = null, $force = false)
		{
			global $activerecord_no_columns_info;
			
			if ($activerecord_no_columns_info)
				return false;
			
			if ($this->get_model_option('no_column_init'))
				return $this;
			
			if ($this->columns_loaded && !$force)
				return $this;

			$this->columns_loaded = true;
//			$context = $context ? $context : $this->column_definition_context;

			$this->define_columns($context);
			$this->fireEvent('onDefineColumns');
			
			return $this;
		}
		
		public function get_column_definitions($context = null)
		{
			$this->init_columns_info($context);
			
			$result = $this->column_definitions;
			
			if (!array_key_exists($this->className, self::$cached_column_definitions))
				return $result;
				
			foreach (self::$cached_column_definitions[$this->className] as $dbName=>$definition)
				$result[$dbName] = $definition->setContext($this);
			
			return $result;
		}
		
		public function disable_column_cache($context = null, $update_column_definitions = true)
		{
			$this->form_field_columns_initialized = true;
			self::$column_cache_disabled[$this->className] = true;
			$this->init_columns_info($context, true);
		}
		
		protected function is_column_cache_disabled()
		{
			return array_key_exists($this->className, self::$column_cache_disabled);
		}
		
		/**
		 * Adds a column definition for real, custom and calculated fields
		 * @param string $dbName Specifies a column database name or a calculated column name
		 * @param string $displayName Specifies a name to display in lists and forms
		 * @return Db_ColumnDefinition
		 */
		public function define_column($dbName, $displayName)
		{
			$this->defined_column_list[$dbName] = 1;
			
			if (!$this->is_column_cache_disabled())
			{
				if (!array_key_exists($this->className, self::$cached_column_definitions))
					self::$cached_column_definitions[$this->className] = array();

				if (!array_key_exists($dbName, self::$cached_column_definitions[$this->className]))
					return self::$cached_column_definitions[$this->className][$dbName] = new Db_ColumnDefinition($this, $dbName, $displayName);

				return self::$cached_column_definitions[$this->className][$dbName]->setContext($this);
			} else
				return $this->column_definitions[$dbName] = new Db_ColumnDefinition($this, $dbName, $displayName);
		}
		
		/**
		 * Adds a column definition for has_one and belongs_to relations
		 * @param string $columnName Specifies a column name. You may use any unique sql-compatible name here.
		 * @param string $relationName Specifies a relation name (should be declared as has_one or belongs_to)
		 * @param string $displayName Specifies a name to display in lists and forms
		 * @param string $type Specifies a display value type
		 * @param string $valueExpression Specifies an SQL expression for evaluating the reference value. Use '@' symbol to indicate a joined table: concat(@first_name, ' ', @last_name)
		 * @return Db_ColumnDefinition
		 */
		public function define_relation_column($columnName, $relationName, $displayName, $type, $valueExpression)
		{
			$this->defined_column_list[$columnName] = 1;

			if (!$this->is_column_cache_disabled())
			{
				if (!array_key_exists($this->className, self::$cached_column_definitions))
					self::$cached_column_definitions[$this->className] = array();

				if (!array_key_exists($columnName, self::$cached_column_definitions[$this->className]))
					return self::$cached_column_definitions[$this->className][$columnName] = new Db_ColumnDefinition($this, $columnName, $displayName, $type, $relationName, $valueExpression);

				return self::$cached_column_definitions[$this->className][$columnName]->extendModel($this);
			} else
				return $this->column_definitions[$columnName] = new Db_ColumnDefinition($this, $columnName, $displayName, $type, $relationName, $valueExpression);
		}
		
		/**
		 * Adds a column definition for has_many relations
		 * @param string $columnName Specifies a column name. You may use any unique sql-compatible name here.
		 * @param string $relationName Specifies a relation name (should be declared as has_one or belongs_to)
		 * @param string $displayName Specifies a name to display in lists and forms
		 * @param string $valueExpression Specifies an SQL expression for evaluating the reference value. Use '@' symbol to indicate a joined table: concat(@first_name, ' ', @last_name)
		 * @return Db_ColumnDefinition
		 */
		public function define_multi_relation_column($columnName, $relationName, $displayName, $valueExpression)
		{
			$this->defined_column_list[$columnName] = 1;

			if (!$this->is_column_cache_disabled())
			{
				if (!array_key_exists($this->className, self::$cached_column_definitions))
					self::$cached_column_definitions[$this->className] = array();

				if (!array_key_exists($columnName, self::$cached_column_definitions[$this->className]))
					return self::$cached_column_definitions[$this->className][$columnName] = new Db_ColumnDefinition($this, $columnName, $displayName, db_varchar, $relationName, $valueExpression);

				return self::$cached_column_definitions[$this->className][$columnName]->extendModel($this);
			} else
				return $this->column_definitions[$columnName] = new Db_ColumnDefinition($this, $columnName, $displayName, db_varchar, $relationName, $valueExpression);
		}
		
		/**
		 * Marks a column, previously added with define_column, as visible on forms.
		 * @param string $dbName Specifies a column database name or a calculated column name
		 * @param $side Specifies a side of a form the column should appear. Possible values: left, right, full
		 * return Db_FormFieldDefinition
		 */
		public function add_form_field($dbName, $side = 'full')
		{
			if (!$this->form_field_columns_initialized)
				$this->disable_column_cache();

			return $this->form_elements[] = new Db_FormFieldDefinition($this, $dbName, $side);
		}
		
		/**
		 * Adds a form section
		 * @param string $description Specifies a section description
		 * @param string $title Specifies a section title
		 * @return Db_FormSection
		 */
		public function add_form_section($description, $title=null)
		{
			if (!$this->form_field_columns_initialized)
				$this->disable_column_cache();

			return $this->form_elements[] = new Db_FormSection($title, $description);
		}

		/**
		 * Adds a form custom area. To render custom area, 
		 * create a partial with name _form_section_ID.htm,
		 * where ID is an area identifier specified with the method parameter.
		 * @param string $id Specifies an area identifier
		 * @return Db_FormCustomArea
		 */
		public function add_form_custom_area($id)
		{
			if (!$this->form_field_columns_initialized)
				$this->disable_column_cache();

			return $this->form_elements[] = new Db_FormCustomArea($id);
		}
		
		/**
		 * Adds a custom form partial
		 * @param string $path Specifies an absolute (full) path to the partial
		 * @return Db_FormPartial
		 */
		public function add_form_partial($path)
		{
			if (!$this->form_field_columns_initialized)
				self::disable_column_cache($this->className);
			
			return $this->form_elements[] = new Db_FormPartial($path);
		}

		/**
		 * Sets a specific HTML ID value to a form tab element
		 * @param string $tabName Specifies a tab name
		 * @param string $tabId Specifies a tab identifier
		 * @return Db_ActiveRecord
		 */
		public function form_tab_id($tabName, $tabId)
		{
			$this->formTabIds[$tabName] = $tabId;
			return $this;
		}
		
		/**
		 * Sets initial tab visibility value
		 * @param string $tabName Specifies a tab name
		 * @param bool $value Determines whether the tab is visible
		 * @return Db_ActiveRecord
		 */
		public function form_tab_visibility($tabName, $value)
		{
			$this->formTabVisibility[$tabName] = $value;
			return $this;
		}
		
		/**
		 * Sets a CSS class value for a specific form tab
		 * @param string $tabName Specifies a tab name
		 * @param string $value Specifies the CSS class name
		 * @return Db_ActiveRecord
		 */
		public function form_tab_css_class($tabName, $value)
		{
			$this->formTabCssClasses[$tabName] = $value;
			return $this;
		}
		
		/**
		 * Finds a form field definition by the field database name.
		 * @param string $dbName Specifies the column name.
		 * @return Db_FormFieldDefinition
		 */
		public function find_form_field($dbName)
		{
			foreach ($this->form_elements as $element)
			{
				if ($element instanceof Db_FormFieldDefinition && $element->dbName == $dbName)
					return $element;
			}
			
			return null;
		}
		
		/**
		 * Deletes a field from form.
		 * @param string $dbName Specifies the column name.
		 * @return boolean Returns true if the field has been found and deleted. Returns false otherwise.
		 */
		public function delete_form_field($dbName)
		{
			foreach ($this->form_elements as $index=>$element)
			{
				if ($element instanceof Db_FormFieldDefinition && $element->dbName == $dbName)
				{
					unset($this->form_elements[$index]);
					return true;
				}
			}
			
			return false;
		}
		
		/**
		 * Finds a column definition by a column name
		 * @param string $columnName Specifies a column name
		 */
		public function find_column_definition($columnName)
		{
			$this->init_columns_info();

			if (array_key_exists($columnName, $this->column_definitions))
				return $this->column_definitions[$columnName];
			
			if (!array_key_exists($this->className, self::$cached_column_definitions))
				return null;

			if (array_key_exists($columnName, self::$cached_column_definitions[$this->className]))
				return self::$cached_column_definitions[$this->className][$columnName]->setContext($this);

			return null;
		}

		/**
		 * Override this method to define columns, references and form fields in your models. 
		 * Use define_column, define_relation_column, add_form_field, add_form_custom_area and add_form_section methods inside it.
		 */
		protected function define_columns($context = null)
		{
		}

		/**
		 * Override this method to define form fields. Usually you may use define_columns methods for defining form fields,
		 * but in some cases form appearance depends on data represented by the model.
		 * Use add_form_field, add_form_custom_area and add_form_section methods inside it.
		 */
		public function define_form_fields($context = null)
		{
		}

		/**
		 * Returns a formatted field value. The field should be defined with define_column method.
		 * 
		 * Important note about date and datetime fields.
		 * Date fields are NOT converted to GMT during saving to the database 
		 * and displayField method always returns the field value as is.
		 *
		 * Datetime fields are CONVERTED to GMT during saving and displayField returns value converted
		 * back to a time zone specified in the configuration file.
		 *
		 * @param string $dbName Specifies a field database name
		 * @param string $media Specifies a media - a list or a form. Text values could be truncated for the list media.
		 */
		public function displayField($dbName, $media = 'form')
		{
			$column_definitions = $this->get_column_definitions();
			
			if (!array_key_exists($dbName, $column_definitions))
				throw new Phpr_SystemException('Cannot execute method "displayField" for field '.$dbName.' - the field is not defined in column definition list.');

			return $column_definitions[$dbName]->displayValue($media);
		}
		
		/**
		 * Alias for the displayField method
		 */		
		public function columnValue($dbName)
		{
			return $this->displayField($dbName);
		}
	}

?>