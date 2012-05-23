<?php

	/**
	 * The bundle helper class has method which are useful for building the front-end 
	 * bundle user interface on the product page.
	 */
	class Shop_BundleHelper
	{
		protected static $normalized_bundle_product_data = null;
		
		/**
		 * Returns TRUE if a specified bundle item product is selected.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object.
		 * @return boolean
		 */
		public static function is_item_product_selected($bundle_item, $bundle_item_product)
		{
			$result = self::is_item_product_selected_internal($bundle_item, $bundle_item_product);

			if ($result === null)
				return false;

			if ($result)
				return true;

			/*
			 * Return TRUE if the item is default
			 */
			
			foreach ($bundle_item->item_products_all as $item_product)
			{
				if ($item_product->is_default && $item_product->id == $bundle_item_product->id)
					return true;
			}

			/*
			 * Return FALSE if there is a default product for this bundle item but this product is not default
			 */
			
			foreach ($bundle_item->item_products_all as $item_product)
			{
				if ($item_product->is_default)
					return false;
			}
			
			/*
			 * Return TRUE if this item is the first in the list for drop-down and radio button controls
			 */

			if (($bundle_item->control_type == Shop_ProductBundleItem::control_dropdown ||  
				$bundle_item->control_type == Shop_ProductBundleItem::control_radio) && $bundle_item->is_required)
			{
				foreach ($bundle_item->item_products as $index=>$item_product)
				{
					if ($item_product->id == $bundle_item_product->id && $index == 0)
						return true;
				}
			}

			return false;
		}
		
		/**
		 * Returns Quantity field value for a specified bundle item product.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object, optional.
		 * @param int $product_id Product identifier, optional.
		 * @return mixed
		 */
		public static function get_product_quantity($bundle_item, $bundle_item_product = null, $product_id = null)
		{
			$result = self::find_bundle_data_element('quantity', $bundle_item, $bundle_item_product, $product_id);

			if (strlen($result))
				return $result;

			if ($bundle_item_product)
				return $bundle_item_product->default_quantity;
				
			foreach ($bundle_item->item_products_all as $bundle_item_product)
			{
				if ($bundle_item_product->is_default)
					return $bundle_item_product->default_quantity;
			}

			return null;
		}
		
		/**
		 * Returns TRUE if a specified bundle item product option is selected.
		 * @param Shop_CustomAttribute $option The option object being checked.
		 * @param string $value Option value to check.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object, optional.
		 * @return boolean
		 */
		public static function is_product_option_selected($option, $value, $bundle_item, $bundle_item_product)
		{
			$result = self::find_bundle_data_element('options', $bundle_item, $bundle_item_product, null);

			if ($result === false || !is_array($result))
				return false;

			if (!array_key_exists($option->option_key, $result))
				return false;

			return (string)trim($result[$option->option_key]) == (string)trim($value);
		}
		
		/**
		 * Returns TRUE if a specified bundle item product extra option is selected.
		 * @param Shop_ExtraOption $option The option object being checked.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object, optional.
		 * @return boolean
		 */
		public static function is_product_extra_option_selected($option, $bundle_item, $bundle_item_product = null)
		{
			$result = self::find_bundle_data_element('extra_options', $bundle_item, $bundle_item_product, null);
			
			if ($result === false || !is_array($result))
				return false;

			if (!array_key_exists($option->option_key, $result))
				return false;

			return true;
		}
		
		/**
		 * Returns a bundle item product selected by visitor.
		 * By default bundle item products match products specified in the bundle item configuration, 
		 * but this could be not true with grouped products.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object, optional.
		 * @return Shop_Product
		 */
		public static function get_bundle_item_product($bundle_item, $bundle_item_product = null)
		{
			if ($bundle_item->control_type == Shop_ProductBundleItem::control_dropdown)
				return self::get_dropdown_bundle_item_product($bundle_item, $bundle_item_product);
			
			$product_id = self::find_bundle_data_element('grouped_product_id', $bundle_item, $bundle_item_product, null);
			
			if (!$bundle_item_product)
			{
				foreach ($bundle_item->item_products as $item_product)
				{
					if (self::is_item_product_selected($bundle_item, $item_product))
					{
						$bundle_item_product = $item_product;
						break;
					}
				}
			}

			if ($product_id === false)
			{
				if (!$bundle_item_product)
					return null;
				
				$product = $bundle_item_product->product;
				if (!$product->grouped_products->count)
					return $product;
				
				return $product->grouped_products[0];
			}
			
			return Shop_Product::create()->find_by_id($product_id);
		}
		
		/**
		 * Returns a bundle item product object corresponding a product selected by visitor.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @return Shop_BundleItemProduct
		 */
		public static function get_bundle_item_product_item($bundle_item)
		{
			foreach ($bundle_item->item_products as $item_product)
			{
				if (self::is_item_product_selected($bundle_item, $item_product))
					return $item_product;
			}

			return null;
		}

		/**
		 * Returns a name for a bundle item product selector input element (drop-down menu, checkbox or radio button).
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object, optional.
		 * @return string
		 */
		public static function get_product_selector_name($bundle_item, $bundle_item_product)
		{
			switch ($bundle_item->control_type)
			{
				case Shop_ProductBundleItem::control_dropdown : return 'bundle_data['.$bundle_item->id.'][product_id]';
				case Shop_ProductBundleItem::control_checkbox : return 'bundle_data['.$bundle_item->id.']['.$bundle_item_product->product_id.'][product_id]';
				default : return 'bundle_data['.$bundle_item->id.'][product_id]';
			}
		}
		
		/**
		 * Returns a value for a bundle item product selector input element (drop-down menu option, checkbox or radio button).
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object.
		 * @return string
		 */
		public static function get_product_selector_value($bundle_item_product)
		{
			return $bundle_item_product->id.'|'.$bundle_item_product->product_id;
		}
		
		/**
		 * Returns a name for bundle item product configuration control (options, extra options or grouped product selector).
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object.
		 * @param string $control_name Control name. Value should be 'quantity', 'options', 'extra_options' or 'grouped_product'
		 * @return string
		 */
		public static function get_product_control_name($bundle_item, $bundle_item_product, $control_name)
		{
			if (!in_array($control_name, array('quantity', 'options', 'extra_options', 'grouped_product')) )
				throw new Phpr_ApplicationException('Invalid control name passed to Shop_BundleHelper::get_product_control_name(). Valid values are options, extra_options, grouped_product.');
				
			if ($control_name == 'grouped_product')
				$control_name = 'grouped_product_id';
			
			switch ($bundle_item->control_type)
			{
				case Shop_ProductBundleItem::control_dropdown : 
					return 'bundle_data['.$bundle_item->id.']['.$control_name.']';
				case Shop_ProductBundleItem::control_checkbox : 
					return 'bundle_data['.$bundle_item->id.']['.$bundle_item_product->product_id.']['.$control_name.']';
				default : 
					return 'bundle_data['.$bundle_item->id.']['.$control_name.']['.$bundle_item_product->product_id.']';
			}
		}
		
		/**
		 * Returns a string containing hidden fields declarations for a bundle item.
		 * @param Shop_ProductBundleItem $bundle_item Bundle item object.
		 * @param Shop_BundleItemProduct $bundle_item_product Bundle item product object.
		 * @return string
		 */
		public static function get_item_hidden_fields($bundle_item, $bundle_item_product)
		{
			if ($bundle_item->control_type != Shop_ProductBundleItem::control_dropdown)
				return null;
				
			$product_id = $bundle_item_product ? $bundle_item_product->id : null;
				
			return '<input type="hidden" name="bundle_data['.$bundle_item->id.'][post_item_product_id]" value="'.$product_id.'"/>';
		}
		
		protected static function find_bundle_data_element($element_name, $bundle_item, $bundle_item_product, $product_id)
		{
			$data = post('bundle_data', array());

			if (!array_key_exists($bundle_item->id, $data))
				return false;

			if (!$product_id)
			{
				if ($bundle_item_product)
					$product_id = $bundle_item_product->product_id;
			}

			$data = $data[$bundle_item->id];
			if (!count($data))
				return false;

			if (array_key_exists($element_name, $data))
			{
				$element_data = $data[$element_name];
				if (!is_array($element_data))
				{
					if (!array_key_exists('post_item_product_id', $data) || !$bundle_item_product)
						return $element_data;

					if ($data['post_item_product_id'] != $bundle_item_product->id)
						return false;

					return $element_data;
				}

				if (!$product_id)
					return false;

				if (array_key_exists($product_id, $element_data))
					return $element_data[$product_id];
				else
				{
					$data_keys = array_keys($element_data);
					if (!count($data_keys))
						return false;
						
					if (is_int($data_keys[0]))
						return false;
					else
						return $element_data;
					
					return false;
				}
			}

			$data_keys = array_keys($data);
			if (!is_int($data_keys[0]))
				return false;

			if (!$product_id || !array_key_exists($product_id, $data))
				return false;
				
			$data = $data[$product_id];
			if (!array_key_exists($element_name, $data))
				return false;
				
			return $data[$element_name];
		}
		
		protected static function get_normalized_bundle_product_data()
		{
			if (self::$normalized_bundle_product_data !== null)
				return self::$normalized_bundle_product_data;
				
			return self::$normalized_bundle_product_data = Shop_Cart::normalize_bundle_data(post('bundle_data', array()));
		}
		
		protected static function is_item_product_selected_internal($bundle_item, $bundle_item_product)
		{
			$data = post('bundle_data', array());
			if (!array_key_exists($bundle_item->id, $data))
				return false;
				
			$data = $data[$bundle_item->id];
			if (array_key_exists('product_id', $data))
			{
				if (!strlen($data['product_id']))
					return null;

				$product_id = $bundle_item_product_id = null;
				self::parse_bundle_product_id($data['product_id'], $product_id, $bundle_item_product_id);
				
				if ($bundle_item_product_id == $bundle_item_product->id)
				{
					return true;
				}
				
				return null;
			}

			if (!count($data))
				return null;
			
			$keys = array_keys($data);
			if (is_int($keys[0]))
			{
				foreach ($data as $product_id => $product_data)
				{
					if (!array_key_exists('product_id', $product_data))
						continue;
						
					$product_id = $bundle_item_product_id = null;
					self::parse_bundle_product_id($product_data['product_id'], $product_id, $bundle_item_product_id);

					if ($bundle_item_product_id == $bundle_item_product->id)
						return true;
				}
			}
			
			if ($bundle_item->control_type == Shop_ProductBundleItem::control_checkbox && $data)
				return null;

			return false;
		}
		
		protected static function parse_bundle_product_id($product_id_data, &$product_id, &$bundle_item_product_id)
		{
			$parts = explode('|', $product_id_data);
			if (count($parts) < 2)
			{
				$product_id = trim($parts[0]);
				$bundle_item_product_id = null;
			} else
			{
				$bundle_item_product_id = trim($parts[0]);
				$product_id = trim($parts[1]);
			}
		}
		
		protected static function get_dropdown_bundle_item_product($bundle_item, $bundle_item_product)
		{
			$master_product_id = self::find_bundle_data_element('product_id', $bundle_item, $bundle_item_product, null);
			$grouped_product_id = self::find_bundle_data_element('grouped_product_id', $bundle_item, $bundle_item_product, null);

			if (!$bundle_item_product)
			{
				foreach ($bundle_item->item_products as $item_product)
				{
					if (self::is_item_product_selected($bundle_item, $item_product))
					{
						$bundle_item_product = $item_product;
						break;
					}
				}
			}

			if ($master_product_id === false)
			{
				if (!$bundle_item_product)
					return null;

				$product = $bundle_item_product->product;
				if (!$product->grouped_products->count)
					return $product;

				return $product->grouped_products[0];
			}

			$master_product_info = explode('|', $master_product_id);
			if (count($master_product_info) != 2)
				return null;

			$master_product = Shop_Product::create()->find_by_id($master_product_info[1]);
			if ($grouped_product_id === false)
				return $master_product;

			if (!$master_product->grouped_products->count)
				return $master_product;

			foreach ($master_product->grouped_products as $grouped_product)
			{
				if ($grouped_product->id == $grouped_product_id)
					return $grouped_product;
			}

			return $master_product;
		}
	}

?>