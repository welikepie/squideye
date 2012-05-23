<?php

	class Shop_OrderItem extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_items';

		public $belongs_to = array(
			'product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'shop_product_id'),
			'parent_order'=>array('class_name'=>'Shop_Order', 'foreign_key'=>'shop_order_id')
		);
		
		public $calculated_columns = array(
			'product_name'=>array(
				'sql'=>'shop_products.name', 
				'join'=>array('shop_products'=>'shop_products.id=shop_product_id'), 'type'=>db_text),
			'product_sku'=>array('sql'=>'shop_products.sku')
		);
		
		public $has_many = array(
			'uploaded_files'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_OrderItem' and field='uploaded_files'", 'order'=>'id', 'delete'=>true),
		);
		
		public $applied_discount = 0;
		protected $api_added_columns = array();
		protected static $cache = array();

		/*
		 * Single price is price of an item without extras
		 */
		
		public $custom_columns = array('single_price'=>db_float, 'unit_total_price'=>db_float, 'subtotal'=>db_float, 'subtotal_tax_incl'=>db_float, 'total_price'=>db_float, 'bundle_item_total'=>db_float, 'bundle_item_total_tax_incl'=>db_float);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('product_name', 'Product');
			$this->define_column('product_sku', 'Product SKU');
			
			$this->define_column('quantity', 'Quantity')->validation()->fn('trim')->required('Please specify item quantity.');
			$this->define_column('price', 'Price')->currency(true)->validation()->fn('trim')->required('Please specify item price');
			$this->define_column('cost', 'Cost')->currency(true)->validation()->fn('trim');

			$this->define_column('discount', 'Discount')->currency(true)->validation()->fn('trim')->required('Please specify discount amount.');
			$this->define_column('total_price', 'Total')->currency(true);
			$this->define_multi_relation_column('uploaded_files', 'uploaded_files', 'Uploaded files', '@name')->defaultInvisible();

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendOrderItemModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			if ($context == 'preview')
			{
				$this->add_form_field('product_name', 'left')->tab('Item Details');
				$this->add_form_field('product_sku', 'right')->tab('Item Details');

				if ($this->product->grouped_option_desc)
					$this->add_form_custom_area('item_grouped_option_value')->tab('Item Details');

				$this->add_form_field('price', 'left')->tab('Item Details');
				$this->add_form_field('cost', 'right')->tab('Item Details');
				$this->add_form_field('quantity', 'left')->tab('Item Details');
				$this->add_form_field('discount', 'right')->tab('Item Details');
				$this->add_form_field('total_price', 'left')->tab('Item Details');

				$options = $this->get_options();
				if ($options)
					$this->add_form_custom_area('item_options')->tab('Options');

				$extras = $this->get_extra_options();
				if ($extras)
					$this->add_form_custom_area('item_extras')->tab('Extras');
			} else
			{
				$deleted_options = $this->get_deleted_options();
				
				if (
					$this->product->grouped_products->count ||
					$this->product->properties->count ||
					$this->product->options->count ||
					$deleted_options
				)
					$this->add_form_custom_area('item_config')->tab('Item Configuration');
					
				$this->add_form_custom_area('item_pricing')->tab('Quantity and Pricing');
				$this->add_form_custom_area('item_extras')->tab('Extras');
				$this->form_tab_css_class('Extras', 'fullsize');
			}
			
			Backend::$events->fireEvent('shop:onExtendOrderItemForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}

			$this->add_form_field('uploaded_files')->renderAs(frm_file_attachments)->tab('Files')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noLabel();
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetOrderItemFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function eval_single_price()
		{
			$result = $this->price;

			if (!strlen($this->extras))
				return $result;

			$extras = unserialize($this->extras);
			foreach ($extras as $extra)
				$result += $extra[0];

			return $result;
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->extras_price = 0;
			if (strlen($this->extras))
			{
				$extras = unserialize($this->extras);
				foreach ($extras as $extra)
					$this->extras_price += $extra[0];
			}
			
			if (!strlen($this->discount))
				$this->discount = 0;

			$this->discount_tax_included = $this->discount + Shop_TaxClass::get_total_tax($this->product->tax_class_id, $this->discount);
			
			$total_price = $this->price + $this->extras_price;
			$this->price_tax_included = $total_price + Shop_TaxClass::get_total_tax($this->product->tax_class_id, $total_price);
		}
		
		public function apply_tax_array($tax_array)
		{
			if (isset($tax_array[0]))
			{
				$this->tax = $tax_array[0]->rate;
				$this->tax_name_1 = $tax_array[0]->name;
			} else
			{
				$this->tax = 0;
				$this->tax_name_1 = null;
			}

			if (isset($tax_array[1]))
			{
				$this->tax_2 = $tax_array[1]->rate;
				$this->tax_name_2 = $tax_array[1]->name;
			} else
			{
				$this->tax_2 = 0;
				$this->tax_name_2 = null;
			}
		}
		
		public function eval_unit_total_price()
		{
			return ($this->single_price - $this->discount);
		}
		
		public function eval_subtotal()
		{
			return $this->unit_total_price*$this->quantity;
		}
		
		public function eval_subtotal_tax_incl()
		{
			return ($this->price_tax_included - $this->discount_tax_included) *$this->quantity;
		}
		
		public function eval_total_price()
		{
			return ($this->single_price - $this->discount)*$this->quantity;
		}
		
		public function eval_bundle_item_total()
		{
			$master_item = $this->get_master_bundle_order_item();
			if (!$master_item)
				return $this->total_price;

			return round($this->total_price/$master_item->quantity, 2);
		}

		public function eval_bundle_item_total_tax_incl()
		{
			$master_item = $this->get_master_bundle_order_item();
			if (!$master_item)
				return $this->subtotal_tax_incl;

			return round($this->subtotal_tax_incl/$master_item->quantity, 2);
		}
		
		public function init_empty_item($product, $customer_group_id, $customer, $bundle_item_product_id = null)
		{
			$this->quantity = 1;

			if (!$product->options->count)
				$this->options = serialize(array());
			else 
			{
				$options = array();
				foreach ($product->options as $option)
				{
					$name = $option->name;
					$values = $option->list_values();
					if (!count($values))
						continue;
						
					$options[$name] = $values[0];
				}
				$this->options = serialize($options);
			}

			$item_quantity = 1;
			if ($product->tier_prices_per_customer && $customer)
				$item_quantity += $customer->get_purchased_item_quantity($product);
				
			if (!$bundle_item_product_id)
				$price = max($product->price_no_tax($item_quantity, $customer_group_id) - $product->get_discount($item_quantity, $customer_group_id), 0);
			else
			{
				$bundle_item_product = Shop_BundleItemProduct::create()->find($bundle_item_product_id);
				if (!$bundle_item_product)
					throw new Phpr_ApplicationException('Bundle item product not found.');
					
				$price = max($bundle_item_product->get_price_no_tax($product, $item_quantity, $customer_group_id) - $product->get_discount($item_quantity, $customer_group_id), 0);
			}

			$this->extras = serialize(array());
			$this->shop_product_id = $product->id;
			$this->price = $price;
			$this->cost = $product->cost;
			$this->discount = 0;
			$this->auto_discount_price_eval = 1;
			
			return $this;
		}
		
		public function update_bundle_item_quantities($items)
		{
			foreach ($items as $item)
			{
				if ($item->bundle_master_order_item_id == $this->id)
				{
					$item->quantity = $this->quantity*$item->get_bundle_item_quantity();
					$item->save();
				}
			}
		}
		
		public function set_from_post($session_key = null)
		{
			$product_options = post('product_options', array());
			$option_names = post('product_option_name', array());
			$options = array();
			
			$price = trim(post('price'));
			$discount = trim(post('discount'));

			$discount_is_percentage = substr($discount, -1) == '%';
			if ($discount_is_percentage)
			{
				if (!strlen($price))
					throw new Phpr_ApplicationException('Please specify item price');

				if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $price))
					throw new Phpr_ApplicationException('Invalid price value. Please specify a number.');

				if (!preg_match('/^([0-9]+\.[0-9]+%|[0-9]+%?)$/', $discount))
					throw new Phpr_ApplicationException('Invalid discount value. Please specify a number or percentage value.');

				$discount = substr($discount, 0, -1);
				$_POST['discount'] = $price*$discount/100;
			}

			foreach ($product_options as $option_key=>$value)
			{
				if (!array_key_exists($option_key, $option_names))
					throw new Phpr_ApplicationException('Option name is not specified');

				$options[$option_names[$option_key]] = $value;
			}

			$_POST['options'] = serialize($options);
			
			$extras = array();
			$product_extras = post('product_extra_options', array());
			$extra_prices = post('product_extra_option_price', array());
			$extra_names = post('product_extra_option_name', array());
			
			foreach ($product_extras as $option_key=>$value)
			{
				if (!array_key_exists($option_key, $extra_prices))
					throw new Phpr_ApplicationException('Extra option price is not specified');

				if (!array_key_exists($option_key, $extra_names))
					throw new Phpr_ApplicationException('Extra option name is not specified');
					
				$name = $extra_names[$option_key];
				$price = trim($extra_prices[$option_key]);
				if (!strlen($price))
					throw new Phpr_ApplicationException('Please specify price for "'.$name.'" extra option.');

				if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $price))
					throw new Phpr_ApplicationException('Invalid price value for "'.$name.'" extra option.');

				$price_with_tax = Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price) + $price;
				$extras[] = array($price, $name, $price_with_tax);
			}
			
			$_POST['extras'] = serialize($extras);
			$data = $_POST;
			
			$item_data = post('Shop_OrderItem', array());
			foreach ($item_data as $key=>$value)
				$data[$key] = $value;
				
			if ($this->bundle_master_order_item_id || isset($data['bundle_master_order_item_id']))
			{
				if (isset($data['bundle_master_order_item_id']))
					$this->bundle_master_order_item_id = $data['bundle_master_order_item_id'];
				
				$master_item = $this->get_master_bundle_order_item();
				if ($master_item && isset($data['quantity']))
				{
					$quantity = trim($data['quantity']);
					$data['quantity'] = $quantity*$master_item->quantity;
				}
			}

			$this->save($data, $session_key);
		}
		
		public function option_value($name)
		{
			if (!strlen($this->options))
				return null;

			$options = unserialize($this->options);
			if (array_key_exists($name, $options))
				return $options[$name];
				
			return null;
		}
		
		public function convert_to_cart_item()
		{
			$result = new Shop_CartItem();
			
			$extra_options = array();
			$this_extra_options = $this->get_extra_option_objects();
			$key_options = array();
			foreach ($this_extra_options as $extra_option)
			{
				// $item = array('price'=>$extra_option[0], 'description'=>$extra_option[1]);
				// $key_options[$extra_option[1]] = $extra_option[0];
				// $extra_options[] = (object)$item;
				$extra_options[] = $extra_option;
			}
			
			$options = $this->get_options();
			$result->key = Shop_InMemoryCartItem::gen_item_key($this->product->id, $options, $key_options, array(), null);
			$result->product = $this->product;
			$result->options = $options;
			$result->extra_options = $extra_options;
			$result->quantity = $this->quantity;
			$result->price_preset = $this->price;
			$result->order_item = $this;
			
			return $result;
		}

		public function extra_checked_price($name)
		{
			if (!strlen($this->extras))
				return false;

			$extras = unserialize($this->extras);
			foreach ($extras as $extra)
			{
				if ($extra[1] == $name)
					return $extra[0];
			}
			
			return false;
		}
		
		public function find_same_item($items, $session_key = null)
		{
			/*
			 * Do not merge bundle item products
			 */
			if ($this->bundle_master_order_item_id)
				return null;

			foreach ($items as $item)
			{
				if ($item->bundle_master_order_item_id == $this->id)
					return null;
			}

			/*
			 * Compare items content
			 */
			
			$this_files_hash = $this->get_files_hash($session_key);

			foreach ($items as $item)
			{
				if ($item->bundle_master_order_item_id)
					continue;
					
				foreach ($items as $bundle_item)
				{
					if ($bundle_item->bundle_master_order_item_id == $item->id)
						continue 2;
				}
				
				if ($item->id == $this->id)
					continue;

				if ($item->shop_product_id != $this->shop_product_id)
					continue;

				if ($item->price != $this->price)
					continue;

				if ($item->cost != $this->cost)
					continue;

				if ($item->auto_discount_price_eval != $this->auto_discount_price_eval)
					continue;

				if ($item->discount != $this->discount)
					continue;

				if ($item->options != $this->options)
					continue;

				if ($item->extras != $this->extras)
					continue;

				if ($item->get_files_hash($session_key) != $this_files_hash)
					continue;

				foreach ($this->api_added_columns as $column_name)
				{
					$column = is_string($this->$column_name) ? trim($this->$column_name) : $this->$column_name;
					
					if ($column != $item->$column_name)
						continue 2;
				}

				return $item;
			}
			
			return null;
		}
		
		public function get_files_hash($session_key)
		{
			$files = $this->list_related_records_deferred('uploaded_files', $session_key);
			$result = '';
			try
			{
				foreach ($files as $file)
				{
					$result .= $file->name.$file->size.md5_file(PATH_APP.$file->getPath());
				}
			}
			catch (exception $ex) {}
			
			return md5($result);
		}

		public function get_extra_options()
		{
			return strlen($this->extras) ? unserialize($this->extras) : array();
		}
		
		public function get_extra_option_objects()
		{
			$result = array();
			
			$extras = $this->get_extra_options();
			foreach ($extras as $extra_info)
			{
				$extra_key = md5($extra_info[1]);
				$option = Shop_ExtraOption::find_product_extra_option($this->product, $extra_key);
				if ($option)
				{
					$option->price = $extra_info[0];
					$result[] = $option;
				}
			}
			
			return $result;
		}

		public function get_options()
		{
			return strlen($this->options) ? unserialize($this->options) : array();
		}
		
		public function __get($name)
		{
			if ($name == 'extra_options')
				return $this->get_extra_options();

			return parent::__get($name);
		}

		public static function find_by_id($id)
		{
			if (array_key_exists($id, self::$cache))
				return self::$cache[$id];
				
			return self::$cache[$id] = self::create()->where('id=?', $id)->find();
		}

		/*
		 * Returns a list of extra options what were deleted from the item product
		 */
		public function get_deleted_extra_options()
		{
			$extras = $this->get_extra_options();
			$product_extras = $this->product->extra_options;
			
			$result = array();
			foreach ($extras as $extra)
			{
				foreach ($product_extras as $option)
				{
					if ($option->description == $extra[1])
						continue 2;
				}
				
				$result[] = $extra;
			}
			
			return $result;
		}
		
		public function after_update() 
		{
		   if ($this->shop_order_id)
		      Backend::$events->fireEvent('shop:onOrderItemUpdated', $this);
		}
		
		/*
		 * Returns a list of options what were deleted from the item product
		 */
		public function get_deleted_options()
		{
			if (!strlen($this->options))
				return null;

			$options = unserialize($this->options);
			$product_options = $this->product->options;
			
			$result = array();
			foreach ($options as $name=>$value)
			{
				foreach ($product_options as $option)
				{
					if ($option->name == $name)
						continue 2;
				}
				
				$result[$name] = $value;
			}
			
			return $result;
		}
		
		public function output_product_name($output_name = true, $as_plain_text = false, $no_tax_incl = false, $extra_details = false)
		{
			global $phpr_order_no_tax_mode;
			
			if (!$this->product_name)
				return h('<product not found>');

			if (!$as_plain_text)
				$result = $output_name ? '<strong>'.h($this->product_name).'</strong>' : null;
			else
				$result = $output_name ? $this->product_name.'. ' : null;
			
			if ($this->product->grouped_option_desc)
			{
				if ($result)
				{
					if (!$as_plain_text)
						$result .= '<br/>';
					else
						$result .= "\n ";
				}
				
				if (!$as_plain_text)
					$result .= h($this->product->grouped_menu_label).': '.h($this->product->grouped_option_desc);
				else
					$result .= $this->product->grouped_menu_label.': '.$this->product->grouped_option_desc;
			}

			$options = array();
			$options_arr = unserialize($this->options);
			foreach ($options_arr as $name=>$value)
			{
				if (!$as_plain_text)
					$options[] = h($name.': '.$value);
				else
					$options[] = $name.': '.$value;
			}
				
			if ($options)
			{
				if (!$as_plain_text)
					$result .= '<br/>'.implode('; ', $options);
				else
					$result .= ",\n ".implode('; ', $options);
			}

			$display_tax_incl = !(isset($phpr_order_no_tax_mode) && $phpr_order_no_tax_mode) && !$no_tax_incl && Shop_CheckoutData::display_prices_incl_tax($this->parent_order);

			$extras = array();
			$extras_arr = unserialize($this->extras);
			foreach ($extras_arr as $value)
			{
				$option_obj = Shop_ExtraOption::find_product_extra_option($this->product, md5($value[1]));
				$group = $option_obj ? $option_obj->group_name : null;

				$extra_price_with_tax = array_key_exists(2, $value) ? $value[2] : $value[0];
				$extra_price = $display_tax_incl ? $extra_price_with_tax : $value[0];

				if (!$as_plain_text)
					$extras[] = '+ '.($group ? '<strong>'.h($group).'</strong> - ' : '').h($value[1]).': '.format_currency($extra_price);
				else
					$extras[] = '+ '.($group ? $group.' - ' : '').$value[1].': '.format_currency($extra_price);
			}

			if ($extras)
			{
				if (!$as_plain_text)
					$result .= '<br/>'.implode('<br/>', $extras);
				else
					$result .= ",\n ".implode(",\n ", $extras);
			}
			
			if ($extra_details)
			{
				$details_list = Backend::$events->fireEvent('shop:onGetOrderItemDisplayDetails', $this, $as_plain_text);
				foreach ($details_list as $details) 
				{
					if (!strlen($details))
						continue;

					if (!$as_plain_text)
						$result .= '<br/>'.$details;
					else
						$result .= ",\n ".$details;
				}
			}

			return $result;
		}
		
		public function after_delete()
		{
			if ($this->shop_order_id)
				Backend::$events->fireEvent('shop:onOrderItemDeleted', $this);
		}
		
		/*
		 * Bundle 
		 */
		
		/**
		 * Returns quantity of a bundle item product in each bundle
		 * @return integer
		 */
		public function get_bundle_item_quantity()
		{
			if (!$this->bundle_master_order_item_id)
				return $this->quantity;
				
			$master_item = $this->get_master_bundle_order_item();
			if (!$master_item)
				return $this->quantity;
				
			return round($this->quantity/$master_item->quantity);
		}
		
		/**
		 * Returns order item representing a master bundle product for this item.
		 * @return Shop_OrderItem
		 */
		public function get_master_bundle_order_item()
		{
			return self::find_by_id($this->bundle_master_order_item_id);
		}
		
		/**
		 * Returns a list of order items representing bundle items which master product is represented by this item.
		 * @return array
		 */
		public function list_bundle_items()
		{
			$result = array();

			$items = $this->parent_order->items;
			foreach ($items as $item)
			{
				if ($item->bundle_master_order_item_id == $this->id)
					$result[] = $item;
			}
			
			return $result;
		}
		
		/**
		 * Returns unit price of a bundle. The price includes prices of all bundle items.
		 * @return float
		 */
		public function get_bundle_single_price()
		{
			$result = $this->eval_single_price();
			
			$items = $this->list_bundle_items();
			foreach ($items as $item)
				$result += $item->eval_single_price()*$item->get_bundle_item_quantity();
			
			return $result;
		}
		
		/**
		 * Returns total discount of a bundle. The discount includes discounts of all bundle items.
		 * @return float
		 */
		public function get_bundle_discount()
		{
			$result = $this->discount;
			
			$items = $this->list_bundle_items();
			foreach ($items as $item)
				$result += $item->discount*$item->get_bundle_item_quantity();
			
			return $result;
		}
		
		/**
		 * Returns total bundle price.
		 * @return float
		 */
		public function get_bundle_total_price()
		{
			$result = $this->eval_total_price();
			
			$items = $this->list_bundle_items();
			foreach ($items as $item)
				$result += $item->eval_total_price();
			
			return $result;
		}

		/*
		 * Dimensions
		 */
		
		public function total_volume()
		{
			$result = $this->product->volume()*$this->quantity;
			
			$extras = $this->get_extra_option_objects();
			foreach ($extras as $option)
				$result += $option->volume()*$this->quantity;
			
			return $result;
		}
		
		public function total_weight()
		{
			$result = $this->product->weight*$this->quantity;
			
			$extras = $this->get_extra_option_objects();
			foreach ($extras as $option)
				$result += $option->weight*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns the total depth of this cart item.
		 * @return integer
		 */
		public function total_depth()
		{
			$result = $this->product->depth*$this->quantity;
			
			$extras = $this->get_extra_option_objects();
			foreach ($extras as $option)
				$result += $option->depth*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns the total width of this cart item.
		 * @return integer
		 */
		public function total_width()
		{
			$result = $this->product->width*$this->quantity;
			
			$extras = $this->get_extra_option_objects();
			foreach ($extras as $option)
				$result += $option->width*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns the total height of this cart item.
		 * @return integer
		 */
		public function total_height()
		{
			$result = $this->product->height*$this->quantity;
			
			$extras = $this->get_extra_option_objects();
			foreach ($extras as $option)
				$result += $option->height*$this->quantity;
			
			return $result;
		}

		/**
		 * Copies order item information from another object
		 */
		public function copy_from($obj)
		{
			$this->init_columns_info();
			
			$fields = array(
				'shop_product_id',
				'price',
				'quantity',
				'options',
				'extras',
				'extras_price',
				'tax',
				'discount',
				'auto_discount_price_eval',
				'tax_2',
				'tax_name_1',
				'tax_name_2',
				'tax_discount_1',
				'tax_discount_2',
				'discount_tax_included',
				'price_tax_included'				
			);
			
			foreach ($fields as $field)
				$this->$field = $obj->$field;

			foreach ($this->api_added_columns as $field)
				$this->$field = $obj->$field;
			
			$this->eval_custom_columns();
			return $this;
		}
	}

?>