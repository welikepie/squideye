<?php

	class Shop_Country extends Db_ActiveRecord
	{
		public $table_name = 'shop_countries';
		
		public $enabled = 1;
		public $enabled_in_backend = 1;
		
		protected static $simple_object_list = null;
		protected static $simple_name_list = null;
		protected static $id_cache = array();

		public $has_many = array(
			'states'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'country_id', 'order'=>'shop_states.name', 'delete'=>true)
		);

		public static function create($no_column_info = false)
		{
			if (!$no_column_info)
				return new self();
			else
				return new self(null, array('no_column_init'=>true, 'no_validation'=>true));
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('code', '2-digit ISO country code')->validation()->fn('trim')->required()->maxLength(2, '2-digit ISO country code must contain exactly 2 letters.')->regexp('/^[a-z]{2}$/i', 'Country code must contain 2 Latin letters')->fn('mb_strtoupper');
			$this->define_column('code_3', '3-digit ISO country code')->validation()->fn('trim')->required()->maxLength(3, '3-digit ISO country code must contain exactly 3 letters.')->regexp('/^[a-z]{3}$/i', 'Country code must contain 3 Latin letters')->fn('mb_strtoupper');
			$this->define_column('code_iso_numeric', 'Numeric ISO country code')->validation()->fn('trim')->required()->maxLength(3, 'Numeric ISO country code must contain exactly 3 digits.')->regexp('/^[0-9]{3}$/i', 'Country code must contain 3 digits')->fn('mb_strtoupper');
			
			$this->define_column('enabled', 'Enabled')->validation();
			$this->define_column('enabled_in_backend', 'Enabled in the Administration Area')->listTitle('Enabled in AA')->validation();
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if (!$front_end)
				$this->define_multi_relation_column('states', 'states', 'States', "@name")->invisible();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->tab('Country');
			$field = $this->add_form_field('code')->tab('Country');
			if ($context != 'preview')
				$field->comment('Specify 2-letter country code. You can find country names and codes here: <a href="http://en.wikipedia.org/wiki/ISO_3166-1" target="_blank">http://en.wikipedia.org/wiki/ISO_3166-1</a>', 'above', true);
			else
				$field->comment('2-letter country code. You can find country names and codes here: http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2', 'above');

			$field = $this->add_form_field('code_3', 'left')->tab('Country');
			if ($context != 'preview')
				$field->comment('Specify 3-letter country code, for example USA.', 'above', true);
			else
				$field->comment('3-letter country code.', 'above');

			$field = $this->add_form_field('code_iso_numeric', 'right')->tab('Country');
			if ($context != 'preview')
				$field->comment('Specify 3-digit numeric country code, for example 840 for USA.', 'above', true);
			else
				$field->comment('3-digit numeric country code.', 'above');
				
			$this->add_form_field('enabled')->tab('Country')->comment('Disabled countries are not shown on the front-end store.', 'above');
			$enabled_backend = $this->add_form_field('enabled_in_backend')->tab('Country')->comment('Use this checkbox if you want the country to be enabled in the Administration Area.', 'above');
			
			if ($this->enabled)
				$enabled_backend->disabled();
			
			$this->add_form_field('states')->tab('States');
		}
		
		public function before_delete($id=null)
		{
			$bind = array('id'=>$this->id);
			$in_use = Db_DbHelper::scalar('select count(*) from shop_customers where shipping_country_id=:id or billing_country_id=:id', $bind);
			
			if ($in_use)
				throw new Phpr_ApplicationException("Cannot delete country because it is in use.");
				
			$in_use = Db_DbHelper::scalar('select count(*) from shop_orders where shipping_country_id=:id or billing_country_id=:id', $bind);
			
			if ($in_use)
				throw new Phpr_ApplicationException("Cannot delete country because it is in use.");
		}
		
		public static function get_list($country_id = null)
		{
			$obj = new self(null, array('no_column_init'=>true, 'no_validation'=>true));
			$obj->order('name')->where('enabled = 1');
			if (strlen($country_id))
				$obj->orWhere('id=?', $country_id);
				
			return $obj->find_all();
		}
		
		public function update_states($enabled, $enabled_in_backend)
		{
			if ($this->enabled != $enabled || $this->enabled_in_backend != $enabled_in_backend)
			{
				$this->enabled = $enabled;
				$this->enabled_in_backend = $enabled_in_backend;

				$this->save();
			}
		}

		public static function get_object_list($default = -1)
		{
			if (self::$simple_object_list && !$default)
				return self::$simple_object_list;

			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 or id=:id order by name', array('id'=>$default));
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country;

			if (!$default)
				return self::$simple_object_list = $result;
			else 
				return $result;
		}

		public static function get_name_list()
		{
			if (self::$simple_name_list)
				return self::$simple_name_list;
			
			$countries = self::get_object_list();
			$result = array();
			foreach ($countries as $id=>$country)
				$result[$id] = $country->name;
				
			return self::$simple_name_list = $result;
		}
		
		public static function find_by_id($id)
		{
			if (array_key_exists($id, self::$id_cache))
				return self::$id_cache[$id];
				
			return self::$id_cache[$id] = self::create(true)->find($id);
		}

		public function before_save($deferred_session_key = null) 
		{
			if ($this->enabled)
				$this->enabled_in_backend = 1;
		}
	}
?>