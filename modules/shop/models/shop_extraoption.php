<?php

	class Shop_ExtraOption extends Db_ActiveRecord
	{
		public $table_name = 'shop_extra_options';
		protected $api_added_columns = array();
		
		public $has_many = array(
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_ExtraOption' and field='images'", 'order'=>'sort_order, id', 'delete'=>true)
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_column('description', 'Description')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('price', 'Price')->currency(true)->validation()->fn('trim')->required();
			$this->define_column('group_name', 'Group')->validation();

			if (!$front_end)
				$this->define_multi_relation_column('images', 'images', 'Images', '@name')->invisible();

			$this->define_column('weight', 'Weight')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('width', 'Width')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('height', 'Height')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('depth', 'Depth')->defaultInvisible()->validation()->fn('trim');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendExtraOptionModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('description')->comment('Specify the option description, e.g. "Gift wrap".', 'above')->size('small')->tab('Option');
			$this->add_form_field('price')->comment('Specify a value to be added to the product price if this option is selected.', 'above')->tab('Option');
			$this->add_form_field('group_name')->comment('You can group extras with equal group names.', 'above')->tab('Option')->renderAs(frm_dropdown);

			$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded')->fileDownloadBaseUrl(url('ls_backend/files/get/'));

			$this->add_form_field('weight', 'left')->tab('Shipping');
			$this->add_form_field('width', 'right')->tab('Shipping');
			$this->add_form_field('height', 'left')->tab('Shipping');
			$this->add_form_field('depth', 'right')->tab('Shipping');

			Backend::$events->fireEvent('shop:onExtendExtraOptionForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetExtraOptionFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_group_name_options($key = -1)
		{
			$result = array();
			$result[''] = '<group is not assigned>';
			$result[-1] = '<create new group>';
			if (strlen($this->group_name))
				$result[$this->group_name] = $this->group_name;
			
			$groups = self::get_group_names();
			foreach ($groups as $group)
			{
				if (!strlen($group))
					continue;

				$result[$group] = $group;
			}
			
			return $result;
		}

		public function copy()
		{
			$obj = new self();
			$obj->description = $this->description;
			$obj->price = $this->price;
			$obj->extra_option_sort_order = $this->extra_option_sort_order;
			$obj->group_name = $this->group_name;
			
			$images = $this->images;
			foreach ($obj->images as $existing_image)
				$existing_image->delete;
				
			foreach ($this->api_added_columns as $field)
				$obj->$field = $this->$field;

			foreach ($images as $image)
			{
				$image_copy = $image->copy();
				$image_copy->master_object_class = get_class($obj);
				$image_copy->field = $image->field;
				$image_copy->save();
				$obj->images->add($image_copy);
			}

			return $obj;
		}
		
		public function before_save($deferred_session_key = null) 
		{
			$this->option_key = md5($this->description);
		}

		/*
		 * Returns the extra option price
		 */
		public function get_price($product, $force_tax = false)
		{
			$price = $this->get_price_no_tax($product);
		
			$include_tax = $force_tax || Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($product->tax_class_id, $price) + $price;
		}
		
		/*
		 * Returns the extra option price without the tax included, regardless of the tax configuration
		 */
		public function get_price_no_tax($product)
		{
			$price = $this->price;
			
			$prices = Backend::$events->fireEvent('shop:onGetProductExtraPrice', $this, $product);

			foreach ($prices as $custom_price)
			{
				if (strlen($custom_price))
				{
					$price = $custom_price;
					break;
				}
			}
			
			return $price;
		}

		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			$result = -1;

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				if ($id == -1)
					$result = $order;

				Db_DbHelper::query('update shop_extra_options set extra_option_sort_order=:extra_option_sort_order where id=:id', array(
					'extra_option_sort_order'=>$order,
					'id'=>$id
				));
			}

			return $result;
		}
		
		public function after_create() 
		{
//			$this->option_key = md5($this->id);
			
			Db_DbHelper::query('update shop_extra_options set extra_option_sort_order=:extra_option_sort_order where id=:id', array(
				'extra_option_sort_order'=>$this->id,
				'id'=>$this->id
			));

			$this->extra_option_sort_order = $this->id;
		}
		
		public static function get_group_names()
		{
			return Db_DbHelper::scalarArray('select distinct group_name from shop_extra_options order by group_name');
		}
		
		/**
		 * Finds an extra option belonging to a specific product by its key
		 */
		public static function find_product_extra_option($product, $extra_key)
		{
			$product_extras = $product->extra_options;
			foreach ($product_extras as $option)
			{
				if ($option->option_key == $extra_key)
					return $option;
			}
			
			return null;
		}
		
		public function volume()
		{
			return $this->width*$this->height*$this->depth;
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public static function sort_extra_options_by_group($option1, $option2)
		{
			return strcmp($option1->group_name, $option2->group_name);
		}
	}

?>