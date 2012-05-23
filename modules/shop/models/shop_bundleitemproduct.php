<?php

	class Shop_BundleItemProduct extends Db_ActiveRecord
	{
		public $table_name = 'shop_bundle_item_products';
		public $implement = 'Db_Sortable';
		
		const price_override_default = 'default';
		const price_override_fixed = 'fixed';
		const price_override_fixed_discount = 'fixed-discount';
		const price_override_percentage_discount = 'percentage-discount';
		
		protected static $cache = array();

		public static $price_override_options = array(
			'default'=>'Use default price',
			'fixed'=>'Fixed price',
			'fixed-discount'=>'Fixed discount',
			'percentage-discount'=>'Percentage discount'
		);
		
		public $belongs_to = array(
			'bundle_item'=>array('class_name'=>'Shop_ProductBundleItem', 'foreign_key'=>'item_id'),
			'product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'product_id'),
		);

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			if (!$front_end)
			{
				$this->define_relation_column('product_name', 'product', 'Product ', db_varchar, '@name');
				$this->define_relation_column('product_sku', 'product', 'Product ', db_varchar, '@sku');
				$this->define_column('price_or_discount', 'Price or discount')->validation()->fn('trim')->method('validate_price_or_discount');
				$this->define_column('default_quantity', 'Default quantity')->validation()->fn('trim')->required('Please specify default quantity.');
			}
		}

		public function define_form_fields($context = null)
		{
			
		}
		
		public function get_price_ovirride_mode_name($value)
		{
			if (isset(self::$price_override_options[$value]))
				return self::$price_override_options[$value];
				
			return null;
		}
		
		public function validate_price_or_discount($name, $value)
		{
			if (!strlen($value))
			{
				if ($this->price_override_mode == self::price_override_default)
					return true;

				if ($this->price_override_mode == self::price_override_fixed_discount || $this->price_override_mode == self::price_override_percentage_discount)
					$this->validation->setError('Please specify product discount in the Price or Discount field', $name, true);

				$this->validation->setError('Please specify product price in the Price or Discount field', $name, true);
			}
			
			return true;
		}
		
		/**
		 * Returns the bundle item product price.
		 * Takes into account the price mode settings applied to the product.
		 * Applies the tax if it is required by the configuration.
		 * @param Shop_Product Currently selected product object. If no object provided, the product assigned to the item will be used.
		 */
		public function get_price($product = null)
		{
			$product = $product ? $product : $this->product;
			$price = $this->get_price_no_tax($product);
		
			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($product->tax_class_id, $price) + $price;
		}
		
		/**
		 * Returns the bundle item product price without any taxes applied.
		 * @param Shop_Product Currently selected product object. If no object provided, the product assigned to the item will be used.
		 */
		public function get_price_no_tax($product = null, $quantity = 1, $customer_group_id = null)
		{
			$product = $product ? $product : $this->product;

			$price = $product->price_no_tax($quantity, $customer_group_id);
			if ($this->price_override_mode == self::price_override_default)
				return $price;

			if ($this->price_override_mode == self::price_override_fixed)
				return $this->price_or_discount;
			
			if ($this->price_override_mode == self::price_override_fixed_discount)
				return max(0, $price - $this->price_or_discount);

			return $price - $price*$this->price_or_discount/100;
		}
		
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}
	}

?>