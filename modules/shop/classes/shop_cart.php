<?

	/**
	 * Manages customer cart and takes care about both in-memory and database cart cases
	 */
	class Shop_Cart
	{
		private static $in_memory_cart = null;
		private static $customer_cart = null;
		private static $items_cache = array();
		private static $product_cache = array();
		
		/**
		 * This method is deprecated. Use add_cart_item() instead.
		 */
		public static function add_item($product, $options, $extra_options, $quantity, $cart_name = 'main', $custom_data = array())
		{
			return self::add_cart_item($product, array(
				'options'=>$options,
				'extra_options'=>$extra_options,
				'quantity'=>$quantity,
				'cart_name'=>$cart_name,
				'custom_data'=>$custom_data
			));
		}
		
		public static function add_cart_item($product, $parameters)
		{
			$defaults = array(
				'quantity'=>1,
				'cart_name'=>'main',
				'extra_options'=>array(),
				'options'=>array(),
				'custom_data'=>array(),
				'bundle_data'=>array(),
				'bundle_data_normalized'=>null,
				'uploaded_files'=>null
			);
			
			$parameters = array_merge($defaults, $parameters);
			foreach ($parameters as $name=>$value)
			{
				if (!array_key_exists($name, $defaults))
					unset($parameters[$name]);
			}
			
			extract($parameters);
			
			/*
			 * Normalize bundle data and check whether required bundle items are presented.
			 */

			$bundle_data = isset($bundle_data_normalized) ? $bundle_data_normalized : self::normalize_bundle_data($bundle_data, true, true);

			self::check_requred_bundle_items($product, $bundle_data);

			/*
			 * Check the availablility 
			 */
			
			self::check_product_availability($product, $quantity, $cart_name);
			self::check_bundle_products_availability($bundle_data, $cart_name, $quantity);

			/*
			 * Add item to the cart
			 */
			
			$parameters['product'] = $product;

			$product_parameters = $parameters;
			$product_parameters['master_bundle_data'] = $bundle_data;
			unset($product_parameters['bundle_data']);

			$existing_item = self::get_cart()->find_matching_item(
				$cart_name, 
				$product->id, 
				$options, 
				self::process_extra_options($extra_options), 
				$custom_data, 
				isset($uploaded_files) ? $uploaded_files : $product->list_uploaded_files(), 
				array(), 
				$bundle_data, 
				$quantity
			);

			if (!$existing_item)
				$item = self::add_product($product_parameters);
			else
			{
				self::set_quantity($existing_item->key, $existing_item->quantity + $quantity, $cart_name);
				self::change_postpone_status(array($existing_item->key=>false), $cart_name);
				$item = $existing_item;
			}

			/*
			 * Add bundle items
			 */
			
			if (!$existing_item)
			{
				foreach ($bundle_data as $bundle_item_id=>$item_data)
				{
					foreach ($item_data as $product_data)
					{
						$product_parameters = $product_data;
						$product_parameters['cart_name'] = $cart_name;
						$product_parameters['bundle_data'] = array(
							'bundle_master_cart_key'=>$item->key,
							'bundle_master_item_id'=>$bundle_item_id,
							'bundle_master_item_product_id'=>$product_data['bundle_item_product_id']
						);

						$product_parameters['product'] = Shop_Product::find_by_id($product_data['product_id']);
						$bundle_item = self::add_product($product_parameters);

						self::set_quantity($bundle_item->key, $product_parameters['quantity'], $cart_name);
					}
				}
			}
			
			/*
			 * Reset cart cache
			 */
			
			self::reset_cart_cache($cart_name);

			return $item;
		}
		
		protected static function process_extra_options($extra_options)
		{
			$processed_extras = array();
			
			foreach ($extra_options as $key=>$value)
			{
				if ($value == -1)
					continue;
				
				if ($value == '1')
					$processed_extras[$key] = $value;
				else
					$processed_extras[$value] = 1;
			}
			
			return $processed_extras;
		}
		
		protected static function add_product($parameters)
		{
			extract($parameters);
			
			if (!isset($bundle_data))
				$bundle_data = array();
				
			if (!isset($master_bundle_data))
				$master_bundle_data = array();
			
			$extra_options = self::process_extra_options($extra_options);
			
			$event_listeners_exist = Backend::$events->listeners_exist('shop:onPreProcessProductCustomData', 'shop:onBeforeAddToCart', 'shop:onAfterAddToCart');
			if ($event_listeners_exist)
			{
				self::reset_cart_cache($cart_name);

				$custom_data_updated = Backend::$events->fireEvent('shop:onPreProcessProductCustomData', $cart_name, $product, $quantity, $options, $extra_options, $custom_data, $bundle_data, $master_bundle_data);
				foreach ($custom_data_updated as $custom_data_item) 
				{
					if (!is_array($custom_data_item))
						continue;

					foreach ($custom_data_item as $item_name=>$item_value)
					{
						$custom_data[$item_name] = $item_value;
					}
				}
			}

			if ($event_listeners_exist)
				Backend::$events->fireEvent('shop:onBeforeAddToCart', $cart_name, $product, $quantity, $options, $extra_options, $custom_data, $bundle_data, $master_bundle_data);

			$files = isset($uploaded_files) ? $uploaded_files : $product->list_uploaded_files();

			$item = self::get_cart()->add_item($product, $options, $extra_options, $quantity, $cart_name, $custom_data, $files, $bundle_data, $master_bundle_data);
			
			if ($event_listeners_exist)
				Backend::$events->fireEvent('shop:onAfterAddToCart', $cart_name, $product, $quantity, $options, $extra_options, $item, $custom_data, $bundle_data, $master_bundle_data);
			
			self::set_custom_data($item->key, $custom_data, $cart_name);

			if ($event_listeners_exist)
				self::reset_cart_cache($cart_name);
			
			return $item;
		}
		
		protected static function check_availability($product, $total_quantity)
		{
			if (!$product->allow_pre_order && $product->track_inventory)
			{
				if ($total_quantity > $product->in_stock)
					throw new Cms_Exception('We are sorry, but only '.$product->in_stock.' unit(s) of the "'.$product->name.'" product are available in stock.');
			}
		}
		
		protected static function check_product_availability($product, $quantity, $cart_name)
		{
			if (!$product->allow_pre_order && $product->track_inventory)
			{
				$product_found = false;
				$total_quantity = 0;
				
				$items = self::list_active_items($cart_name);
				foreach ($items as $item)
				{
					if ($item->product && $item->product->id == $product->id)
					{
						$product_found = true;
						$total_quantity += $item->quantity;
					}
				}
				
				if($product_found)
					self::check_availability($product, $total_quantity + $quantity);
				else
					self::check_availability($product, $quantity);
			}
		}
		
		protected static function check_bundle_products_availability($bundle_data, $cart_name, $master_quantity)
		{
			foreach ($bundle_data as $bundle_item_id=>$item_data)
			{
				foreach ($item_data as $product_data)
				{
					self::check_product_availability(Shop_Product::find_by_id($product_data['product_id']), $product_data['quantity']*$master_quantity, $cart_name);
				}
			}
		}
		
		public static function uploaded_files_to_array($files)
		{
			$result = array();
			
			if ($files instanceof Db_DataCollection)
			{
				foreach ($files as $db_file)
				{
					$file_info = array();
					$file_info['name'] = $db_file->name;
					$file_info['size'] = $db_file->size;
//					$file_info['path'] = $db_file->getPath();

					$result[] = $file_info;
				}
			}
			
			return $result;
		}
		
		public static function normalize_bundle_data($bundle_data, $validate_quantity = false, $apply_grouped_products = false)
		{
			$defaults = array(
				'extra_options'=>array(),
				'options'=>array(),
				'custom_data'=>array()
			);

			$result = array();
			
			$product_id = $bundle_item_product_id = null;

			foreach ($bundle_data as $bundle_item_id=>$item_data)
			{
				if (!count($item_data))
					continue;
				
				$data_keys = array_keys($item_data);
				if (is_int($data_keys[0]))
				{
					/*
					 * Multi-product notation with product identifiers in the data keys
					 */
					
					foreach ($item_data as $product_data)
					{
						if (!isset($product_data['product_id']) || !strlen($product_data['product_id']))
							continue;
							
						self::parse_bundle_product_id($product_data['product_id'], $product_id, $bundle_item_product_id);

						$product_data = array_merge($defaults, $product_data);
						if (!array_key_exists($bundle_item_id, $result))
							$result[$bundle_item_id] = array();
							
						$product_data['product_id'] = $product_id;
						$product_data['bundle_item_product_id'] = $bundle_item_product_id;
						
						if ($apply_grouped_products && array_key_exists('grouped_product_id', $product_data))
						{
							$product_data['product_id'] = $product_data['grouped_product_id'];
							unset($product_data['grouped_product_id']);
						}

						$result[$bundle_item_id][] = $product_data;
					}
				} else {
					/*
					 * Single-product notation with product data in the item data
					 */

					if (!isset($item_data['product_id']) || !strlen($item_data['product_id']))
						continue;

					self::parse_bundle_product_id($item_data['product_id'], $product_id, $bundle_item_product_id);
					
					$item_data_processed = array();

					foreach ($item_data as $data_key=>$data_value)
					{
						if (is_array($data_value))
						{
							$keys = array_keys($data_value);
							if (count($keys) && is_int($keys[0]))
							{
								if (array_key_exists($product_id, $data_value))
									$item_data_processed[$data_key] = $data_value[$product_id];
							} else
								$item_data_processed[$data_key] = $data_value;
						} else
							$item_data_processed[$data_key] = $data_value;
					}

					$item_data = array_merge($defaults, $item_data_processed);
					
					if ($apply_grouped_products && isset($item_data['grouped_product_id']))
					{
						if (is_array($item_data['grouped_product_id']))
						{
							if (isset($item_data['grouped_product_id'][$product_id]))
								$product_id = $item_data['grouped_product_id'][$product_id];
						} else
							$product_id = $item_data['grouped_product_id'];
					}
					
					$item_data['product_id'] = $product_id;
					$item_data['bundle_item_product_id'] = $bundle_item_product_id;
					$result[$bundle_item_id][] = $item_data;
				}
			}
			
			if ($validate_quantity)
			{
				foreach ($result as $bundle_item_id=>&$item_products_data)
				{
					foreach ($item_products_data as &$product_data)
					{
						if (array_key_exists('quantity', $product_data))
						{
							$product_data['quantity'] = trim($product_data['quantity']);
							if (!strlen($product_data['quantity']))
							{
								$product = Shop_Product::find_by_id($product_data['product_id']);
								throw new Phpr_ApplicationException(sprintf('Please specify quantity for product "%s"', $product->name));
							}

							if (!preg_match('/^[0-9]+$/', $product_data['quantity']) || $product_data['quantity'] <= 0)
							{
								$product = Shop_Product::find_by_id($product_data['product_id']);
								throw new Cms_Exception(sprintf('Invalid quantity value specified for product "%s"', $product->name));
							}
						}
						else {
							$item_product = Shop_BundleItemProduct::create()->find($product_data['bundle_item_product_id']);
							$product_data['quantity'] = $item_product->default_quantity;
						}
					}
				}
			}

			foreach ($result as $bundle_item_id=>&$item_products_data)
			{
				foreach ($item_products_data as &$product_data)
				{
					$product_data = array(
						'extra_options'=>$product_data['extra_options'],
						'options'=>$product_data['options'],
						'custom_data'=>$product_data['custom_data'],
						'quantity'=>isset($product_data['quantity']) ? (int)$product_data['quantity'] : null,
						'product_id'=>$product_data['product_id'],
						'bundle_item_product_id'=>$product_data['bundle_item_product_id']
					);
				}
			}

			return $result;
		}
		
		protected static function check_requred_bundle_items($product, $bundle_data)
		{
			foreach ($product->bundle_items as $bundle_item)
			{
				if ($bundle_item->is_required && !array_key_exists($bundle_item->id, $bundle_data))
					throw new Phpr_ApplicationException(sprintf('Please select %s.', $bundle_item->name));
			}
		}
		
		protected static function parse_bundle_product_id($product_id_data, &$product_id, &$bundle_item_product_id)
		{
			$parts = explode('|', $product_id_data);
			if (count($parts) != 2)
				throw new Phpr_ApplicationException('Invalid bundle item product specifier');

			$bundle_item_product_id = trim($parts[0]);
			$product_id = trim($parts[1]);
		}
		
		public static function remove_item($key, $cart_name = 'main')
		{
			$item = self::find_item($key, $cart_name, false);
			$bundle_items = $item ? $item->get_bundle_items() : array();

			self::remove_item_internal($key, $cart_name);
			foreach ($bundle_items as $bundle_item)
				self::remove_item_internal($bundle_item->key, $cart_name);
		}
		
		protected static function remove_item_internal($key, $cart_name = 'main')
		{
			self::reset_cart_cache($cart_name);
			Backend::$events->fireEvent('shop:onBeforeRemoveFromCart', $cart_name, $key);
			self::get_cart()->remove_item($key, $cart_name);
			Backend::$events->fireEvent('shop:onAfterRemoveFromCart', $cart_name, $key);
			self::reset_cart_cache($cart_name);
		}
		
		public static function find_by_product_sku($sku, $cart_name = 'main')
		{
			$items = self::list_active_items($cart_name);
			$result = array();
			$sku = mb_strtolower(trim($sku));
			foreach ($items as $item)
			{
				if (mb_strtolower($item->product->sku) == $sku)
					$result[] = $item;
			}
			
			return $result;
		}
		
		protected static function reset_cart_cache($cart_name = 'main')
		{
			if (isset(self::$items_cache[$cart_name]))
				self::$items_cache[$cart_name] = null;
		}

		public static function find_item($key, $cart_name = 'main', $auto_remove_items = true)
		{
			$items = self::list_items($cart_name, $auto_remove_items);

			if (!array_key_exists($key, $items))
				return null;
				
			return $items[$key];
		}
		
		public static function set_quantity($key, $value, $cart_name = 'main')
		{
			$item = self::find_item($key, $cart_name);
			if ($item && $item->product)
			{
				if ($item->is_bundle_item())
				{
					$master_item = $item->get_master_bundle_item();
					if ($master_item)
						$value = $value*$master_item->quantity;
						
					if ($value <= 0)
					{
						$bundle_item = $item->get_bundle_item();
						if ($bundle_item && $bundle_item->is_required)
							throw new Phpr_ApplicationException(
								sprintf('"%s" item is required. You cannot delete it from "%s" product.', $bundle_item->name, $master_item->product->name)
							);
					}
				}
				
				self::check_availability($item->product, $value);
			}

			$bundle_items = array();
			if ($item)
			{
				$bundle_items = $item ? $item->get_bundle_items() : array();
				$bundle_item_quantities = array();
				foreach ($bundle_items as $bundle_item)
				{
					$bundle_item_quantities[$bundle_item->key] = $updated_quantity = $value*$bundle_item->get_quantity();
					
					if ($bundle_item->product)
						self::check_availability($bundle_item->product, $updated_quantity);
				}
			}

			self::set_item_quantity($key, $value, $cart_name, $item);
			foreach ($bundle_items as $bundle_item)
			{
				self::set_item_quantity($bundle_item->key, $bundle_item_quantities[$bundle_item->key], $cart_name, $bundle_item);
			}
		}
		
		protected static function set_item_quantity($key, $value, $cart_name, $item)
		{
			if (!$item || $item->get_quantity() == $value)
				return;

			Backend::$events->fireEvent('shop:onBeforeSetCartQuantity', $cart_name, $key, $value);
			if (self::get_cart()->set_quantity($key, $value, $cart_name))
			{
				if ($item)
					$item->quantity = $value;

				Backend::$events->fireEvent('shop:onAfterSetCartQuantity', $cart_name, $key, $value);
				self::reset_cart_cache($cart_name);
			}
		}
		
		public static function set_custom_data($key, $values, $cart_name)
		{
			foreach ($values as $value_key=>$value)
			{
				if (!preg_match('/^x_/', $value_key))
					throw new Phpr_SystemException('Invalid custom data key: '.$value_key.'. Custom data keys should have the x_ prefix.');
			}

			self::get_cart()->set_custom_data($key, $values, $cart_name);
		}
		
		public static function change_postpone_status($values, $cart_name = 'main')
		{
			if (self::get_cart()->change_postpone_status($values, $cart_name))
			{
				foreach ($values as $key=>$value)
				{
					$item = self::find_item($key, $cart_name);
					if ($item)
					{
						$bundle_items = $item ? $item->get_bundle_items() : array();
						$bundle_item_statuses = array();
						foreach ($bundle_items as $bundle_item)
							$bundle_item_statuses[$bundle_item->key] = $value;
							
						self::get_cart()->change_postpone_status($bundle_item_statuses, $cart_name);
					}
				}
				
				self::reset_cart_cache($cart_name);
			}
		}

		public static function list_postponed_items($cart_name = 'main')
		{
			$result = array();
			$items = self::list_items($cart_name);
			foreach ($items as $item)
			{
				if ($item->postponed)
					$result[] = $item;
			}
			
			return $result;
		}

		public static function list_active_items($cart_name = 'main')
		{
			$result = array();
			$items = self::list_items($cart_name);

			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result[] = $item;
			}
			
			return $result;
		}

		public static function remove_active_items($cart_name = 'main')
		{
			$items = self::list_active_items($cart_name);
			foreach ($items as $item)
				self::remove_item($item->key, $cart_name);
		}
		
		public static function list_items($cart_name = 'main', $auto_remove_items = true)
		{
			if (array_key_exists($cart_name, self::$items_cache) && self::$items_cache[$cart_name] !== null)
				return self::$items_cache[$cart_name];

			$items = self::get_cart()->list_items($cart_name);

			if (!count($items))
				return self::$items_cache[$cart_name] = array();

			/*
			 * Load cart products, options and extras and prepare cache
			 */

			$product_ids = array();
			foreach ($items as $item)
				$product_ids[$item->product_id] = 1;

			$product_ids = array_keys($product_ids);
			
			$product_ids_to_load = array_diff($product_ids, array_keys(self::$product_cache));

			if (count($product_ids_to_load) > 0)
			{
				$products = new Shop_Product(null, array('no_timestamps'=>true, 'no_validation'=>true, 'no_column_init'=>true));
				$products = $products->apply_visibility()->where('id in (?)', array($product_ids_to_load))->find_all();
				
				foreach ($products as $product)
					self::$product_cache[$product->id] = $product;
			}
			
			$options = new Shop_CustomAttribute(null, array('no_timestamps'=>true));
			$options = $options->where('product_id in (?)', array($product_ids))->find_all();

			$extra_options = new Shop_ExtraOption(null, array('no_timestamps'=>true));
			$extra_options->where('(product_id in (?) and (option_in_set is null or option_in_set <> 1))', array($product_ids));
			$extra_options->orWhere('exists(select * from shop_products_extra_sets where extra_option_set_id=shop_extra_options.product_id and extra_product_id in (?))', array($product_ids));

			$extra_options = $extra_options->find_all();
			
			$products = self::$product_cache;

			$option_list = array();
			foreach ($options as $option)
				$option_list[$option->option_key.'|'.$option->product_id] = $option;

			$options = $option_list;

			$extra_option_list = array();
			$global_extra_option_list = array();
			foreach ($extra_options as $extra_option)
			{
				if ($extra_option->option_in_set)
					$global_extra_option_list[$extra_option->option_key] = $extra_option;
				else
					$extra_option_list[$extra_option->option_key.'|'.$extra_option->product_id] = $extra_option;
			}

			$extra_options = $extra_option_list;

			/*
			 * Populate result list, filtering unavailable products
			 */

			$result = array();
			$to_remove = array();
			foreach ($items as $item)	
			{

				$product = array_key_exists($item->product_id, $products) ? $products[$item->product_id] : null;
				if (!$product)
				{
					$to_remove[] = $item;
//					self::remove_item($item->key, $cart_name);
					continue;
				}

				$cart_item = new Shop_CartItem();
				$cart_item->product = $product;
				$cart_item->cart_name = $cart_name;

				foreach ($item->options as $key=>$value)
				{
					$product_option_key = $key.'|'.$product->id;

					if (!array_key_exists($product_option_key, $options))
					{
						$to_remove[] = $item;
//						self::remove_item($item->key, $cart_name);
						continue 2;
					}

					$option = $options[$product_option_key];
					$cart_item->options[$option->name] = $value;
				}

				foreach ($item->extras as $key=>$value)
				{
					$product_option_key = $key.'|'.$product->id;

					if (!array_key_exists($product_option_key, $extra_options) && !array_key_exists($key, $global_extra_option_list))
					{
						$to_remove[] = $item;
//						self::remove_item($item->key, $cart_name);
						continue 2;
					}

					if (array_key_exists($product_option_key, $extra_options))
						$cart_item->extra_options[] = $extra_options[$product_option_key];
					else
						$cart_item->extra_options[] = $global_extra_option_list[$key];
				}

				$cart_item->quantity = $item->quantity;
				$cart_item->key = $item->key;
				$cart_item->native_cart_item = $item;
				$cart_item->postponed = $item->postponed;
				$result[$item->key] = $cart_item;
			}

			/**
			 * Remove disabled items along with bundle items if any
			 */

			$to_remove_updated = array();
			foreach ($to_remove as $item_to_remove)
			{
				if ($item_to_remove->bundle_master_cart_key)
				{
					foreach ($items as $item)
					{
						if ($item_to_remove->bundle_master_cart_key == $item->key)
						{
							$to_remove_updated[] = $item;
							break;
						}
					}
				} else
					$to_remove_updated[] = $item_to_remove;
			}
			
			$to_remove = $to_remove_updated;
			
			foreach ($to_remove as $item_to_remove)
			{
				self::remove_item_internal($item_to_remove->key, $cart_name);
				if (isset($result[$item_to_remove->key]))
					unset($result[$item_to_remove->key]);
				
				foreach ($items as $item)
				{
					if ($item->bundle_master_cart_key == $item_to_remove->key)
					{
						self::remove_item_internal($item->key, $cart_name);
						if (isset($result[$item->key]))
							unset($result[$item->key]);
					}
				}
			}

			return self::$items_cache[$cart_name] = $result;
		}

		/**
		 * Returns string hash identifying the cart content 
		 */
		public static function get_content_id($cart_name = 'main')
		{
			$items = self::list_items($cart_name);

			$str = null;
			foreach ($items as $item)
			{
				$str .= $item->product->id.'_'.$item->quantity.'_'.$item->postponed.'_'.$item->product->price_no_tax();
			}
			$str .= self::total_price_no_tax($cart_name, false);

			return md5($str);
		}

		/**
		 * Returns total price of all items in the cart
		 */
		public static function total_price_no_tax($cart_name = 'main', $apply_cart_discounts = true, $items = null)
		{
			$items = $items === null ? self::list_items($cart_name) : $items;
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_price_no_tax($apply_cart_discounts);
			}

			return $result;
		}
		
		/**
		 * Returns total price of all items in the cart. Adds tax to the result if the "Display catalog/cart prices including tax" option is enabled
		 */
		public static function total_price($cart_name = 'main', $apply_cart_discounts = true, $items = null, $force_tax = false)
		{
			$items = $items === null ? self::list_items($cart_name) : $items;
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_price($apply_cart_discounts, $force_tax);
			}

			return $result;
		}
		
		/**
		 * Returns total tax amount applied to the cart items
		 */
		public static function total_tax($cart_name, $items = null)
		{
			$items = $items === null ? self::list_items($cart_name) : $items;
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_tax();
			}

			return $result;
		}
		
		public static function get_item_total_num($cart_name = 'main', $count_bundle_items = true)
		{
			return self::get_cart()->get_item_total_num($cart_name, $count_bundle_items);
		}
		
		public static function total_volume($cart_name = 'main')
		{
			$items = self::list_items($cart_name);
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_volume();
			}

			return $result;
		}

		public static function total_weight($cart_name = 'main')
		{
			$items = self::list_items($cart_name);
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_weight();
			}

			return $result;
		}
		
		/**
		 * Returns the total depth of all cart items of the cart specified by $cart_name.
		 * @param string $cart_name Name of the cart to use.
		 * @return integer
		 */
		public static function total_depth($cart_name = 'main')
		{
			$items = self::list_items($cart_name);
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_depth();
			}

			return $result;
		}
		
		/**
		 * Returns the total width of all cart items of the cart specified by $cart_name.
		 * @param string $cart_name Name of the cart to use.
		 * @return integer
		 */
		public static function total_width($cart_name = 'main')
		{
			$items = self::list_items($cart_name);
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_width();
			}

			return $result;
		}
		
		/**
		 * Returns the total height of all cart items of the cart specified by $cart_name.
		 * @param string $cart_name Name of the cart to use.
		 * @return integer
		 */
		public static function total_height($cart_name = 'main')
		{
			$items = self::list_items($cart_name);
			$result = 0;
			foreach ($items as $item)
			{
				if (!$item->postponed)
					$result += $item->total_height();
			}

			return $result;
		}

		/**
		 * Moves in-memory cart items to the customer cart
		 */
		public static function move_cart()
		{
			$carts = self::get_in_memory_cart()->list_cart_names();
			foreach ($carts as $cart_name)
			{
				$in_memory_items = self::get_in_memory_cart()->list_items($cart_name);
				$customer_cart = self::get_cart();

				if (self::get_in_memory_cart() == $customer_cart)
					return;

				if ($in_memory_items)
				{
					$cart_behavior = Shop_ConfigurationRecord::get()->cart_login_behavior;

					if ($cart_behavior != 'ignore')
					{
						if ($cart_behavior == 'move_and_sum' || $cart_behavior == 'move_and_max')
						{
							/*
							 * Make all items in the customer cart postponed
							 */
							$customer_items = $customer_cart->list_items($cart_name);
							$postponed_items = array();
							foreach ($customer_items as $key=>$item)
								$postponed_items[$key] = 1;

							self::change_postpone_status($postponed_items, $cart_name);
						}
					
						$customer_items = $customer_cart->list_items($cart_name);
					
						if ($cart_behavior == 'override')
						{
							foreach ($customer_items as $customer_item)
								$customer_cart->remove_item($customer_item->key, $cart_name);
						}

						/*
						 * Move items from in-memory cart to customer cart
						 */
				
						foreach ($in_memory_items as $item)
						{
							if ($item->is_bundle_item())
								continue;
							
							if ($cart_behavior == 'move_and_sum' || $cart_behavior == 'no_move_sum')
							{
								$customer_cart_item = self::add_customer_cart_item($item, $in_memory_items);

								if ($customer_cart_item)
								{
									if ($item->postponed)
										self::change_postpone_status(array($customer_cart_item->item_key=>true), $cart_name);
									else
										self::change_postpone_status(array($customer_cart_item->item_key=>false), $cart_name);
								}
							} elseif ($cart_behavior == 'move_and_max' || $cart_behavior == 'no_move_max' || $cart_behavior == 'override')
							{
								if ($cart_behavior == 'override')
									$customer_cart_item = self::add_customer_cart_item($item, $in_memory_items);
								else
								{
									$existing_cart_item = self::get_cart()->find_matching_item(
										$item->cart_name, 
										$item->product_id, 
										$item->options, 
										$item->extras, 
										$item->custom_data, 
										$item->list_uploaded_files(), 
										array(),  
										$item->get_master_bundle_data($in_memory_items), 
										$item->quantity
									);

									if (!$existing_cart_item)
										$customer_cart_item = self::add_customer_cart_item($item, $in_memory_items);
									else
									{
										$customer_cart_item = $existing_cart_item;
										if (!$customer_cart_item || $customer_cart_item->bundle_master_cart_key)
											continue;

										$quantity = max($customer_cart_item->quantity, $item->quantity);

										self::set_quantity($customer_cart_item->item_key, $quantity, $cart_name);

										if ($item->postponed)
											self::change_postpone_status(array($customer_cart_item->item_key=>true), $cart_name);
										else
											self::change_postpone_status(array($customer_cart_item->item_key=>false), $cart_name);
									}
								}
							}
						}
					}
				}

				self::get_in_memory_cart()->empty_cart($cart_name);
			}
		}
		
		private static function add_customer_cart_item($item, $existing_items)
		{
			$parameters = array(
				'quantity'=>$item->quantity,
				'cart_name'=>$item->cart_name,
				'extra_options'=>$item->extras,
				'options'=>$item->options,
				'custom_data'=>$item->custom_data,
				'bundle_data_normalized'=>$item->get_master_bundle_data($existing_items),
				'uploaded_files'=>$item->list_uploaded_files()
			);

			if ($product = Shop_Product::find_by_id($item->product_id))
				return self::add_cart_item($product, $parameters)->native_cart_item;
				
			return null;
		}

		private static function remap_obj_array($array, $object_key)
		{
			$result = array();
			foreach ($array as $obj)
				$result[$obj->$object_key] = $obj;
				
			return $result;
		}

		/**
		 * Returns the in-memory or database cart
		 */
		private static function get_cart()
		{
			if (!($customer = Phpr::$frontend_security->authorize_user()))
				return self::get_in_memory_cart();
				
			if (self::$customer_cart === null)
				self::$customer_cart = new Shop_CustomerCart($customer);
				
			return self::$customer_cart;
		}

		private static function get_in_memory_cart()
		{
			if (self::$in_memory_cart === null)
				self::$in_memory_cart = new Shop_InMemoryCart();
				
			return self::$in_memory_cart;
		}
	}

?>