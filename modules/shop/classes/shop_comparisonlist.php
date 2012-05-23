<?

	class Shop_ComparisonList
	{
		public static function add_product($product_id)
		{
			$product_id = trim($product_id);

			if (!preg_match('/^[0-9]+$/', $product_id))
				return;

			$items = self::list_product_ids();
			$items[$product_id] = 1;

			self::set_product_ids($items);
		}
		
		public static function list_products()
		{
			$items = self::list_product_ids();
			$items = array_keys($items);
			
			if (!$items)
				return new Db_DataCollection(array());

			$products = Shop_Product::create()->where('shop_products.id in (?)', array($items))->find_all()->as_array(null, 'id');
			$result = array();
			foreach ($items as $item_id)
			{
				if (array_key_exists($item_id, $products))
					$result[] = $products[$item_id];
			}

			return new Db_DataCollection($result);
		}

		public static function clear()
		{
			self::set_product_ids(array());
		}

		public static function remove_product($product_id)
		{
			$product_id = trim($product_id);

			$items = self::list_product_ids();
			if (isset($items[$product_id]))
				unset($items[$product_id]);

			self::set_product_ids($items);
		}
		
		protected static function list_product_ids()
		{
			return Phpr::$session->get('comparison_list_items', array());
		}
		
		protected static function set_product_ids($ids)
		{
			Phpr::$session->set('comparison_list_items', $ids);
		}
	}

?>