<?php

	class Shop_CustomerGroup extends Db_ActiveRecord
	{
		const guest_group = 'guest';
		const registered_group = 'registered';
		
		public $table_name = 'shop_customer_groups';
		protected $api_added_columns = array();

		protected static $guest_group = null;
		protected static $cache = null;

		public $calculated_columns = array(
			'customer_num'=>array('sql'=>"(select count(*) from shop_customers where customer_group_id=shop_customer_groups.id)", 'type'=>db_number)
		);
		
		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the group name");
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('customer_num', 'Customers')->validation()->fn('trim');
			
			$this->define_column('disable_tax_included', 'Do not include tax into displayed product prices')->listTitle('Disable Tax Inclusive');

			$this->define_column('code', 'API Code')->validation()->fn('trim')->fn('mb_strtolower')->unique('The API Code "%s" is already in use.');
			$this->define_column('tax_exempt', 'Tax Exempt');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomerGroupModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name');
			$this->add_form_field('description');
			$this->add_form_field('disable_tax_included')->comment('Use this checkbox if you want to override the global "Display catalog/cart prices including tax" option for customers belonging to this customer group.');
			$this->add_form_field('tax_exempt')->comment('Use this feature if the tax should not be applied to customers from this group.');
			
			$field = $this->add_form_field('code')->comment('You can use the API code for referring the customer group in the API calls.', 'above');
			if ($this->code == self::guest_group || $this->code == self::registered_group)
				$field->disabled = true;
				
			Backend::$events->fireEvent('shop:onExtendCustomerGroupForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomerGroupFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}

			return false;
		}

		public function before_delete($id=null)
		{
			if ($this->code == self::guest_group || $this->code == self::registered_group)
				throw new Phpr_ApplicationException("The Guest and Registered customer groups cannot be deleted.");
			
			if ($this->customer_num)
				throw new Phpr_ApplicationException("The group cannot be deleted because {$this->customer_num} customer(s) belong to this group.");
		}
		
		public static function get_guest_group()
		{
			if (self::$guest_group !== null)
				return self::$guest_group;
				
			self::$guest_group = self::create()->where('code = ?', self::guest_group)->find();
			
			if (!self::$guest_group)
				throw new Phpr_ApplicationException("The Guest customer group is not found in the database.");
			
			return self::$guest_group;
		}
		
		/**
		 * Returns a list of customer groups by their codes
		 * @param array $codes Specifies a list of customer group codes to find
		 */
		public static function list_groups_by_codes($codes)
		{
			foreach ($codes as &$code)
				$code = mb_strtolower($code);
				
			if (!is_array($codes))
				$codes = array($codes);

			if (!count($codes))
				return new Db_DataCollection();

			return self::create()->where('code in (?)', array($codes))->find_all();
		}
		
		/**
		 * Returns a list of a all customer groups
		 */
		public static function list_groups()
		{
			if (self::$cache === null)
				self::$cache = self::create()->find_all()->as_array(null, 'id');
				
			return self::$cache;
		}
		
		public static function find_by_id($id)
		{
			$groups = self::list_groups();
			if (isset($id, $groups))
				return $groups[$id];
				
			return null;
		}
		
		public static function is_tax_exempt($id)
		{
			$group = self::find_by_id($id);
			if (!$group)
				return false;
				
			return $group->tax_exempt;
		}
	}

?>