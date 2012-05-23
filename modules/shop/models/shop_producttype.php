<?php

	class Shop_ProductType extends Db_ActiveRecord
	{
		public $table_name = 'shop_product_types';
		protected $api_added_columns = array();
		
		public static function create()
		{
			return new self();
		}
		
		public static function get_default_type()
		{
			if ($default = self::create()->where('is_default=1')->find())
				return $default;
			else
				return self::create()->find();
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('name', 'Type Name')->order('asc')->validation()->fn('trim')->required('Please specify the product type name.');
			$this->define_column('code', 'API Code')->validation()->fn('trim')->unique('API code is already in use by another product type.');
			$this->define_column('is_default', 'Is default');
			$this->define_column('files', 'Enable files')->defaultInvisible();
			$this->define_column('inventory', 'Enable inventory tracking')->defaultInvisible();
			$this->define_column('shipping', 'Enable shipping')->defaultInvisible();
			$this->define_column('grouped', 'Enable grouped products')->defaultInvisible();
			$this->define_column('options', 'Enable options')->defaultInvisible();
			$this->define_column('extras', 'Enable extra options')->defaultInvisible();
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductTypeModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name', 'left')->tab('Product Type');
			$this->add_form_field('code', 'right')->tab('Product Type');
			$this->add_form_field('is_default')->tab('Product Type')->renderAs('checkbox')->comment('Use this checkbox if you want this product type to be applied to all new products by default.');
			$this->add_form_field('files')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Files tab on the product page.');
			$this->add_form_field('inventory')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Inventory tab on the product page.');
			$this->add_form_field('shipping')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Shipping tab on the product page.');
			$this->add_form_field('grouped')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Grouped tab on the product page.');
			$this->add_form_field('options')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Options tab on the product page.');
			$this->add_form_field('extras')->tab('Product Type')->renderAs('checkbox')->comment('Select to enable the Extras tab on the product page.');
			
			Backend::$events->fireEvent('shop:onExtendProductTypeForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductTypeFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}

		public function before_delete($id = null)
		{
			//Do not allow deleting a product type if there are products assigned to it
			if ($products_num = Db_DbHelper::scalar('select count(*) from shop_products where product_type_id=:id', array('id'=>$this->id)))
				throw new Phpr_ApplicationException("Cannot delete this product type. There are $products_num product(s) belonging to it.");
			//Ensure there is at least one product type available at all times
			$count_types = Db_DbHelper::scalar('select count(*) from shop_product_types');
			if($count_types < 2)
				throw new Phpr_ApplicationException("Product type cannot be deleted because it is the only one configured. There should always be at least one product type configured.");
		}

		public function after_save()
		{
			//If the saved product type is now the default, make others not default
			if ($this->is_default)
				Db_DbHelper::query('update shop_product_types set is_default=0 where id<>:id', array('id'=>$this->id));
		}
	}
?>