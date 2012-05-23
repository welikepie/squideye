<?

	class Shop_CartItem
	{
		public $key;
		public $product;
		public $options = array();
		public $extra_options = array();
		public $quantity = 0;
		public $postponed = false;
		public $cart_name = 'main';
		public $free_shipping = false;

		public $native_cart_item = null;
		
		public $price_preset = false;

		public $applied_discount = 0;
		
		/**
		 * This field used in the manual discount management on the Edit Order page
		 */
		public $ignore_product_discount = false;

		public $order_item = null;
		
		/**
		 * Returns product options in format: Color: white, Size: M.
		 */
		public function options_str()
		{
			$result = array();
			
			if ($this->product->grouped_option_desc)
				$result[] = $this->product->grouped_menu_label.': '.$this->product->grouped_option_desc;
			
			foreach ($this->options as $name=>$value)
				$result[] = $name.': '.$value;
				
			return implode('; ', $result);
		}
		
		protected function get_effective_quantity()
		{
			$effective_quantity = $this->quantity;
			
			$controller = Cms_Controller::get_instance();
			if ($controller && $controller->customer && $this->product->tier_prices_per_customer)
				$effective_quantity += $controller->customer->get_purchased_item_quantity($this->product);
				
			return $effective_quantity;
		}

		/**
		 * Evaluates price of a single product unit, taking into account extra paid options
		 */
		public function single_price_no_tax($include_extras = true, $effective_quantity = null)
		{
			if ($this->price_preset === false)
			{
				$external_price = Backend::$events->fireEvent('shop:onGetCartItemPrice', $this);
				$external_price_found = false;
				foreach ($external_price as $price) 
				{
					if (strlen($price))
					{
						$result = $price;
						$external_price_found = true;
						break;
					}
				}

				if (!$external_price_found)
				{
					$bundle_item_product = $this->get_bundle_item_product();
					if ($bundle_item_product)
						$result = $bundle_item_product->get_price_no_tax($this->product);
					else 
					{
						$effective_quantity = $effective_quantity ? $effective_quantity : $this->get_effective_quantity();
						$result = $this->product->price_no_tax($effective_quantity);
					}
				}
				
				$updated_price = Backend::$events->fireEvent('shop:onUpdateCartItemPrice', $this, $result);
				foreach ($updated_price as $price) 
				{
					if (strlen($price))
					{
						$result = $price;
						break;
					}
				}
			}
			else
				$result = $this->price_preset;

			if ($include_extras)
			{
				foreach ($this->extra_options as $option)
					$result += $option->get_price_no_tax($this->product);
			}

			return $result;
		}
		
		public function total_single_price()
		{
			$discount = $this->price_preset === false ? $this->discount(false) : 0;
			return $this->single_price_no_tax(true) - $discount;
		}
		
		/**
		 * Evaluates price of a single product unit, taking into account extra paid options. 
		 * Adds tax amount to the result if the "Display catalog/cart prices including tax" option is enabled
		 */
		public function single_price($include_extras = true)
		{
			$price = $this->single_price_no_tax($include_extras) - $this->discount(false);

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;
			
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns price of a single item of the bundle product cart item. If the cart item does not represent
		 * a bundle product, the method returns the single_price() method result.
		 */
		public function bundle_single_price()
		{
			if (!$this->native_cart_item)
				return $this->single_price();
				
			$bundle_items = $this->get_bundle_items();
			
			$result = $this->single_price();
			foreach ($bundle_items as $item)
				$result += $item->single_price()*$item->get_quantity();
				
			return $result;
		}
		
		/**
		 * Returns cart items which represent bundle items for this cart item.
		 */
		public function get_bundle_items()
		{
			$items = Shop_Cart::list_items($this->cart_name);
			$bundle_items = array();
			foreach ($items as $item)
			{
				if ($item->key == $this->key)
					continue;
					
				if (!$item->native_cart_item)
					continue;
					
				if ($item->native_cart_item->bundle_master_cart_key == $this->key)
					$bundle_items[] = $item;
			}
			
			return $bundle_items;
		}
		
		public function get_extras_cost()
		{
			$result = 0;
			
			foreach ($this->extra_options as $option)
				$result += $option->get_price_no_tax($this->product);
			
			return $result;
		}
		
		/**
		 * Returns item quantity for displaying on pages. 
		 * For bundle items visible quantity and actual quantity can be different. For example, if there was a computer 
		 * bundle product in the cart and its quantity was 2 and it had a bundle item CPU with qty = 2, the actual quantity
		 * for CPU would be 4, while the visible quantity would be 2.
		 */
		public function get_quantity()
		{
			$master_item = $this->get_master_bundle_item();
			if (!$master_item)
				return $this->quantity;
				
			return round($this->quantity/$master_item->quantity);
		}

		/**
		 * Evaluates the item discount, based on the catalog price rules
		 */
		public function discount($total_discount = true)
		{
			if ($total_discount)
				return $this->total_discount_no_tax();

			$effective_quantity = $this->get_effective_quantity();
			if (!$this->price_is_overridden($effective_quantity))
			{
				return round($this->product->get_discount($effective_quantity), 2);
			}

			return 0;
		}

		/**
		 * Evaluates total volume of the item
		 */
		public function total_volume()
		{
			$result = $this->product->volume()*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->volume()*$this->quantity;
			
			return $result;
		}
		
		public function total_weight()
		{
			$result = $this->product->weight*$this->quantity;
			
			foreach ($this->extra_options as $option)
				$result += $option->weight*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Returns TRUE of the item price has been overridden by a custom module
		 */
		public function price_is_overridden($effective_quantity)
		{
			if ($this->price_preset === false)
			{
				$external_price = Backend::$events->fireEvent('shop:onGetCartItemPrice', $this);
				foreach ($external_price as $price) 
				{
					if (strlen($price))
						return true;
				}

				$effective_quantity = $effective_quantity ? $effective_quantity : $this->get_effective_quantity();
				$result = $this->product->price_no_tax($effective_quantity);
				
				$updated_price = Backend::$events->fireEvent('shop:onUpdateCartItemPrice', $this, $result);
				foreach ($updated_price as $price) 
				{
					if (strlen($price))
						return true;
				}
			}

			return false;
		}
		
		/**
		 * Returns the total depth of this cart item.
		 * @return integer
		 */
		public function total_depth()
		{
			$result = $this->product->depth*$this->quantity;
			
			foreach ($this->extra_options as $option)
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
			
			foreach ($this->extra_options as $option)
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
			
			foreach ($this->extra_options as $option)
				$result += $option->height*$this->quantity;
			
			return $result;
		}
		
		/**
		 * Evaluates total price of the item
		 */
		public function total_price_no_tax($apply_cart_level_discount = true, $quantity = null)
		{
			$cart_level_discount = $apply_cart_level_discount ? $this->applied_discount : 0;
			$catalog_level_discount = ($this->price_preset === false) ? $this->discount(false) : 0;
			
			$quantity = $quantity === null ? $this->quantity : $quantity;
			
			return ($this->single_price_no_tax() - $catalog_level_discount - $cart_level_discount)*$quantity;
		}
		
		/**
		 * Returns total price of the item. Adds tax to the result if the "Display catalog/cart prices including tax" option is enabled 
		 * or of the second parameter is TRUE
		 */
		public function total_price($apply_cart_level_discount = true, $force_tax = false, $quantity = null)
		{
			$price = $this->total_price_no_tax($apply_cart_level_discount, $quantity);
			
			if (!$force_tax)
			{
				$include_tax = Shop_CheckoutData::display_prices_incl_tax();
				if (!$include_tax)
					return $price;
			}
			
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns total price of a bundle cart item. If the cart item does not represent
		 * a bundle product, the method returns the total_price() method result.
		 */
		public function bundle_total_price()
		{
			if (!$this->native_cart_item)
				return $this->total_price();
				
			$bundle_items = $this->get_bundle_items();
			
			$result = $this->total_price();
			foreach ($bundle_items as $item)
				$result += $item->total_price();
				
			return $result;
		}
		
		/**
		 * Returns total price of a bundle item cart item. If the cart item does not represent
		 * a bundle item product, the method returns the total_price() method result.
		 */
		public function bundle_item_total_price()
		{
			if (!$this->is_bundle_item())
				return $this->total_price();
				
			return $this->total_price(true, false, $this->get_quantity());
		}
		
		/**
		 * Returns the total value of a tax applied to the item.
		 * @return float
		 */
		public function total_tax()
		{
			$price = $this->total_price_no_tax(true);
			return Shop_TaxClass::get_total_tax($this->product->tax_class_id, $price);
		}
		
		/**
		 * Returns a list of taxes applied to the cart item. Returns an array, containing objects with the following fields: name, rate, total. 
		 * @return array
		 */
		public function get_tax_rates()
		{
			return Shop_TaxClass::get_tax_rates_static($this->product->tax_class_id, Shop_CheckoutData::get_shipping_info());
		}
		
		/**
		 * Returns the total value of a tax applied to a bundle cart item. If the cart item does not represent a bundle product returns
		 * the total_tax() method call result;
		 * @return float
		 */
		public function bundle_total_tax()
		{
			if (!$this->native_cart_item)
				return $this->total_tax();

			$bundle_items = $this->get_bundle_items();
			$result = $this->total_tax();
			foreach ($bundle_items as $item)
				$result += $item->total_tax();
				
			return $result;
		}
		
		public function total_discount_no_tax()
		{
			return $this->applied_discount;
		}

		/**
		 * Returns total item discount value. Adds tax to the result if the "Display catalog/cart prices including tax" option is enabled
		 */
		public function total_discount()
		{
			$product_discount = 0;
			
			$applied_discount = $this->applied_discount;
			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if ($include_tax)
				$applied_discount = Shop_TaxClass::get_total_tax($this->product->tax_class_id, $applied_discount) + $applied_discount;
			
			return $product_discount + $applied_discount;
		}
		
		/**
		 * Returns total discount of a single item of the bundle product cart item. If the cart item does not represent
		 * a bundle product, the method returns the total_discount() method result.
		 */
		public function bundle_total_discount()
		{
			if (!$this->native_cart_item)
				return $this->total_discount();
				
			$bundle_items = $this->get_bundle_items();
			
			$result = $this->total_discount();
			foreach ($bundle_items as $item)
				$result += $item->total_discount()*$item->get_quantity();
				
			return $result;
		}
		
		/**
		 * Return a custom data field value. Custom field names should begin with the 'x_' prefix
		 * @param string $field_name Specifies a field name
		 * @param mixed $default Specifies a default field value
		 */
		public function get_data_field($field_name, $default_value = null)
		{
			if (!$this->native_cart_item)
				return $default_value;
			
			return $this->native_cart_item->get_data_field($field_name, $default_value);
		}

		/**
		 * Returns all custom data fields assigned with the cart item
		 */
		public function get_data_fields()
		{
			if (!$this->native_cart_item)
				return array();
			
			return $this->native_cart_item->get_data_fields();
		}

		/**
		 * Returns a list of files uploaded by the customer on the product page
		 */
		public function list_uploaded_files()
		{
			if (!$this->native_cart_item)
				return array();
			
			return $this->native_cart_item->list_uploaded_files();
		}
		
		public function copy_files_to_order_item($order_item)
		{
			$files = $this->list_uploaded_files();
			foreach ($files as $file_info)
			{
				$file = new Db_File();
				$file->fromFile(PATH_APP.$file_info['path']);
				$file->name = $file_info['name'];
				$file->is_public = false;
				$file->master_object_class = get_class($order_item);
				$file->field = 'uploaded_files';
				$file->save();

				$order_item->uploaded_files->add($file);
			}
		}
		
		/**
		 * Returns TRUE if this cart item represents a bundle item.
		 */
		public function is_bundle_item()
		{
			if ($this->order_item && $this->order_item->bundle_master_order_item_id)
				return true;
			
			$item = $this->get_bundle_item();
			return $item ? true : false;
		}
		
		/**
		 * Returns a bundle item object (Shop_ProductBundleItem) this cart item refers to.
		 * If this cart item does not represent a bundle item product, returns null.
		 */
		public function get_bundle_item()
		{
			if (!$this->native_cart_item)
				return null;

			return $this->native_cart_item->get_bundle_item();
		}
		
		/**
		 * Returns a bundle item product object (Shop_BundleItemProduct) this cart item refers to.
		 * If this cart item does not represent a bundle item product, returns null.
		 */
		public function get_bundle_item_product()
		{
			if (!$this->native_cart_item)
				return null;

			return $this->native_cart_item->get_bundle_item_product();
		}
		
		/**
		 * Returns a cart item object representing a master bundle product for this item.
		 * @return Shop_CartItem Returns the cart item object or NULL if this item is not a bundle item or 
		 * if the master cart item cannot be found.
		 */
		public function get_master_bundle_item()
		{
			if (!$this->native_cart_item)
				return null;

			$key = $this->native_cart_item->bundle_master_cart_key;
			if (!$key)
				return null;

			return Shop_Cart::find_item($key, $this->cart_name);
		}
	}

?>