<?php

	class Shop_CountryState extends Db_ActiveRecord
	{
		public $table_name = 'shop_states';
		
		public $calculated_columns = array(
			'country_state_name'=>array('sql'=>"concat(shop_countries.name, '/', shop_states.name)", 'type'=>db_text, 'join'=>array('shop_countries'=>'shop_countries.id=shop_states.country_id'))
		);
		
		protected static $id_cache = array();
		protected static $simple_object_list = array();
		protected static $simple_name_list = array();
		
		public static function create($no_column_info = false)
		{
			if (!$no_column_info)
				return new self();
			else
				return new self(null, array('no_column_init'=>true, 'no_validation'=>true));
		}

		public function define_columns($context = null)
		{
			$this->define_column('code', 'Code')->validation()->fn('trim')->required()->fn('mb_strtoupper');
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('country_state_name', 'Full Name')->invisible();
		}

		public function define_form_fields($context = null)
		{
			$field = $this->add_form_field('code');
			if ($context != 'preview')
				$field->comment('Specify state code. You are not restricted by any rules about state codes. Codes are used for referring states in your custom tax and shipping rules.', 'above');
			else
				$field->comment('Codes are used for referring states in your custom tax and shipping rules.', 'above');
			
			$this->add_form_field('name');
		}

		public function check_in_use()
		{
			$bind = array('id'=>$this->id);
			$in_use = Db_DbHelper::scalar('select count(*) from shop_customers where shipping_state_id=:id or billing_state_id=:id', $bind);
			
			if ($in_use)
				throw new Phpr_ApplicationException("Cannot delete state because it is in use.");
				
			$in_use = Db_DbHelper::scalar('select count(*) from shop_orders where shipping_state_id=:id or billing_state_id=:id', $bind);
			
			if ($in_use)
				throw new Phpr_ApplicationException("Cannot delete state because it is in use.");
		}
		
		public function before_delete($id=null)
		{
			$this->check_in_use();
		}
		
		public static function find_by_id($id)
		{
			if (array_key_exists($id, self::$id_cache))
				return self::$id_cache[$id];
				
			return self::$id_cache[$id] = self::create(true)->find($id);
		}
		
		public static function get_object_list($country_id)
		{
			if (array_key_exists($country_id, self::$simple_object_list))
				return self::$simple_object_list[$country_id];

			$records = Db_DbHelper::objectArray('select * from shop_states where country_id=:country_id order by name', array('country_id'=>$country_id));
			$result = array();
			foreach ($records as $state)
				$result[$state->id] = $state;

			return self::$simple_object_list[$country_id] = $result;
		}
		
		public static function get_name_list($country_id)
		{
			if (array_key_exists($country_id, self::$simple_name_list))
				return self::$simple_name_list[$country_id];
			
			$states = self::get_object_list($country_id);
			$result = array();
			foreach ($states as $id=>$state)
				$result[$id] = $state->name;
				
			return self::$simple_name_list[$country_id] = $result;
		}
	}

?>