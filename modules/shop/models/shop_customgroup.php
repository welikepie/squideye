<?php

	class Shop_CustomGroup extends Db_ActiveRecord
	{
		public $table_name = 'shop_custom_group';
		protected $api_added_columns = array();

		protected static $product_sort_orders = null;

		public static function create()
		{
			return new self();
		}
		
		public $has_and_belongs_to_many = array(
			'all_products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_customgroups', 'primary_key'=>'shop_custom_group_id', 'foreign_key'=>'shop_product_id', 'order'=>'name'),
			
			// Interface products list
			//
			'products'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_products_customgroups', 'primary_key'=>'shop_custom_group_id', 'foreign_key'=>'shop_product_id', 'order'=>'name', 'conditions'=>'((shop_products.enabled=1 and not (
			shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.in_stock=0))
		)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
			and not (
			grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.in_stock=0))
		))) and (shop_products.disable_completely is null or shop_products.disable_completely = 0)', 'order'=>'shop_products_customgroups.product_group_sort_order')
		);
		
		public $calculated_columns = array( 
			'product_num'=>array('sql'=>'select count(*) from shop_products,  shop_products_customgroups where
				shop_products.id=shop_products_customgroups.shop_product_id and
				shop_products_customgroups.shop_custom_group_id=shop_custom_group.id', 'type'=>db_number)
		);

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Group Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('code', 'Code')->validation()->fn('trim')->required()->unique();

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			
			$this->define_multi_relation_column('all_products', 'all_products', 'Products', $front_end ? null : '@name')->invisible()->validation();
			$this->define_column('product_num', 'Products');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomGroupModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->add_form_field('name')->tab('Group');
			$this->add_form_field('code')->tab('Group')->comment('You will use the code to refer the group to output its contents on pages.', 'above');
			
			$this->add_form_section('Manage the product group contents. You can manage the product order by dragging the arrow icons up and down.')->tab('Products');

			if (!$front_end)
				$this->add_form_field('all_products')->tab('Products')->comment('Products belonging to the group', 'above')->renderAs('products')->referenceSort('@name');
			Backend::$events->fireEvent('shop:onExtendCustomGroupForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomGroupFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function after_delete()
		{
			Db_DbHelper::query('delete from shop_products_customgroups where shop_custom_group_id=:id', array('id'=>$this->id));
		}
		
		public function get_products_orders()
		{
			if (self::$product_sort_orders !== null)
				return self::$product_sort_orders;
			
			$orders = Db_DbHelper::objectArray('select product_group_sort_order, shop_product_id from shop_products_customgroups where shop_custom_group_id=:group_id', 
			array('group_id'=>$this->id));
			
			$result = array();
			foreach ($orders as $order_item)
				$result[$order_item->shop_product_id] = $order_item->product_group_sort_order;

			return self::$product_sort_orders = $result;
		}
		
		public function set_product_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update shop_products_customgroups set product_group_sort_order=:product_group_sort_order where shop_product_id=:product_id and shop_custom_group_id=:group_id', array(
					'product_group_sort_order'=>$order,
					'product_id'=>$id,
					'group_id'=>$this->id
				));
			}
		}

		/**
		 * Returns a list of the custom group products
		 * @return Shop_Product Returns an object of the Shop_Product. 
		 * Call the find_all() method of this object to obtain a list of products (Db_DataCollection object).
		 */
		public function list_products($options = array())
		{
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array();

			if (!is_array($sorting))
				$sorting = array();

			$allowed_sorting_columns = Shop_Product::list_allowed_sort_columns();

			$custom_sorting = false;

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, $allowed_sorting_columns))
					continue;

				$custom_sorting = true;

				if (strpos($sorting_column, 'price') !== false)
				{
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				}
				elseif(strpos($sorting_column, 'manufacturer') !== false)
					$sorting_column = str_replace('manufacturer', 'manufacturer_link_calculated', $sorting_column);
				elseif (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}

			$customer_group_id = Cms_Controller::get_customer_group_id();

			$product_obj = $this->products_list;
			
			if ($custom_sorting)
				$product_obj->reset_order();

			$product_obj->apply_customer_group_visibility();
			$product_obj->apply_catalog_visibility();
			
			$product_obj->where('
				((enable_customer_group_filter is null or enable_customer_group_filter=0) or (
					enable_customer_group_filter = 1 and
					exists(select * from shop_products_customer_groups where shop_product_id=shop_products.id and customer_group_id=?)
				))
			', $customer_group_id);
			
			if ($custom_sorting)
			{
				$sort_str = implode(', ', $sorting);
				$product_obj->order($sort_str);
			}

			return $product_obj;
		}
	}

?>