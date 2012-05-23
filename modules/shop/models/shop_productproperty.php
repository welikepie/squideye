<?php

	class Shop_ProductProperty extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_properties';

		public $implement = 'Db_Sortable';
		public $custom_columns = array('value_pickup'=>db_text);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Attribute Name')->validation()->fn('trim')->required();

			$this->define_column('value_pickup', 'Value');
			$this->define_column('value', 'Value')->validation()->fn('trim');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name');
			$this->add_form_field('value_pickup')->renderAs(frm_dropdown)->emptyOption('<known attribute values>')->comment('Please enter a value to the text field below, or choose an existing value.', 'above')->cssClassName('relative');
			$this->add_form_field('value')->renderAs(frm_textarea)->size('small')->noLabel()->cssClassName('relative');
		}
		
		public function copy()
		{
			$obj = self::create();
			$obj->name = $this->name;
			$obj->value = $this->value;
			
			return $obj;
		}
		
		public function get_value_pickup_options($key = -1)
		{
			$result = array();
			
			$name = mb_strtolower(trim($this->name));
			$values = Db_DbHelper::objectArray('select distinct id, value from shop_product_properties where lower(name)=:name group by value order by value', array('name'=>$name));
			foreach ($values as $value_obj)
			{
				$value = Phpr_Html::strTrim(str_replace("\n", " ", $value_obj->value), 40);
				$result[$value_obj->id] = $value;
			}
			
			return $result;
		}
		
		public static function list_unique_names()
		{
			return Db_DbHelper::scalarArray('select distinct name from shop_product_properties');
		}
		
		public function load_value($attribute_id)
		{
			if (!strlen($attribute_id))
				return;
				
			$this->value = Db_DbHelper::scalar('select value from shop_product_properties where id=:id', array('id'=>$attribute_id));
		}
		
		public static function list_unique_values($name)
		{
			$values = Db_DbHelper::scalarArray('select distinct value from shop_product_properties where name=:name', array('name'=>$name));
			$result = array();
			foreach ($values as $value)
			{
				if (strlen($value) && !in_array($value, $result))
					$result[] = $value;
			}

			sort($result);
			return $result;
		}
	}

?>