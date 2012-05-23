<?

	class Shop_CustomAttribute extends Db_ActiveRecord
	{
		public $table_name = 'shop_custom_attributes';
		protected $api_added_columns = array();

		public $implement = 'Db_Sortable';

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('attribute_values', 'Values')->validation()->fn('trim')->required();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendOptionModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->comment('Specify the option name, e.g. "Colors" to display near the attribute drop-down menu.', 'above');
			$this->add_form_field('attribute_values')->renderAs(frm_textarea)->comment('Specify option values, e.g. "Red, Green, Blue" - one value per line.', 'above');

			Backend::$events->fireEvent('shop:onExtendOptionForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetOptionFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		
		public function list_values()
		{
			$values = explode("\n", $this->attribute_values);
			$result = array();
			foreach ($values as $value)
			{
				if (strlen($value))
					$result[] = $value;
			}

			return $result;
		}
		
		public function copy()
		{
			$obj = new self();
			$obj->name = $this->name;
			$obj->attribute_values = $this->attribute_values;

			return $obj;
		}

		public function before_save($deferred_session_key = null) 
		{
			$this->option_key = md5($this->name);
		}
		
		public static function list_unique_names()
		{
			return Db_DbHelper::scalarArray('select distinct name from shop_custom_attributes');
		}
		
		public static function list_unique_values($name)
		{
			$values = Db_DbHelper::scalarArray('select distinct attribute_values from shop_custom_attributes where name=:name', array('name'=>$name));
			$result = array();
			foreach ($values as $value)
			{
				$value_set = explode("\n", $value);
				foreach ($value_set as $attr_value)
				{
					$attr_value = trim($attr_value);
					if (strlen($attr_value) && !in_array($attr_value, $result))
						$result[] = $attr_value;
				}
			}
			
			sort($result);

			return $result;
		}
	}