<? 

	class Shop_ProductExport
	{
		protected static $manufacturer_names = array();
		protected static $tax_class_names = array();
		protected static $related_map = null;
		protected static $extra_sets_map = null;
		protected static $category_parent_cache = null;
		protected static $customer_groups = null;
		protected static $product_types = array();
		
		public static function export_csv($iwork = false)
		{
			$filename = 'products.csv';
			
			set_time_limit(3600);
			
			header("Expires: 0");
			header("Content-Type: Content-type: text/csv");
			header("Content-Description: File Transfer");
			header("Cache-control: private");
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: pre-check=0, post-check=0, max-age=0');
			header("Content-disposition: attachment; filename=$filename");

			$columns = Shop_Product::create()->get_csv_import_columns(false);
			
			if (isset($columns['images']))
				unset($columns['images']);
			
			$header = array();
			foreach ($columns as $column)
				$header[] = strlen($column->listTitle) ? $column->listTitle : $column->displayName;

			$separator = $iwork ? ',' : ';';

			Phpr_Files::outputCsvRow($header, $separator);
			
			$products = new Shop_Product(null, array('no_column_init'=>true, 'no_validation'=>true));
			$query = $products->build_sql();

//			$products = $products->find_all()->as_array(null, 'id');
			$list_data = Db_DbHelper::queryArray($query);
			
			global $activerecord_no_columns_info;
			$activerecord_no_columns_info = true;
			
			$sku_index = array();
			foreach ($list_data as $row_data)
				$sku_index[$row_data['id']] = $row_data['sku'];

			foreach ($list_data as $row_data)
			{
				$product = $products;
				$product->fill_external($row_data);
				
				$row = array();
				
				foreach ($columns as $column)
				{
					if ($column->dbName == 'categories')
						$row[$column->dbName] = self::list_categories($product);
					elseif ($column->dbName == 'manufacturer_link')
						$row[$column->dbName] = self::get_manufacturer($product);
					elseif ($column->dbName == 'tax_class')
						$row[$column->dbName] = self::get_tax_class($product);
					elseif ($column->dbName == 'csv_import_parent_sku')
						$row[$column->dbName] = self::get_parent_sku($product, $sku_index);
					elseif ($column->dbName == 'options')
						$row[$column->dbName] = self::get_options($product);
					elseif ($column->dbName == 'product_extra_options')
						$row[$column->dbName] = self::get_extra_options($product);
					elseif ($column->dbName == 'perproduct_shipping_cost')
						$row[$column->dbName] = self::get_perproduct_shipping_cost($product);
					elseif ($column->dbName == 'csv_related_sku')
						$row[$column->dbName] = self::get_related_skus($product);
					elseif ($column->dbName == 'extra_option_sets')
						$row[$column->dbName] = self::get_global_extra_sets($product);
					elseif (preg_match('/^ATTR:/', $column->dbName))
						$row[$column->dbName] = self::get_attribute_value($product, $column->dbName);
					elseif ($column->dbName == 'product_groups')
						$row[$column->dbName] = self::list_product_groups($product);
					else if ($column->dbName == 'price_tiers')
						$row[$column->dbName] = self::get_price_tiers($product);
					elseif ($column->dbName == 'product_type')
						$row[$column->dbName] = self::get_product_type($product);
					else
					{
						$db_name = $column->dbName;
						$value = $product->$db_name;
						if (is_object($value))
						{
							if ($value instanceof Phpr_DateTime)
								$value = $value->toSqlDate();
							else
								$value = null;
						}
						
						$row[$column->dbName] = $value;
					}
				}

				Phpr_Files::outputCsvRow($row, $separator);
			}
		}

		protected static function get_parent_sku($product, &$sku_index)
		{
			if (!$product->grouped || !$product->product_id)
				return null;

			if (!array_key_exists($product->product_id, $sku_index))
				return null;

			return $sku_index[$product->product_id];
		}

		protected static function get_related_skus($product)
		{
			if (self::$related_map === null)
			{
				self::$related_map = array();
				$related_products = Db_DbHelper::objectArray('select
					shop_related_products.master_product_id, 
					shop_products.sku 
					from shop_related_products, shop_products 
					where shop_products.id = related_product_id');

				foreach ($related_products as $relation)
				{
					if (!array_key_exists($relation->master_product_id, self::$related_map))
						self::$related_map[$relation->master_product_id] = array();

					self::$related_map[$relation->master_product_id][] = $relation->sku;
				}
			}
			
			if (!array_key_exists($product->id, self::$related_map))
				return null;
				
			return implode('|', self::$related_map[$product->id]);
		}
		
		protected static function get_global_extra_sets($product)
		{
			if (self::$extra_sets_map === null)
			{
				self::$extra_sets_map = array();
				$extra_set_links = Db_DbHelper::objectArray('select code, shop_products_extra_sets.extra_product_id as product_id
					from shop_extra_option_sets, shop_products_extra_sets 
					where shop_products_extra_sets.extra_option_set_id= shop_extra_option_sets.id');

				foreach ($extra_set_links as $link)
				{
					if (!strlen(trim($link->code)))
						continue;

					if (!array_key_exists($link->product_id, self::$extra_sets_map))
						self::$extra_sets_map[$link->product_id] = array();

					self::$extra_sets_map[$link->product_id][] = $link->code;
				}
			}
			
			if (!array_key_exists($product->id, self::$extra_sets_map))
				return null;
				
			return implode('|', self::$extra_sets_map[$product->id]);
		}

		protected static function list_categories($product)
		{
			$product_id = $product->grouped ? $product->product_id : $product->id;
			if (!strlen($product_id))
				return null;
				
			if (self::$category_parent_cache === null)
			{
				self::$category_parent_cache = array();
				$existing_categories = Db_DbHelper::objectArray('select id, name, category_id from shop_categories');

				foreach ($existing_categories as $category)
					self::$category_parent_cache[$category->id] = $category;
			}
			
			$product_categories = Db_DbHelper::objectArray("select shop_categories.id, shop_categories.name, shop_categories.category_id 
				from shop_categories, shop_products_categories 
				where shop_product_id = :product_id
				and shop_categories.id=shop_category_id",
				array('product_id'=>$product_id)
			);
			
			$result = array();
			foreach ($product_categories as $category)
			{
				$parents = array($category);
				self::find_category_parents($category, $parents);
				$parents = array_reverse($parents);
			
				$category_path = array();
				foreach ($parents as $parent)
					$category_path[] = $parent->name;
				
				$result[] = implode('=>', $category_path);
			}
			
			return implode('|', $result);
		}
		
		protected static function find_category_parents($category, &$parents)
		{
			$parent_key = $category->category_id ? $category->category_id : -1;
			
			if (array_key_exists($parent_key, self::$category_parent_cache))
			{
				$parent = self::$category_parent_cache[$parent_key];
				$parents[] = $parent;
				self::find_category_parents($parent, $parents);
			}
		}
		
		protected static function get_options($product)
		{
			$options = Db_DbHelper::objectArray('select * from shop_custom_attributes where product_id=:product_id',
				array('product_id'=>$product->id)
			);
			
			$result = array();
			foreach ($options as $option)
			{
				$values = str_replace("\n", "|", $option->attribute_values);
				$option_str = $option->name.': '.$values;
				$result[] = $option_str;
			}
			
			return implode("\n", $result);
		}
		
		protected static function get_extra_options($product)
		{
			$options = Db_DbHelper::objectArray('select * from shop_extra_options where product_id=:product_id and (option_in_set is null or option_in_set=0) order by extra_option_sort_order',
				array('product_id'=>$product->id)
			);
			
			$result = array();
			foreach ($options as $option)
			{
				$description = str_replace("\n", '\n', $option->description);

				$images = Db_DbHelper::scalarArray('select name from db_files where master_object_class=:master_object_class and master_object_id=:master_object_id and field=:field order by sort_order', array(
					'master_object_class'=>'Shop_ExtraOption',
					'master_object_id'=>$option->id,
					'field'=>'images'
				));
				
				$images_str = implode(',', $images);

				$result[] = $description.'|'.$option->price.'|'.$option->group_name.'|'.$images_str;
			}
			
			return implode("\n", $result);
		}
		
		protected static function get_perproduct_shipping_cost($product)
		{
			$shipping_cost = $product->perproduct_shipping_cost;
			if (!is_array($shipping_cost) || !count($shipping_cost))
				return 0;
				
			$result = array();
			foreach ($shipping_cost as $row)
			{
				if (
					!array_key_exists('country', $row) ||
					!array_key_exists('state', $row) ||
					!array_key_exists('zip', $row) ||
					!array_key_exists('cost', $row)
				)
					continue;
					
				$country = strlen($row['country']) ? $row['country'] : '*';
				$state = strlen($row['state']) ? $row['state'] : '*';
				$zip = strlen($row['zip']) ? $row['zip'] : '*';
				$cost = strlen($row['cost']) ? $row['cost'] : 0;
				
				$result[] = $country.'|'.$state.'|'.$zip.'|'.$cost;
			}
			
			if (!count($result))
				$result[] = '*|*|*|0';
			
			return implode("\n", $result);
		}

		protected static function get_price_tiers($product)
		{
			if(!self::$customer_groups)
			{
				self::$customer_groups = array();
				$groups = Db_DbHelper::objectArray('select id, name from shop_customer_groups');
				if(count($groups))
				{
					foreach($groups as $group)
						self::$customer_groups[$group->id] = $group->name;
				}
			}
			$price_tiers = unserialize($product->tier_price_compiled);

			if (!is_array($price_tiers) || !count($price_tiers))
				return null;

			$result = array();
			foreach($price_tiers as $tier)
			{
				if($tier->customer_group_id!='')
					$group_name = self::$customer_groups[$tier->customer_group_id];
				else $group_name = '*';

				$result[] = $group_name.'|'.$tier->quantity.'|'.$tier->price;
			}
			return implode("\n", $result);
		}

		protected static function get_manufacturer($product)
		{
			if (!$product->grouped)
				$manufacturer_id = $product->manufacturer_id;
			else
			{
				if (!strlen($product->product_id))
					return null;

				$manufacturer_id = Db_DbHelper::scalar('select manufacturer_id from shop_products where id=:id', array('id'=>$product->product_id));
			}

			if (!strlen($manufacturer_id))
				return null;
			
			if (array_key_exists($manufacturer_id, self::$manufacturer_names))
				return self::$manufacturer_names[$manufacturer_id];
			
			return self::$manufacturer_names[$manufacturer_id] = Db_DbHelper::scalar('select name from shop_manufacturers where id=:id', array('id'=>$manufacturer_id));
		}
		
		protected static function get_product_type($product)
		{
			$product_type_id = Db_DbHelper::scalar('select product_type_id from shop_products where id=:id', array('id'=>$product->product_id));

			if (!strlen($product_type_id))
				return null;
			
			if (array_key_exists($product_type_id, self::$product_types))
				return self::$product_types[$product_type_id];
			
			return self::$product_types[$product_type_id] = Db_DbHelper::scalar('select name from shop_product_types where id=:id', array('id'=>$product_type_id));
		}
		
		protected static function get_attribute_value($product, $db_name)
		{
			$attr_name = mb_substr($db_name, 6);
			return Db_DbHelper::scalar('select value from shop_product_properties where product_id=:product_id and name=:name', array('product_id'=>$product->id, 'name'=>$attr_name));
		}
		
		protected static function get_tax_class($product)
		{
			if (array_key_exists($product->tax_class_id, self::$tax_class_names))
				return self::$tax_class_names[$product->tax_class_id];
				
			return self::$tax_class_names[$product->tax_class_id] = Db_DbHelper::scalar('select name from shop_tax_classes where id=:id', array('id'=>$product->tax_class_id));
		}
		
		protected static function list_product_groups($product)
		{
			$product_id = $product->grouped ? $product->product_id : $product->id;
			if (!strlen($product_id))
				return null;
				
			$product_groups = Db_DbHelper::objectArray("select scg.name from shop_custom_group scg
				inner join shop_products_customgroups spcg on (scg.id = spcg.shop_custom_group_id)
				where spcg.shop_product_id = :product_id",
				array('product_id'=>$product_id)
			);
			if(count($product_groups))
			{
				foreach($product_groups as $product_group)
				{
					$groups[] = $product_group->name;
				}
				return implode('|', $groups);
			}				
			else return null;
		}
	}

?>