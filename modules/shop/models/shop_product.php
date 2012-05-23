<?php

	class Shop_Product extends Db_ActiveRecord
	{
		public $table_name = 'shop_products';

		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;

		public $enabled = true;
		public $visibility_search = true;
		public $visibility_catalog = true;
		public $perproduct_shipping_cost_use_parent = true;

		protected $api_added_columns = array();

		public static $price_sort_query = '(ifnull((select price from shop_product_price_index where pi_product_id=shop_products.id and pi_group_id=\'%s\'), shop_products.price)) ';
		public static $allowed_sorting_columns = array('name', 'title', 'price', 'sku', 'weight', 'width', 'height', 'depth', 'rand()', 'created_at', 'manufacturer', 'expected_availability_date');

		protected static $cache = array();
		protected $category_cache = null;

		public $belongs_to = array(
			'page'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'page_id'),
			'master_grouped_product'=>array('class_name'=>'Shop_Product', 'foreign_key'=>'product_id'),
			'tax_class'=>array('class_name'=>'Shop_TaxClass', 'foreign_key'=>'tax_class_id'),
			'product_type'=>array('class_name'=>'Shop_ProductType', 'foreign_key'=>'product_type_id'),
			'manufacturer_link'=>array('class_name'=>'Shop_Manufacturer', 'foreign_key'=>'manufacturer_id')
		);

		public $has_and_belongs_to_many = array(
			'categories'=>array('class_name'=>'Shop_Category', 'join_table'=>'shop_products_categories', 'order'=>'name'),
			'customer_groups'=>array('class_name'=>'Shop_CustomerGroup', 'join_table'=>'shop_products_customer_groups', 'order'=>'name', 'foreign_key'=>'customer_group_id', 'primary_key'=>'shop_product_id'),
			'related_products_all'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id'),
			
			// Interface related products list
			//
//			'related_product_list'=>array('class_name'=>'Shop_Product', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id', 'conditions'=>'shop_products.enabled=1')

			'related_product_list'=>array('class_name'=>'Shop_Product', 'order'=>'shop_products.name', 'join_table'=>'shop_related_products', 'primary_key'=>'master_product_id', 'foreign_key'=>'related_product_id', 'conditions'=>'((shop_products.enabled=1 and not (
				shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.in_stock<=0))
				)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
				and not (
				grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.in_stock<=0))
				))) and (shop_products.disable_completely is null or shop_products.disable_completely = 0)'),
				
			'extra_option_sets'=>array('class_name'=>'Shop_ExtraOptionSet', 'order'=>'shop_extra_option_sets.name', 'join_table'=>'shop_products_extra_sets', 'primary_key'=>'extra_product_id', 'foreign_key'=>'extra_option_set_id')			
		);

		public $has_many = array(
			'grouped_products_all'=>array('class_name'=>'Shop_Product', 'delete'=>true, 'order'=>'grouped_sort_order', 'foreign_key'=>'product_id'),
			'images'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='images'", 'order'=>'sort_order, id', 'delete'=>true),
			'options'=>array('class_name'=>'Shop_CustomAttribute', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),
			'product_extra_options'=>array('class_name'=>'Shop_ExtraOption', 'foreign_key'=>'product_id', 'order'=>'id', 'delete'=>true, 'order'=>'extra_option_sort_order', 'conditions'=>'(option_in_set is null or option_in_set=0)'),
			'properties'=>array('class_name'=>'Shop_ProductProperty', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),
			'price_tiers'=>array('class_name'=>'Shop_PriceTier', 'foreign_key'=>'product_id', 'order'=>'(select name from shop_customer_groups where id=customer_group_id), price desc', 'delete'=>true),
			'bundle_items_link'=>array('class_name'=>'Shop_ProductBundleItem', 'foreign_key'=>'product_id', 'order'=>'sort_order', 'delete'=>true),

			// Interface grouped products list
			//
			'grouped_product_list'=>array('class_name'=>'Shop_Product', 'delete'=>true, 'order'=>'grouped_sort_order', 'foreign_key'=>'product_id', 'conditions'=>'shop_products.enabled=1 and not (
			shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.in_stock<=0)))'),

			'files'=>array('class_name'=>'Shop_ProductFile', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='files'", 'order'=>'id', 'delete'=>true),

			'uploaded_files'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Product' and field='uploaded_files'", 'order'=>'id', 'delete'=>true)
		);

		public $calculated_columns = array(
			'page_url'=>array('sql'=>"pages.url", 'type'=>db_text, 'join'=>array('pages'=>'shop_products.page_id=pages.id')),
			'items_ordered'=>array('sql'=>'0', 'type'=>db_number),
			'grouped_name'=>array('sql'=>'if (shop_products.grouped = 1, concat(shop_products.name, " (", shop_products.grouped_option_desc,")"), shop_products.name)', 'type'=>db_text)
		);
		
		public $perproduct_shipping_cost = array(
			array(
				'country'=>'*',
				'state'=>'*',
				'zip'=>'*',
				'cost'=>'0'
			)
		);

		/**
		 * The current_price field is needed only for the price rule conditions user interface. 
		 * Please use the price() method for obtaining a current product price.
		 */
		public $custom_columns = array(
			'current_price'=>db_number,
			'csv_import_parent_sku'=>db_text, 
			'csv_related_sku'=>db_text,
			'image'=>db_text
		);
		
		public $category_sort_order;
		protected $categories_column;

		public static function create()
		{
			return new self();
		}
		
		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}

		public function define_columns($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('image', 'Image');
			
			$this->define_column('grouped_name', 'Product')->invisible();

			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Name can contain only latin characters, numbers and signs -, _, -')->method('validateUrl')->unique('The URL Name "%s" already in use. Please select another URL Name.', array($this, 'configure_unique_validator'));
			
			$this->define_column('title', 'Title')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('description', 'Long Description')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('short_description', 'Short Description')->defaultInvisible()->validation()->fn('trim');
			
			$this->define_relation_column('page', 'page', 'Custom Page ', db_varchar, $front_end ? null : '@title')->defaultInvisible()->listTitle('Page')->validation();
			$this->define_relation_column('product_type', 'product_type', 'Product Type ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->listTitle('Type')->validation();
			$this->define_relation_column('manufacturer_link', 'manufacturer_link', 'Manufacturer ', db_varchar, '@name')->defaultInvisible()->validation();
			$this->define_multi_relation_column('images', 'images', 'Images', $front_end ? null : '@name')->invisible();
			
			$this->define_column('price', 'Base Price')->currency(true)->defaultInvisible()->validation()->fn('trim')->required();
			$this->define_column('cost', 'Cost')->currency(true)->defaultInvisible()->validation()->fn('trim');
			$this->define_column('enabled', 'Enabled')->defaultInvisible();
			$this->define_column('disable_completely', 'Disable Completely')->defaultInvisible();
			
			$this->define_relation_column('tax_class', 'tax_class', 'Tax Class ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->validation()->required('Please select product tax class.');

			$this->define_column('tier_prices_per_customer', 'Take into account previous orders')->defaultInvisible();
			$this->define_column('on_sale', 'On Sale')->defaultInvisible();
			$this->define_column('sale_price_or_discount', 'Sale Price or Discount')->defaultInvisible()->validation()->fn('trim')->method('validate_sale_price_or_discount');

			$this->define_column('sku', 'SKU')->validation()->fn('trim')->required("Please enter the product SKU")->unique('The SKU "%s" is already in use.', array($this, 'configure_unique_validator'));
			
			$this->define_column('weight', 'Weight')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('width', 'Width')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('height', 'Height')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('depth', 'Depth')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('enable_perproduct_shipping_cost', 'Enable per product shipping cost')->invisible();
			$this->define_column('perproduct_shipping_cost', 'Shipping cost')->invisible()->validation();
			$this->define_column('perproduct_shipping_cost_use_parent', 'Use parent product per product shipping cost settings')->invisible();
			
			
			$this->define_column('track_inventory', 'Track Inventory')->defaultInvisible();
			$this->define_column('in_stock', 'Units In Stock')->defaultInvisible()->validation()->fn('trim')->method('validate_in_stock');
			$this->define_column('allow_negative_stock_values', 'Allow Negative Stock Values')->defaultInvisible();
			$this->define_column('hide_if_out_of_stock', 'Hide if Out Of Stock')->defaultInvisible();
			$this->define_column('stock_alert_threshold', 'Out of Stock  Threshold')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('expected_availability_date', 'Expected Availability Date')->defaultInvisible()->validation();
			
			$this->define_column('allow_pre_order', 'Allow pre-order')->defaultInvisible();
			
			$this->define_column('meta_description', 'Meta Description')->defaultInvisible()->listTitle('Meta Description')->validation()->fn('trim');
			$this->define_column('meta_keywords', 'Meta Keywords')->defaultInvisible()->listTitle('Meta Keywords')->validation()->fn('trim');
			
			$this->categories_column = $this->define_multi_relation_column('categories', 'categories', 'Categories', $front_end ? null : '@name')->defaultInvisible()->validation();
			$this->define_multi_relation_column('grouped_products_all', 'grouped_products_all', 'Grouped Products', $front_end ? null : "@grouped_option_desc")->invisible();
			
			$this->define_column('grouped_attribute_name', 'Attribute Name')->invisible()->validation()->method('validate_grouped_options');
			$this->define_column('grouped_option_desc', 'This Product Description')->invisible()->validation()->method('validate_grouped_options');

			$this->define_multi_relation_column('options', 'options', 'Options', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('product_extra_options', 'product_extra_options', 'Extra Options', $front_end ? null : "@description")->invisible();
			$this->define_multi_relation_column('extra_option_sets', 'extra_option_sets', 'Global extra option sets', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('price_tiers', 'price_tiers', 'Price Tiers', $front_end ? null : "@id")->invisible();
			$this->define_multi_relation_column('related_products_all', 'related_products_all', 'Related Products', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('properties', 'properties', 'Properties', $front_end ? null : "@name")->invisible();
			$this->define_multi_relation_column('files', 'files', 'Files', $front_end ? null : '@name')->defaultInvisible();

			$this->define_column('xml_data', 'XML Data')->invisible()->validation()->fn('trim');
			
			$this->define_column('current_price', 'Price')->invisible();
			
			$this->define_column('csv_import_parent_sku', 'Grouped - Parent Product SKU')->invisible();
			$this->define_column('csv_related_sku', 'Related products SKU')->invisible();
			$this->define_column('grouped_sort_order', 'Grouped - Sort Order')->invisible();

			$this->define_column('items_ordered', 'Units ordered')->invisible();
			
			$this->define_multi_relation_column('customer_groups', 'customer_groups', 'Customer Groups', $front_end ? null : '@name')->defaultInvisible();

			$this->define_column('enable_customer_group_filter', 'Enable customer group filter')->defaultInvisible();
			$this->define_column('product_rating', 'Rating (Approved)')->defaultInvisible();
			$this->define_column('product_rating_all', 'Rating (All)')->defaultInvisible();
			
			$this->define_column('visibility_search', 'Visible in search results')->invisible();
			$this->define_column('visibility_catalog', 'Visible in the catalog')->invisible();

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendProductModel', $this);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			if ($context == 'preview')
			{
				$user = Phpr::$security->getUser();
				$reports_access = $user->get_permission('shop', 'access_reports');

				$this->add_form_field('name', 'left')->tab('Product Summary');
				$this->add_form_field('sku', 'right')->tab('Product Summary');
				
				$this->add_form_field('product_type', 'left')->tab('Product Summary')->previewNoRelation();
				$this->add_form_field('manufacturer_link', 'right')->previewNoRelation()->previewNoOptionsMessage('Not assigned')->tab('Product Summary');
				
				if (!$this->page)
				{
					$this->add_form_field('url_name')->tab('Product Summary');
				} else
				{
					$this->add_form_field('url_name', 'left')->tab('Product Summary');
					$this->add_form_field('page', 'right')->tab('Product Summary')->previewNoRelation()->previewLink($this->page_url('/'));
				}
				
				if ($this->track_inventory)
					$this->add_form_field('in_stock')->tab('Product Summary');

				$this->add_form_field('price', 'left')->tab('Product Summary');
				$this->add_form_field('cost', 'right')->tab('Product Summary');
				
				if (!$reports_access)
				{
					$this->add_form_field('title')->tab('Description');
					$this->add_form_field('short_description')->tab('Description');
					$this->add_form_field('meta_description')->tab('Description');
					$this->add_form_field('meta_keywords')->tab('Description');
				}

				if ($this->grouped_products_all->count)
					$this->add_form_custom_area('grouped_list')->tab('Product Summary');

				if ($reports_access)
					$this->add_form_custom_area('statistics_data')->tab('Product Statistics');
			} else {
				$front_end = Db_ActiveRecord::$execution_context == 'front-end';

				if ($context == 'grouped')
				{
					$this->add_form_field('grouped_option_desc')->tab('Product')->comment('Please specify a description for a drop-down list option corresponding this product, e.g. "XXL size".', 'above');
					$column = $this->find_column_definition('grouped_option_desc');
					$column->validation()->required();
				}
			
				if ($context != 'grouped')
					$this->add_form_field('enabled', 'left')->tab('Product')->comment('Use this checkbox to show or hide the product from the website. This option does not affect grouped products.');
				else
					$this->add_form_field('enabled')->tab('Product')->comment('Use this checkbox to show or hide the product from the website.');

				if ($context != 'grouped')
					$this->add_form_field('disable_completely', 'right')->tab('Product')->comment('Use this checkbox to hide this product and all its grouped products from the website.');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('product_type', 'left')->tab('Product');
					$this->add_form_field('manufacturer_link', 'right')->tab('Product');
				}
			
				$this->add_form_field('name', 'left')->tab('Product');
				$this->add_form_field('sku', 'right')->tab('Product');

				if ($context != 'grouped')
					$this->add_form_field('url_name', 'left')->tab('Product')->comment('Specify the product URL name (for example "cannon_printer") or leave this field empty if you want to provide a specially designed product page.', 'above');
				else
					$this->add_form_field('url_name')->tab('Product');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('page', 'right')->tab('Product')->emptyOption('<default product page>')->comment('You can customize the product landing page. Select a page, specially designed for this product or leave the default value.', 'above')->optionsHtmlEncode(false);
				
					$this->categories_column->required('Please select categories the product belongs to.');
				}

				$this->add_form_field('title')->comment('Use this field to customize the product page title. Leave this field empty to use the product name as the page title.', 'above')->tab('Product');
			
				if (!$front_end)
					$this->add_form_field('tax_class')->tab('Pricing')->emptyOption('<please select>');

				$this->add_form_field('price', 'left')->tab('Pricing')->comment('The product price will be visible on the front-end store. You can set different prices for different customer groups using the tier price section below.', 'above');
				$this->add_form_field('cost', 'right')->tab('Pricing')->comment('The product cost will be subtracted from the price to get the revenue value in reports. Leave this value empty if the revenue should match the product price.', 'above');
				$this->add_form_field('on_sale')->tab('Pricing')->comment('Select to override the catalog price rules for this product and enter the sale price or discount below directly.', 'above');
				$this->add_form_field('sale_price_or_discount')->tab('Pricing')->comment('Enter the sale price as a fixed sale price (e.g. 5.00), the discount amount (e.g. -5.00) or discount percentage (e.g. 25.00%). The discount amount and percentage will be subtracted from the regular price to calculate the sale price.', 'above');

				$this->add_form_section(null, 'Tier Price')->tab('Pricing');
				$this->add_form_field('tier_prices_per_customer')->tab('Pricing');

				if (!$front_end)
					$this->add_form_field('price_tiers')->tab('Pricing')->renderAs('price_tiers');

				$this->add_form_field('short_description')->tab('Product')->size('small');
				$field = $this->add_form_field('description')->tab('Product')->renderAs(frm_html)->size('small')->saveCallback('save_item');
				$field->htmlPlugins .= ',save';
				$field->htmlFullWidth = true;
				$editor_config = System_HtmlEditorConfig::get('shop', 'shop_products_categories');
				$editor_config->apply_to_form_field($field);
			
				if (!$front_end)
				{
					$this->add_form_field('images')->renderAs(frm_file_attachments)->renderFilesAs('image_list')->addDocumentLabel('Add image(s)')->tab('Images')->noAttachmentsLabel('There are no images uploaded')->fileDownloadBaseUrl(url('ls_backend/files/get/'));
					$this->add_form_field('files')->renderAs(frm_file_attachments)->tab('Files')->fileDownloadBaseUrl(url('ls_backend/files/get/'));
				}

				$this->add_form_section('Dimensions are used for evaluating shipping cost.')->tab('Shipping');
				$this->add_form_field('weight', 'left')->tab('Shipping');
				$this->add_form_field('width', 'right')->tab('Shipping');
				$this->add_form_field('height', 'left')->tab('Shipping');
				$this->add_form_field('depth', 'right')->tab('Shipping');

				if ($context == 'grouped')
					$this->add_form_field('perproduct_shipping_cost_use_parent')->tab('Shipping');
				
				$this->add_form_field('enable_perproduct_shipping_cost')->tab('Shipping');

				$this->add_form_field('perproduct_shipping_cost')->tab('Shipping')->renderAs(frm_grid)->gridColumns(array(
					'country'=>array('title'=>'Country Code', 'align'=>'left', 'width'=>'100', 'autocomplete'=>array('type'=>'local', 'tokens'=>$this->get_ppsc_country_list())), 
					'state'=>array('title'=>'State/County Code', 'align'=>'left', 'width'=>'120', 'autocomplete'=>array('type'=>'local', 'depends_on'=>'country', 'tokens'=>$this->get_ppsc_state_list())),
					'zip'=>array('title'=>'ZIP/Postal Code', 'align'=>'left'),
					'cost'=>array('title'=>'Cost', 'align'=>'right')
				))->comment('Specify a shipping cost for different locations. The shipping cost for this product will be added to the shipping quote, which is determined by the shipping method that the customer chooses.', 'above');
			
				$this->add_form_field('track_inventory', 'left')->tab('Inventory')->comment('Enable this checkbox if you have limited number of this product in stock.');
				$this->add_form_field('hide_if_out_of_stock', 'right')->tab('Inventory')->comment('Remove the product from the website if is out of stock.', 'below');
				$this->add_form_field('allow_negative_stock_values')->tab('Inventory');
			
				$this->add_form_field('in_stock', 'left')->tab('Inventory')->comment('Specify how many units of the product there are left in stock at the moment.', 'above');
				$this->add_form_field('stock_alert_threshold', 'right')->tab('Inventory')->comment('The low number of units to set the product status to Out of Stock.', 'above');
				$this->add_form_field('expected_availability_date', 'left')->tab('Inventory');
			
				$this->add_form_field('allow_pre_order', 'left')->tab('Inventory')->comment('Allow customers to order the product even if it is out of stock.', 'below');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_field('categories')->tab('Categories')->comment('Select categories the product belongs to.', 'above')->referenceSort('name')->optionsHtmlEncode(false);
				}

				$this->add_form_field('meta_description')->tab('Meta');
				$this->add_form_field('meta_keywords')->tab('Meta');

				if ($context != 'grouped')
				{
					$this->add_form_section('Use grouped products to create options of main product what could affect its price, SKU, description or other parameters.')->tab('Grouped');
					$this->add_form_field('grouped_attribute_name', 'left')->tab('Grouped')->comment('Provide a text label to be displayed near the grouped products drop-down menu, e.g. "Size".', 'above');
					$this->add_form_field('grouped_option_desc', 'right')->tab('Grouped')->comment('Please specify a description for a drop-down list option corresponding this product, e.g. "Small size".', 'above');
					if (!$front_end)
						$this->add_form_field('grouped_products_all')->tab('Grouped');
				}

				$this->add_form_section('Use this tab to create product options what cannot affect the product price or other parameters, for example product colors.')->tab('Options');

				if (!$front_end)
					$this->add_form_field('options')->tab('Options')->renderAs('options');
			
				$this->add_form_section('Use this tab to create free or paid options what could be added to  the product, for example a gift wrap.')->tab('Extras');
				if (!$front_end)
				{
					$this->add_form_field('product_extra_options')->tab('Extras');
					$this->add_form_field('extra_option_sets')->tab('Extras')->noOptions('Global extra option sets are not defined. You can create option sets on the Shop/Products/Manage extra option sets page.')->comment('Select global extra option sets you want to include to this product.', 'above');
					$this->add_form_section('Use attributes to create product-specific properties to output on the product page, e.g. "book format"')->tab('Attributes');
					$this->add_form_field('properties')->tab('Attributes');
				}

				$this->add_form_field('xml_data')->tab('XML Data')->renderAs(frm_code_editor)->language('xml')->size('giant');

				if ($context != 'grouped' && !$front_end)
				{
					$this->add_form_section('Use this tab to create cross- and up-selling products.')->tab('Related');
					$this->add_form_field('related_products_all')->tab('Related')->renderAs('related');

					$this->add_form_field('visibility_search', 'left')->tab('Visibility')->comment('Use this checkbox to make the product visible in search results.');
					$this->add_form_field('visibility_catalog', 'right')->tab('Visibility')->comment('Use this checkbox to make the product visible on the catalog pages.');

					$this->add_form_field('enable_customer_group_filter')->tab('Visibility');
					$this->add_form_field('customer_groups')->tab('Visibility')->comment('Please select customer groups the product should be visible for.', 'above');
			
					$this->form_tab_id('Files', 'tab_files');
					$this->form_tab_id('Inventory', 'tab_inventory');
					$this->form_tab_id('Shipping', 'tab_shipping');
					$this->form_tab_id('Grouped', 'tab_grouped');
					$this->form_tab_id('Options', 'tab_options');
					$this->form_tab_id('Extras', 'tab_extras');
					$this->form_tab_id('XML Data', 'tab_xml');
				}

				/*
				 * Init product type and setup tabs visibility
				 */

				if (!$this->product_type_id)
				{
					$this->product_type = Shop_ProductType::get_default_type();
					$this->product_type_id = $this->product_type->id;
				}

				$product_type = $this->product_type;
				
				$this->form_tab_visibility('Files', $product_type->files);
				$this->form_tab_visibility('Inventory', $product_type->inventory);
				$this->form_tab_visibility('Shipping', $product_type->shipping);
				$this->form_tab_visibility('Grouped', $product_type->grouped);
				$this->form_tab_visibility('Options', $product_type->options);
				$this->form_tab_visibility('Extras', $product_type->extras);
				$this->form_tab_visibility('XML Data', $product_type->xml);
			
				Backend::$events->fireEvent('shop:onExtendProductForm', $this, $context);
				foreach ($this->api_added_columns as $column_name)
				{
					$form_field = $this->find_form_field($column_name);
					if ($form_field) {
						$form_field->optionsMethod('get_added_field_options');
						$form_field->optionStateMethod('get_added_field_option_state');
					}
				}
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetProductFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function validate_grouped_options($name, $value)
		{
			$value = trim($value);

			if (!$this->grouped_products_all->count)
				return true;
				
			if (strlen($value))
				return true;
				
			if ($name == 'grouped_attribute_name')
				$this->validation->setError('Please specify the grouped products attribute name value', $name, true);

			if ($name == 'grouped_option_desc')
				$this->validation->setError('Please specify the product option description', $name, true);
				
			return true;
		}
		

		public function get_categories_options($keyValue = -1)
		{
			$result = array();
			$obj = new self();

			if ($keyValue == -1)
				$this->list_categories_id_options(null, $result, 0, null);
			else 
			{
				if ($keyValue == null)
					return $result;
				
				$obj = Shop_Category::create();
				$obj = $obj->find($keyValue);

				if ($obj)
					return h($obj->name);
			}

			return $result;
		}

		private function list_categories_id_options($items, &$result, $level, $ignore)
		{
			if ($items === null)
				$items = Shop_Category::list_children_category_proxies(null);
			
			foreach ($items as $item)
			{
				if ($ignore !== null && $item->id == $ignore)
					continue;

				$result[$item->id] = array($item->name, null, $level, 'level'=>$level);
				$this->list_categories_id_options(Shop_Category::list_children_category_proxies($item->id), $result, $level+1, $ignore);
			}
		}
		
		public function get_page_options($key_value=-1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public function validateUrl($name, $value)
		{
			$urlName = trim($this->url_name);

			if (!strlen($urlName) && !$this->page)
				$this->validation->setError('Please specify either URL name or product custom page.', $name, true);
				
			return true;
		}

		public function validate_in_stock($name, $value)
		{
			if ($this->track_inventory && !strlen(trim($value)))
				$this->validation->setError('Please specify a number of products in stock.', $name, true);
				
			return true;
		}
		
		public function validate_sale_price_or_discount($name, $value)
		{
			if(!strlen($value) && $this->on_sale)
				$this->validation->setError('Please specify a sale price or discount or uncheck the "On Sale" checkbox.', $name, true);
			
			if($error = self::is_sale_price_or_discount_invalid($value, $this->price))
				$this->validation->setError($error, $name, true);
			
			return true;
		}
		
		public function configure_unique_validator($checker, $product, $deferred_session_key)
		{
			/*
			 * Exclude not commited deferred bindings
			 */
			
			$filter = 'not (exists(select * from db_deferred_bindings where detail_class_name=\'Shop_Product\' and master_relation_name=\'grouped_products_all\' and detail_key_value=shop_products.id) %s)';

			if ($deferred_session_key)
				$filter = sprintf($filter, ' or exists(select * 
					from 
						db_deferred_bindings as master_binding
					where 
						master_binding.detail_class_name=\'Shop_Product\' 
						and master_binding.master_relation_name=\'grouped_products_all\' 
						and master_binding.session_key=?
				)');
			else
				$filter = sprintf($filter, '');

			/*
			 * Include all commited grouped products of this master product
			 */

			if ($product->product_id) 
				$filter .= ' or (shop_products.product_id is not null and shop_products.product_id='.$product->product_id.')';
				
			$filter = '('.$filter.')';

			$checker->where($filter, $deferred_session_key);
		}

		public function before_delete($id=null)
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_order_items, shop_orders where shop_product_id=:id and shop_orders.id = shop_order_items.shop_order_id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete product because there are orders referring to it.');
				
			$count = Db_DbHelper::scalar('select count(*) from shop_order_items, shop_products, shop_orders where shop_product_id=shop_products.id and shop_products.grouped is not null and shop_products.grouped=1 and shop_products.product_id=:id and shop_orders.id = shop_order_items.shop_order_id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete product because there are orders referring to its grouped products.');
		}
		
		public function after_save()
		{
			if (!$this->grouped)
			{
				Shop_CatalogPriceRule::apply_price_rules($this->id);

				$grouped_ids = Db_DbHelper::queryArray('select id from shop_products where grouped=1 and product_id=:id', array('id'=>$this->id));
				foreach ($grouped_ids as $grouped_id)
					Shop_CatalogPriceRule::apply_price_rules($this->id);
			}
		}

		public function after_delete()
		{
		 	$files = Db_File::create()->where('master_object_class=?', get_class($this))->where('master_object_id=?', $this->id)->find_all();
		 	foreach ($files as $file)
		 		$file->delete();
		
			Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_products_customgroups where shop_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_related_products where master_product_id=:id or related_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_customer_cart_items where product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_product_price_index where pi_product_id=:id', array('id'=>$this->id));
			Db_DbHelper::query('delete from shop_product_reviews where prv_product_id=:id', array('id'=>$this->id));
		}
		
		public function after_create() 
		{
			if ($this->grouped_sort_order != -1)
			{
				Db_DbHelper::query('update shop_products set grouped_sort_order=:grouped_sort_order where id=:id', array(
					'grouped_sort_order'=>$this->id,
					'id'=>$this->id
				));

				$this->grouped_sort_order = $this->id;
			}
		}

		public function get_manufacturer_link_options($key = -1)
		{
			if ($key != -1)
			{
				if (strlen($key))
				{
					$manufacturer = Shop_Manufacturer::create()->find($key);
					if ($manufacturer)
						return $manufacturer->name;
				} 
				
				return null;
			}
			
			$options = array();
			$options[0] = '<select>';
			$options[-1] = '<create new manufacturer>';

			$manufacturers = Db_DbHelper::objectArray('select * from shop_manufacturers order by name');
			foreach ($manufacturers as $manufacturer)
				$options[$manufacturer->id] = $manufacturer->name;

			return $options;
		}

		public function copy_properties($obj, $session_key, $this_session_key)
		{
			$images = $this->list_related_records_deferred('images', $this_session_key);
			foreach ($images as $image)
			{
				$image_copy = $image->copy();
				$image_copy->master_object_class = get_class($obj);
				$image_copy->field = $image->field;
				$image_copy->save();
				$obj->images->add($image_copy, $session_key);
			}

			$files = $this->list_related_records_deferred('files', $this_session_key);
			foreach ($files as $file)
			{
				$file_copy = $file->copy();
				$file_copy->master_object_class = get_class($obj);
				$file_copy->field = $file->field;
				$file_copy->save();
				$obj->files->add($file_copy, $session_key);
			}

			/*
			 * Copy options
			 */
			
			$options = $this->list_related_records_deferred('options', $this_session_key);
			foreach ($options as $attribute)
			{
				$attribute_copy = $attribute->copy();
				$attribute_copy->save();
				$obj->options->add($attribute_copy, $session_key);
			}
			
			/*
			 * Copy properties
			 */
			
			$properties = $this->list_related_records_deferred('properties', $this_session_key);
			foreach ($properties as $property)
			{
				$property_copy = $property->copy();
				$property_copy->save();
				$obj->properties->add($property_copy, $session_key);
			}
			
			/*
			 * Copy price tiers
			 */

			$tiers = $this->list_related_records_deferred('price_tiers', $this_session_key);
			foreach ($tiers as $tier)
			{
				$tier_copy = $tier->copy();
				$tier_copy->save();
				$obj->price_tiers->add($tier_copy, $session_key);
			}
			
			/*
			 * Copy extra options
			 */
			
			$extras = $this->list_related_records_deferred('product_extra_options', $this_session_key);
			foreach ($extras as $extra)
			{
				$extra_copy = $extra->copy();
				$extra_copy->save();
				$obj->product_extra_options->add($extra_copy, $session_key);
			}
			
			// $extra_sets = $this->list_related_records_deferred('extra_option_sets', $this_session_key);
			// foreach ($extra_sets as $set)
			// 	$obj->bind('extra_option_sets', $set, $session_key);
			
			return $obj;
		}
		
		public function list_copy_properties()
		{
			return array(
				'name'=>'Name',
				'title'=>'Title',
				'enabled'=>'Enabled',
				'short_description'=>'Short Description',
				'description'=>'Long Description',
				'shipping_dimensions'=>'Dimensions and weight',
				'meta'=>'META information',
				'images'=>'Images',
				'files'=>'Downloadable Files',
				'price'=>'Base Price',
				'cost'=>'Cost',
				'tier_price'=>'Tier price',
				'on_sale' => 'On Sale',
				'sale_price_or_discount' => 'Sale Price or Discount',
				'in_stock'=>'Units in stock',
				'inventory_settings'=>'Inventory Tracking Settings',
				'expected_availability_date'=>'Expected Availability Date',
				'options'=>'Product options',
				'extras'=>'Product extras',
				'attributes'=>'Product attributes'
			);
		}
		
		public function copy_properties_to_grouped($edit_session_key, $product_ids, $properties, $post_data = null)
		{
			foreach ($product_ids as $product_id)
			{
				if (!strlen($product_id))
					continue;

				$product = Shop_Product::create()->find($product_id);
				if (!$product)
					continue;

				$product->define_form_fields('grouped');

				foreach ($properties as $property_id)
				{
					switch ($property_id)
					{
						case 'name' :
						case 'title' :
						case 'enabled' :
						case 'short_description' :
						case 'description' :
						case 'price' :
						case 'on_sale' :
						case 'sale_price_or_discount' :
						case 'cost' :
						case 'in_stock' :
							$product->$property_id = $this->$property_id;
						break;
						case 'meta' :
							$product->meta_description = $this->meta_description;
							$product->meta_keywords = $this->meta_keywords;
						break;
						case 'images' :
							$images = $this->list_related_records_deferred('images', $edit_session_key);
							foreach ($product->images as $image)
								$image->delete();

							foreach ($images as $image)
							{
								$image_copy = $image->copy();
								$image_copy->master_object_class = get_class($product);
								$image_copy->field = $image->field;
								$image_copy->save();
								$product->images->add($image_copy);
							}
						break;
						case 'shipping_dimensions' :
							$product->weight = $this->weight;
							$product->width = $this->width;
							$product->height = $this->height;
							$product->depth = $this->depth;
						break;
						case 'files' :
							$files = $this->list_related_records_deferred('files', $edit_session_key);
							foreach ($product->files as $file)
								$file->delete();

							foreach ($files as $file)
							{
								$file_copy = $file->copy();
								$file_copy->master_object_class = get_class($product);
								$file_copy->field = $file->field;
								$file_copy->save();
								$product->files->add($file_copy);
							}
						break;
						case 'tier_price' :
							$product->tier_prices_per_customer = $this->tier_prices_per_customer;
							$product->tier_price_compiled = $this->tier_price_compiled;
							
							$tiers = $this->list_related_records_deferred('price_tiers', $edit_session_key);
							foreach ($product->price_tiers as $tier)
								$tier->delete();
								
							foreach ($tiers as $tier)
							{
								$tier_copy = $tier->copy();
								$tier_copy->save();
								$product->price_tiers->add($tier_copy);
							}
						break;
						case 'inventory_settings' :
							$product->track_inventory = $this->track_inventory;
							$product->hide_if_out_of_stock = $this->hide_if_out_of_stock;
							$product->stock_alert_threshold = $this->stock_alert_threshold;
							$product->allow_pre_order = $this->allow_pre_order;
							$product->allow_negative_stock_values = $this->allow_negative_stock_values;
							
							if (!strlen($product->in_stock))
								$product->in_stock = 0;
						break;
						case 'expected_availability_date' :
							$product->expected_availability_date = $this->expected_availability_date;
						break;
						case 'options' :
							$options = $this->list_related_records_deferred('options', $edit_session_key);
							foreach ($product->options as $option)
								$option->delete();

							foreach ($options as $attribute)
							{
								$attribute_copy = $attribute->copy();
								$attribute_copy->save();
								$product->options->add($attribute_copy);
							}
						break;
						case 'extras' :
							$extras = $this->list_related_records_deferred('product_extra_options', $edit_session_key);
							foreach ($product->product_extra_options as $extra_option)
								$extra_option->delete();

							foreach ($extras as $extra)
							{
								$extra_copy = $extra->copy();
								$extra_copy->save();
								$product->product_extra_options->add($extra_copy);
							}

							$extras_sets = array();
							if ($post_data && is_array($post_data))
							{
								if (array_key_exists('Shop_Product', $post_data) && array_key_exists('extra_option_sets', $post_data['Shop_Product']))
									$extras_sets = $post_data['Shop_Product']['extra_option_sets'];
							} else
							{
								$extras_set_collection = $this->list_related_records_deferred('extra_option_sets', $edit_session_key);
								$extras_sets = $extras_set_collection->as_array('id');
							}
							
							$product->copy_extra_option_sets($extras_sets);
						break;
						case 'attributes' :
							$attributes = $this->list_related_records_deferred('properties', $edit_session_key);
							foreach ($product->properties as $property)
								$property->delete();

							foreach ($attributes as $property)
							{
								$property_copy = $property->copy();
								$property_copy->save();
								$product->properties->add($property_copy);
							}

						break;
					}
				}
				
				$product->save();
			}
		}
		
		public function ungroup($parent_product, $session_key = null, $categories = null)
		{
			Db_DbHelper::query('update shop_products set grouped_attribute_name=:grouped_attribute_name, grouped=null, product_id=null where id=:id', array('id'=>$this->id, 'grouped_attribute_name'=>$parent_product->grouped_attribute_name));
			
			if ($categories)
			{
				foreach ($categories as $category_id)
				{
				    $bind = array('shop_product_id'=>$this->id, 'shop_category_id'=>$category_id);
				    
				    Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:shop_product_id and shop_category_id=:shop_category_id', $bind);
					Db_DbHelper::query('insert into shop_products_categories(shop_product_id, shop_category_id) values (:shop_product_id, :shop_category_id)', $bind);
				}
			}

			if ($session_key)
				$obj = Db_DeferredBinding::reset_object_field_bindings($parent_product, $this, 'grouped_products_all', $session_key);

			Shop_Module::update_catalog_version();
		}
		
		public function duplicate_product($grouped_parent = null)
		{
			$copy = new self();
			$fields = $this->fields();
			
			$exclude_columns = array(
				'id',
				'created_user_id',
				'updated_user_id',
				'created_at',
				'updated_at'
			);
			
			$unique_columns = array(
				'url_name',
				'name',
				'sku'
			);

			/*
			 * Copy plain fields
			 */

			foreach ($fields as $column_name=>$column_desc)
			{
				if (in_array($column_name, $exclude_columns))
					continue;
					
				if (array_key_exists($column_name, $this->has_models))
					continue;
					
				$column_value = $this->$column_name;
				if ($column_name == 'enabled')
					$column_value = 0;
					
				if (in_array($column_name, $unique_columns))
					$column_value = Db_DbHelper::getUniqueColumnValue($copy, $column_name, $column_value);

				$copy->$column_name = $column_value;
			}

			/*
			 * Copy relations
			 */

			$context = $grouped_parent ? 'grouped' : null;
			$copy->define_form_fields($context);

			$this->copy_properties($copy, null, null);

			/*
			 * Copy categories
			 */

			if (!$this->grouped)
				$copy->categories = $this->categories->as_array('id', 'id');

			$copy->save();

			/*
			 * Copy grouped products
			 */

			if (!$grouped_parent)
			{
				foreach ($this->grouped_products_all as $grouped_product)
					$grouped_product->duplicate_product($copy);
			} else
			{
				$grouped_parent->grouped_products_all->add($copy);
				$grouped_parent->save();
			}
		}

		public function __get($name)
		{
			/*
			 * Process properties of grouped products
			 */
		
			if ($name == 'grouped_products')
				return $this->eval_grouped_product_list();
			
			if ($name == 'grouped_menu_label')
			{
				if ($this->grouped)
					return $this->master_grouped_product->grouped_attribute_name;
					
				return $this->grouped_attribute_name;
			}
			
			if ($name == 'manufacturer')
			{
				if ($this->grouped)
					return $this->master_grouped_product->manufacturer_link;
				else
					return $this->manufacturer_link;
					
				return $this->grouped_attribute_name;
			}
			
			if ($name == 'bundle_items')
			{
				if ($this->grouped)
					return $this->master_grouped_product->bundle_items_link;
				else
					return $this->bundle_items_link;
			}

			if ($name == 'category_list')
			{
				if ($this->grouped)
					return $this->master_grouped_product->categories;
					
				return $this->categories;
			}

			if ($name == 'master_grouped_product_id')
			{
				if ($this->grouped)
					return $this->product_id;
					
				return $this->id;
			}
			
			if ($name == 'related_products')
			{
				if ($this->grouped)
					return $this->master_grouped_product->related_product_list;

				return $this->related_product_list;
			}
			
			if ($name == 'master_grouped_option_desc')
			{
				if ($this->grouped)
					return $this->master_grouped_product->grouped_option_desc;

				return $this->grouped_option_desc;
			}
			
			if ($name == 'rating_approved')
			{
				$result = 0;
				
				if ($this->grouped)
					$result = $this->master_grouped_product->product_rating;
				else
					$result = $this->product_rating;
					
				return round(($result*2), 0)/2;
			}
			
			if ($name == 'rating_review_num')
			{
				$result = 0;
				
				if ($this->grouped)
					return $this->master_grouped_product->product_rating_review_num;

				return $this->product_rating_review_num;
			}
			
			if ($name == 'rating_all')
			{
				$result = 0;
				
				if ($this->grouped)
					$result = $this->master_grouped_product->product_rating_all;
				else
					$result = $this->product_rating_all;
					
				return round(($result*2), 0)/2;
			}
			
			if ($name == 'rating_all_review_num')
			{
				$result = 0;
				
				if ($this->grouped)
					return $this->master_grouped_product->product_rating_all_review_num;

				return $this->product_rating_all_review_num;
			}
			
			if ($name == 'extra_options')
				return $this->get_extra_options_merged();
			
			return parent::__get($name);
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

				Db_DbHelper::query('update shop_products set grouped_sort_order=:grouped_sort_order where id=:id', array(
					'grouped_sort_order'=>$order,
					'id'=>$id
				));
			}

			return $result;
		}
		
		public static function update_page_reference($parent_product)
		{
			Db_DbHelper::query('update shop_products set page_id=:page_id where product_id is not null and product_id=:product_id', array(
				'page_id'=>$parent_product->page_id,
				'product_id'=>$parent_product->id
			));
		}
		
		public static function set_product_units_in_stock($product_id, $value)
		{
			Db_DbHelper::query('update shop_products set in_stock=:value where id=:id', array(
				'value'=>$value,
				'id'=>$product_id
			));
		}

		/**
		 * Hides disabled products. Call this method before you call the find() or find_all() methods
		 */
		public function apply_visibility()
		{
			$this->where('enabled=1 and (disable_completely is null or disable_completely=0)');
			return $this;
		}

		/**
		 * Hides products which should not be visible for a current customer.
		 * Call this method before you call the find() or find_all() methods.
		 */
		public function apply_customer_group_visibility()
		{
			$customer_group_id = Cms_Controller::get_customer_group_id();
			$this->where('
				((enable_customer_group_filter is null or enable_customer_group_filter=0) or (
					enable_customer_group_filter = 1 and
					exists(select * from shop_products_customer_groups where shop_product_id=shop_products.id and customer_group_id=?)
				))
			', $customer_group_id);
			return $this;
		}

		/**
		 * Hides products which should not be visible in the catalog.
		 * Call this method before you call the find() or find_all() methods.
		 */
		public function apply_catalog_visibility()
		{
			$this->where('visibility_catalog is not null and visibility_catalog=1');
			return $this;
		}
		
		/**
		 * Applies product stock avaliability filters
		 */
		public function apply_availability()
		{
			$this->where('
				((shop_products.enabled=1 and (shop_products.grouped is null or shop_products.grouped=0) and not (
					shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.in_stock<=0))
					)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
					and not (
						grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.in_stock<=0))
				)))');
			return $this;
		}
		
		/**
		* @deprecated use apply_availability instead
		*/
		public function apply_avaliability()
		{
			return $this->apply_availability();
		}

		/**
		 * Applies visibility, customer group filters, and availability filters
		 */
		public function apply_filters()
		{
			return $this->apply_visibility()->apply_catalog_visibility()->apply_customer_group_visibility()->apply_availability();
		}

		/**
		 * Orders a product list by current product price
		 */
		public function order_by_price($direction = 'asc')
		{
			if ($direction !== 'asc' && $direction != 'desc')
				$direction = 'asc';
			
			return $this->order(sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()).' '.$direction);
		}

		public function compile_tier_prices($session_key)
		{
			$tiers = $this->list_related_records_deferred('price_tiers', $session_key);
			$result = array();
			foreach ($tiers as $tier)
			{
				$tier_array = array();
				$tier_array['customer_group_id'] = $tier->customer_group_id;
				$tier_array['quantity'] = $tier->quantity;
				$tier_array['price'] = $tier->price;
				$result[] = (object)$tier_array;
			}
			
			$this->tier_price_compiled = serialize($result);
		}
		
		public function list_tier_prices()
		{
			if (!strlen($this->tier_price_compiled))
				return array();
				
			try
			{
				$result = unserialize($this->tier_price_compiled);
				return $result;
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading tier prices for the "'.$this->name.'" product');
			}
		}

		public function list_group_price_tiers($group_id)
		{
			$product_price_tiers = $this->list_tier_prices();

			$result = array();
			$general_price_tiers = array();
			foreach ($product_price_tiers as $tier)
			{
				if ($tier->customer_group_id == $group_id)
					$result[$tier->quantity] = $tier->price;
					
				if ($tier->customer_group_id == null)
					$general_price_tiers[$tier->quantity] = $tier->price;
			}
			
			if (!count($result))
				$result = $general_price_tiers;
				
			if (!array_key_exists(1, $result))
				$result[1] = $this->price;
				
			ksort($result);
				
			return $result;
		}
		
		/**
		 * Returns the product price, taking into account the tier price settings
		 * @param int $group_id Customer group identifier
		 * @param int $quantity Product quantity
		 */
		public function eval_tier_price($group_id, $quantity)
		{
			$price_tiers = $this->list_group_price_tiers($group_id);
			$price_tiers = array_reverse($price_tiers, true);

			foreach ($price_tiers as $tier_quantity=>$price)
			{
				if ($tier_quantity <= $quantity)
					return $price;
			}

			return $this->price;
		}
		
		/**
		 * This method used by the discount engine internally
		 */
		public function set_compiled_price_rules($price_rules, $rule_map)
		{
			$this->price_rules_compiled = serialize($price_rules);
			$this->price_rule_map_compiled = serialize($rule_map);
			Db_DbHelper::query('update shop_products set price_rules_compiled=:price_rules_compiled, price_rule_map_compiled=:price_rule_map_compiled where id=:id', array(
				'price_rules_compiled'=>$this->price_rules_compiled,
				'price_rule_map_compiled'=>$this->price_rule_map_compiled,
				'id'=>$this->id
			));
			
			$this->update_price_index();
		}
		
		/**
		 * This method used by the discount engine internally
		 */
		public function update_price_index()
		{
			Db_DbHelper::query('delete from shop_product_price_index where pi_product_id=:product_id', array('product_id'=>$this->id));

			$groups = Shop_CustomerGroup::list_groups();
			$index_values = array();
			foreach ($groups as $group_id=>$group)
			{
				$index_values[] = array($group_id, $this->get_discounted_price_no_tax(1, $group_id));
			}
			
			if ($cnt = count($index_values))
			{
				$query = 'insert into shop_product_price_index(pi_product_id, pi_group_id, price) values';
				foreach ($index_values as $index=>$values)
				{
					$query .= '('.$this->id.','.mysql_real_escape_string($values[0]).','.mysql_real_escape_string($values[1]).')';
					if ($index < $cnt-1)
						$query .= ',';
				}
				
				Db_DbHelper::query($query);
			}
		}
		
		/** 
		 * Updates product rating fields. This method is called by the rating system internally.
		 */
		public static function update_rating_fields($product_id)
		{
			Db_DbHelper::query("update shop_products set 
				product_rating=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status=:approved_status),
				product_rating_all=(select avg(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id),
				product_rating_review_num=ifnull((select count(*) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id and prv_moderation_status=:approved_status), 0),
				product_rating_all_review_num=ifnull((select count(prv_rating) from shop_product_reviews where prv_rating is not null and prv_product_id=shop_products.id), 0)
				where shop_products.id = :product_id
			", array(
				'product_id'=>$product_id,
				'approved_status'=>Shop_ProductReview::status_approved
			));
		}

		/**
		 * Returns the product discounted price for the specified cart item quantity.
		 * If there are no price rules defined for the product and no sale price or discount specified, returns the product original price
		 * (taking into account tier prices)
		 */
		public function get_sale_price_no_tax($quantity, $customer_group_id = null)
		{
			if ($customer_group_id === null )
				$customer_group_id = Cms_Controller::get_customer_group_id();

			if($this->on_sale && strlen($this->sale_price_or_discount))
			{
				$price = $this->price_no_tax($quantity, $customer_group_id);
				return round(self::get_set_sale_price($price, $this->sale_price_or_discount), 2);
			}

			if (!strlen($this->price_rules_compiled))
				return $this->price_no_tax($quantity, $customer_group_id);

			$price_rules = array();
			try
			{
				$price_rules = unserialize($this->price_rules_compiled);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading price rules for the "'.$this->name.'" product');
			}

			if (!array_key_exists($customer_group_id, $price_rules))
				return $this->price_no_tax($quantity, $customer_group_id);

			$price_tiers = $price_rules[$customer_group_id];
			$price_tiers = array_reverse($price_tiers, true);

			foreach ($price_tiers as $tier_quantity=>$price)
			{
				if ($tier_quantity <= $quantity)
					return round($price, 2);
			}

			return $this->price_no_tax($quantity, $customer_group_id);
		}
		
		/**
		* @deprecated use get_sale_price_no_tax instead
		*/
		public function get_discounted_price_no_tax($quantity, $customer_group_id = null)
		{
			return $this->get_sale_price_no_tax($quantity, $customer_group_id);
		}
		
		/**
		 * Returns the product discounted price for the specified cart item quantity, with taxes included
		 * If there are no price rules defined for the product, returns the product original price 
		 * (taking into account tier prices).
		 * Includes tax if the "Display catalog/cart prices including tax" option is enabled
		 */
		public function get_sale_price($quantity = 1, $customer_group_id = null)
		{
			$price = $this->get_sale_price_no_tax($quantity, $customer_group_id);
			
			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($this->tax_class_id, $price) + $price;
		}
		
		/**
		* @deprecated use get_sale_price instead
		*/
		public function get_discounted_price($quantity = 1, $customer_group_id = null)
		{
			return $this->get_sale_price($quantity, $customer_group_id);
		}
		
		/**
		 * Returns TRUE if there are active catalog-level price rules affecting the product price
		 * or if the product is on sale ('On Sale' checkbox on Product UI)
		 */
		public function is_on_sale()
		{
			return $this->price_no_tax() <> $this->get_sale_price_no_tax(1);
		}
		
		/**
		 * @deprecated use is_on_sale() instead
		 */
		public function is_discounted()
		{
			return $this->is_on_sale();
		}
		
		/**
		 * Returns the difference between the regular price and sale price of the product
		 */
		public function get_sale_reduction($quantity, $customer_group_id = null)
		{
			$sale_price = $this->get_sale_price_no_tax($quantity, $customer_group_id);
			$original_price = $this->price_no_tax($quantity, $customer_group_id);

			return $original_price - $sale_price;
		}
		
		/**
		* @deprecated use get_sale_reduction instead
		*/
		public function get_discount($quantity, $customer_group_id = null)
		{
			return $this->get_sale_reduction($quantity, $customer_group_id);
		}

		public function before_save($deferred_session_key = null) 
		{
			$this->validate_shipping_cost();
			
			$this->compile_tier_prices($deferred_session_key);
			$this->pt_description = html_entity_decode(strip_tags($this->description), ENT_QUOTES, 'UTF-8');
			
			$this->perproduct_shipping_cost = serialize($this->perproduct_shipping_cost);
		}
		
		protected function after_fetch()
		{
			if(is_string($this->perproduct_shipping_cost) && strlen($this->perproduct_shipping_cost))
				$this->perproduct_shipping_cost = unserialize($this->perproduct_shipping_cost);
		}
		
		protected function custom_relation_save()
		{
			/*
			 * Preserve the Top Products sort orders
			 */

			$preserved_sort_orders = array();

			$has_bind = isset($this->changed_relations['bind']['categories']);
			$has_unbind = isset($this->changed_relations['unbind']['categories']);

			if ($has_unbind && $has_bind)
			{
				$unbind_categories = $this->changed_relations['unbind']['categories'];
				$unbind_keys = $unbind_categories['values'];
				
				$bind_data = array('product_id'=>$this->id, 'unbind_keys'=>$unbind_keys);

				if (count($unbind_keys))
				{
					$existing_records = Db_DbHelper::objectArray('select * from shop_products_categories where shop_product_id=:product_id and shop_category_id in (:unbind_keys)', $bind_data);

					foreach ($existing_records as $record)
						$preserved_sort_orders[$record->shop_category_id] = $record->product_category_sort_order;
						
					Db_DbHelper::query('delete from shop_products_categories where shop_product_id=:product_id and shop_category_id in (:unbind_keys)', $bind_data);
				}

				unset($this->changed_relations['unbind']['categories']);
				
				$bind_categories = $this->changed_relations['bind']['categories'];
				$bind_keys = $bind_categories['values'];
				
				if (count($bind_keys))
				{
					foreach ($bind_keys as $category_id)
					{
						$sort_order = array_key_exists($category_id, $preserved_sort_orders) ? $preserved_sort_orders[$category_id] : null;
						
						$bind_data = array(
							'shop_product_id' => $this->id,
							'shop_category_id'=> $category_id, 
							'product_category_sort_order'=>$sort_order
						);
						Db_DbHelper::query('insert into shop_products_categories(shop_product_id, shop_category_id, product_category_sort_order) values (:shop_product_id, :shop_category_id, :product_category_sort_order)', $bind_data);
					}
				}

				unset($this->changed_relations['bind']['categories']);
			}
		}
		
		public function get_extra_options_merged()
		{
			$options = $this->product_extra_options->as_array();
			foreach ($this->extra_option_sets as $option_set)
			{
				foreach ($option_set->extra_options as $option)
				{
					$option->product_id = $this->id;
					$option->__lock();
					$options[] = $option;
				}
			}
			
			return new Db_DataCollection($options);
		}
		
		public function copy_extra_option_sets($sets)
		{
			Db_DbHelper::query('delete from shop_products_extra_sets where extra_product_id=:id', array('id'=>$this->id));
			
			foreach ($sets as $set_id)
			{
				Db_DbHelper::query('insert into shop_products_extra_sets(extra_product_id, extra_option_set_id) values (:extra_product_id, :extra_option_set_id)', array(
					'extra_product_id'=>$this->id,
					'extra_option_set_id'=>$set_id
				));
			}
		}

		/**
		 * Returns a list of attributes which can be used in price rule conditions
		 */
		public function get_condition_attributes()
		{
			$fields = array(
				'name',
				'description',
				'short_description',
				'price',
				'tax_class',
				'sku',
				'weight',
				'width',
				'height',
				'depth',
				'categories',
				'current_price',
				'manufacturer_link',
				'product_type'
			);

			$result = array();
			$definitions = $this->get_column_definitions();
			foreach ($fields as $field)
			{
				if (isset($definitions[$field]))
					$result[$field] = $definitions[$field]->displayName;
			}

			return $result;
		}

		/**
		 * Returns a list of grouped products, including the master product
		 */
		public function eval_grouped_product_list()
		{
			if ($this->grouped)
				$list = $this->master_grouped_product->grouped_product_list;
			else
				$list = $this->grouped_product_list;
				
			$master_product = $this->grouped ? $this->master_grouped_product : $this;
			if (!$master_product->enabled || ($master_product->is_out_of_stock() && $master_product->hide_if_out_of_stock))
				return $list;

			if (!strlen($master_product->grouped_attribute_name) || !strlen($master_product->grouped_option_desc))
				return $list;

			$array = $list->as_array();
			$result = array($master_product);
			foreach ($array as $obj)
				$result[] = $obj;

			usort($result, array('Shop_Product', 'sort_grouped_products'));

			return new Db_DataCollection($result);
		}
		
		public static function list_enabled_products()
		{
			$obj = Shop_Product::create();
			$obj->where('(shop_products.enabled=1) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1)');
			return $obj->where('grouped is null');
		}
		
		public static function sort_grouped_products($product_1, $product_2)
		{
			if ($product_1->grouped_sort_order == $product_2->grouped_sort_order)
				return 0;
				
			if ($product_1->grouped_sort_order > $product_2->grouped_sort_order)
				return 1;
				
			return -1;
		}
		
		public static function list_allowed_sort_columns()
		{
			$result = self::$allowed_sorting_columns;
			$custom_field_sets = Backend::$events->fireEvent('shop:onGetProductSortColumns');
			foreach ($custom_field_sets as $fields) 
			{
				foreach ($fields as $field)
					$result[] = $field;
			}
			
			return $result;
		}

		/**
		 * Inventory tracking
		 */
		
		public function decrease_stock($quantity)
		{
			if (!$this->track_inventory)
				return;
			
			$in_stock = $this->in_stock - $quantity;
			if ($in_stock < 0 && !$this->allow_negative_stock_values)
				$in_stock = 0;
			
			$this->in_stock = $in_stock;
			Db_DbHelper::query('update shop_products set in_stock=:in_stock where id=:id', array(
				'in_stock'=>$this->in_stock,
				'id'=>$this->id
			));
			
			if ($this->is_out_of_stock())
			{
				Backend::$events->fireEvent('shop:onProductOutOfStock', $this);
				
				$users = Users_User::create()->from('users', 'distinct users.*');
				$users->join('shop_roles', 'shop_roles.id=users.shop_role_id');
				$users->where('shop_roles.notified_on_out_of_stock is not null and shop_roles.notified_on_out_of_stock=1');
				$users->where('(users.status is null or users.status = 0)');
				$users = $users->find_all();
				
				$template = System_EmailTemplate::create()->find_by_code('shop:out_of_stock_internal');
				if (!$template)
					return;

				$product_url = Phpr::$request->getRootUrl().url('shop/products/edit/'.$this->master_grouped_product_id.'?'.uniqid());

				$message = $this->set_email_variables($template->content, $product_url);
				$template->subject = $this->set_email_variables($template->subject, $product_url);

				$template->send_to_team($users, $message);
			}
		}
		
		protected function set_email_variables($message, $product_url)
		{
			$message = str_replace('{out_of_stock_product}', h($this->name), $message);
			$message = str_replace('{out_of_stock_sku}', h($this->sku), $message);
			$message = str_replace('{out_of_stock_count}', h($this->in_stock), $message);
			$message = str_replace('{out_of_stock_url}', $product_url, $message);
			
			return $message;
		}
		
		public function is_out_of_stock()
		{
			if (!$this->track_inventory)
				return false;

			if ($this->stock_alert_threshold !== null)
				return $this->in_stock <= $this->stock_alert_threshold;

			if ($this->in_stock <= 0)
			 	return true;

			return false;
		}
		
		/**
		 * Returns the total number (a sum) of items in stock for the 
		 * product and all its grouped products
		 */
		public function in_stock_grouped()
		{
			$master_product_id = $this->product_id ? $this->product_id : $this->id;
				
			return Db_DbHelper::scalar(
				'select sum(ifnull(in_stock, 0)) from shop_products where id=:id or (product_id is not null and product_id=:id)', 
				array('id'=>$master_product_id));
		}

		/*
		 * Product CSV import/export functions
		 */
		
		public function get_csv_import_columns($import = true)
		{
			$columns = $this->get_column_definitions();
			
			$columns['price']->displayName = 'Price';
			$columns['product_type']->listTitle = 'Product Type';
			$columns['grouped_attribute_name']->displayName = 'Grouped - Attribute Name';
			$columns['grouped_option_desc']->displayName = 'Grouped - Product Description';
			$columns['tier_prices_per_customer']->displayName = 'Price Tiers - Take into account previous orders';

			unset(
				$columns['image'],
				$columns['grouped_name'],
				$columns['page'],
				$columns['grouped_products_all'],
				$columns['related_products_all'],
				$columns['properties'],
				$columns['created_at'],
				$columns['created_user_name'],
				$columns['updated_at'],
				$columns['updated_user_name'],
				$columns['current_price'],
				$columns['customer_groups'],
				$columns['enable_customer_group_filter'],
				$columns['items_ordered'],
				$columns['product_rating'],
				$columns['product_rating_all']
			);

			$rules = $this->validation->getRule('categories');
			if ($rules)
				$rules->required = false;
			$rules1 = $this->validation->getRule('name');
			if ($rules1)
				$rules1->required = false;
			$rules2 = $this->validation->getRule('price');
			if ($rules2)
				$rules2->required = false;
			/*
			 * Add product attribute columns
			 */

			if ($import)
				$attributes = Shop_PropertySetProperty::create()->order('name')->find_all();
			else
				$attributes = Shop_ProductProperty::create()->order('name')->find_all();

			foreach ($attributes as $attribute)
			{
				$column_display_name = 'ATTR: '.$attribute->name;
				$column_info = array(
					'dbName'=>$column_display_name, 
					'displayName'=>$column_display_name,
					'listTitle'=>$column_display_name,
					'type'=>db_text
				);
				$columns[$column_display_name] = (object)$column_info;
			}

			$column_info = array(
				'dbName'=>'product_groups', 
				'displayName'=>'Product groups',
				'listTitle'=>'Product groups',
				'type'=>db_text
			);
			$columns['product_groups'] = (object)$column_info;
					
			return $columns;
		}
		
		public static function generate_unique_url_name($name)
		{
			$separator = Phpr::$config->get('URL_SEPARATOR', '_');
			
			$url_name = preg_replace('/[^a-z0-9]/i', $separator, $name);
			$url_name = str_replace($separator.$separator, $separator, $url_name);
			if (substr($url_name, -1) == $separator)
				$url_name = substr($url_name, 0, -1);
				
			$url_name = trim(mb_strtolower($url_name));

			$orig_url_name = $url_name;
			$counter = 1;
			while (Db_DbHelper::scalar('select count(*) from shop_products where url_name=:url_name', array('url_name'=>$url_name)))
			{
				$url_name = $orig_url_name.$separator.$counter;
				$counter++;
			}
			
			return $url_name;
		}
		
		/*
		 * Per-product shipping cost
		 */
		
		protected function get_ppsc_country_list()
		{
			$countries = Shop_Country::get_object_list();
			$result = array();
			$result[] = '* - Any country||*';
			foreach ($countries as $country)
				$result[] = $country->code.' - '.$country->name.'||'.$country->code;

			return $result;
		}
		
		protected function get_ppsc_state_list()
		{
			$result = array(
				'*'=>array('* - Any state||*')
			);

			$states = Db_DbHelper::objectArray('select shop_states.code as state_code, shop_states.name, shop_countries.code as country_code
				from shop_states, shop_countries 
				where shop_states.country_id = shop_countries.id
				order by shop_countries.code, shop_states.name');

			foreach ($states as $state)
			{
				if (!array_key_exists($state->country_code, $result))
					$result[$state->country_code] = array('* - Any state||*');

				$result[$state->country_code][] = $state->state_code.' - '.$state->name.'||'.$state->state_code;
			}

			$countries = Shop_Country::get_object_list();
			foreach ($countries as $country)
			{
				if (!array_key_exists($country->code, $result))
					$result[$country->code] = array('* - Any state||*');
			}

			return $result;
		}
		
		protected function validate_shipping_cost()
		{
			if (!$this->enable_perproduct_shipping_cost)
				return;
			
			if (!is_array($this->perproduct_shipping_cost) || !count($this->perproduct_shipping_cost))
				$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost or disable the Per-Product Shipping Cost feature.');

			/*
			 * Preload countries and states
			 */

			$db_country_codes = Db_DbHelper::objectArray('select * from shop_countries order by code');
			$countries = array();
			foreach ($db_country_codes as $country)
				$countries[$country->code] = $country;
			
			$country_codes = array_merge(array('*'), array_keys($countries));
			$db_states = Db_DbHelper::objectArray('select * from shop_states order by code');
			
			$states = array();
			foreach ($db_states as $state)
			{
				if (!array_key_exists($state->country_id, $states))
					$states[$state->country_id] = array('*'=>null);

				$states[$state->country_id][mb_strtoupper($state->code)] = $state;
			}
			
			foreach ($countries as $country)
			{
				if (!array_key_exists($country->id, $states))
					$states[$country->id] = array('*'=>null);
			}

			/*
			 * Validate table rows
			 */

			$processed_locations = array();
			foreach ($this->perproduct_shipping_cost as $row_index=>&$locations)
			{
				$empty = true;
				foreach ($locations as $value)
				{
					if (strlen(trim($value)))
					{
						$empty = false;
						break;
					}
				}

				if ($empty)
					continue;

				/*
				 * Validate country
				 */
				$country = $locations['country'] = trim(mb_strtoupper($locations['country']));

				if (!strlen($country))
					$this->field_error('perproduct_shipping_cost', 'Please specify country code. Valid codes are: '.implode(', ', $country_codes).'.', $row_index, 'country');
				
				if (!array_key_exists($country, $countries) && $country != '*')
					$this->field_error('perproduct_shipping_cost', 'Invalid country code. Valid codes are: '.implode(', ', $country_codes).'.', $row_index, 'country');
					
				/*
				 * Validate state
				 */
				if ($country != '*')
				{
					$country_obj = $countries[$country];
					$country_states = $states[$country_obj->id];
					$state_codes = array_keys($country_states);

					$state = $locations['state'] = trim(mb_strtoupper($locations['state']));
					if (!strlen($state))
						$this->field_error('perproduct_shipping_cost', 'Please specify state code. State codes, valid for '.$country_obj->name.' are: '.implode(', ', $state_codes).'.', $row_index, 'state');

					if (!in_array($state, $state_codes) && $state != '*')
						$this->field_error('perproduct_shipping_cost', 'Invalid state code. State codes, valid for '.$country_obj->name.' are: '.implode(', ', $state_codes).'.', $row_index, 'state');
				} else {
					$state = $locations['state'] = trim(mb_strtoupper($locations['state']));
					if (!strlen($state) || $state != '*')
						$this->field_error('perproduct_shipping_cost', 'Please specify state code as wildcard (*) to indicate "Any state" condition.', $row_index, 'state');
				}
				
				/*
				 * Process ZIP code
				 */
				
				$locations['zip'] = trim(mb_strtoupper($locations['zip']));

				$price = $locations['cost'] = trim(mb_strtoupper($locations['cost']));
				if (!strlen($price))
					$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost', $row_index, 'cost');

			 	if (!Core_Number::is_valid($price))
					$this->field_error('perproduct_shipping_cost', 'Invalid numeric value in column Cost', $row_index, 'cost');

				$processed_locations[] = $locations;
			}

			if (!count($processed_locations))
				$this->field_error('perproduct_shipping_cost', 'Please specify shipping cost or disable the Per-Product Shipping Cost option.');
				
			$this->perproduct_shipping_cost = $processed_locations;
		}
		
		public function get_shipping_cost($country_id, $state_id, $zip)
		{
			if ($this->grouped)
			{
				if ($this->perproduct_shipping_cost_use_parent)
				{
					$enable_perproduct_shipping_cost = $this->master_grouped_product->enable_perproduct_shipping_cost;
					$perproduct_shipping_cost = $this->master_grouped_product->perproduct_shipping_cost;
				}
				else
				{
					$enable_perproduct_shipping_cost = $this->enable_perproduct_shipping_cost;
					$perproduct_shipping_cost = $this->perproduct_shipping_cost;
				}
			} else
			{
				$enable_perproduct_shipping_cost = $this->enable_perproduct_shipping_cost;
				$perproduct_shipping_cost = $this->perproduct_shipping_cost;
			}
				
			if (!$enable_perproduct_shipping_cost)
				return 0;
				
			if (!is_array($perproduct_shipping_cost) || !count($perproduct_shipping_cost))
				return 0;
			
			$country = Shop_Country::find_by_id($country_id);
			if (!$country)
				return 0;

			$state = null;
			if (strlen($state_id))
				$state = Shop_CountryState::find_by_id($state_id);
				
			$country_code = $country->code;
			$state_code = $state ? mb_strtoupper($state->code) : '*';

			/*
			 * Find shipping rate
			 */

			$rate = 0;

			foreach ($perproduct_shipping_cost as $row)
			{
				if ($row['country'] != $country_code && $row['country'] != '*')
					continue;
					
				if (mb_strtoupper($row['state']) != $state_code && $row['state'] != '*')
					continue;

				if ($row['zip'] != '' && $row['zip'] != '*')
				{
					$row['zip'] = str_replace(' ', '', $row['zip']);
					
					if ($row['zip'] != $zip)
					{
						if (mb_substr($row['zip'], -1) != '*')
							continue;
							
						$len = mb_strlen($row['zip'])-1;
							
						if (mb_substr($zip, 0, $len) != mb_substr($row['zip'], 0, $len))
							continue;
					}
				}
				
				$rate = $row['cost'];
				break;
			}

			return $rate;
		}

		protected function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}

		/*
		 * Interface methods
		 */

		/*
		 * Returns product price. Use this method instead of accessing the price field directly
		 */
		public function price_no_tax($quantity = 1, $customer_group_id = null)
		{
			if ($customer_group_id === null)
				$customer_group_id = Cms_Controller::get_customer_group_id();
			$price = $this->eval_tier_price($customer_group_id, $quantity);
			$price_adjusted = Backend::$events->fire_event(array('name' => 'shop:onGetProductPriceNoTax', 'type'=>'filter'), array(
				'product' => $this,
				'price' => $price,
				'quantity' => $quantity,
				'customer_group_id' => $customer_group_id
				));
			return ($price_adjusted['price']) ? $price_adjusted['price'] : $price;
		}

		/*
		 * Returns product price with tax included, if the "Display catalog/cart prices including tax" option is enabled
		 */
		public function price($quantity = 1, $customer_group_id = null)
		{
			$price = $this->price_no_tax($quantity, $customer_group_id);

			$include_tax = Shop_CheckoutData::display_prices_incl_tax();
			if (!$include_tax)
				return $price;

			return Shop_TaxClass::get_total_tax($this->tax_class_id, $price) + $price;
		}
		
		public function volume()
		{
			return $this->width*$this->height*$this->depth;
		}

		public function page_url($default)
		{
			$page_url = Cms_PageReference::get_page_url($this, 'page_id', $this->page_url);

			if (!strlen($page_url))
				return root_url($default.'/'.$this->url_name);
				
			if (!strlen($this->url_name))
				return root_url($page_url);
				
			return root_url($page_url.'/'.$this->url_name);
		}
		
		public function image_url($index, $width, $height, $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			if ($index < 0 || $index > $this->images->count-1)
				return null;

			return $this->images[$index]->getThumbnailPath($width, $height, $returnJpeg, $params);
		}

		public static function get_rss($feed_name, $feed_description, $default_product_url, $record_number = 20)
		{
			$products = Shop_Product::create();
			$products->order('created_at desc');
			$products->where('shop_products.enabled = 1');
			$products->where('(shop_products.grouped is null or shop_products.grouped=0)');
			$products = $products->limit($record_number)->find_all();
			
			$root_url = Phpr::$request->getRootUrl();
			$rss = new Core_Rss( $feed_name, $root_url, $feed_description, Phpr::$request->getCurrentUrl() );

			foreach ( $products as $product )
			{
				$product_url = $product->page_url($default_product_url);
				if(substr($product_url, 0, 1) != '/')
					$product_url = '/'.$product_url;

				$link = $root_url.$product_url;
				if(substr($link, -1) != '/')
					$link .= '/';

				$image = $product->image_url(0, 100, 'auto');
				if (strlen($image))
					$image = $root_url.$image;
					
				$body = $product->description;
				if ($image)
					$body .= '<p><img alt="" src="'.$image.'"/></p>';

				$rss->add_entry( $product->name,
					$link,
					$product->id,
					$product->created_at,
					$product->short_description,
					$product->created_at,
					'LemonStand',
					$body);
			}

			return $rss->to_xml();
		}

		public static function find_products($query, $pagination, $page=1, $options = array())
		{
			$query = str_replace('%', '', $query);
			
			$words = Core_String::split_to_words($query);
			$query_presented = strlen(trim($query));

			$configuration = Shop_ConfigurationRecord::get();
			$customer_group_id = Cms_Controller::get_customer_group_id();
			
			$search_in_grouped_products = $configuration->search_in_grouped_products;
			$search_in_product_names = array_key_exists('search_in_product_names', $options) ? $options['search_in_product_names'] : true;
			
			$grouped_products_filter = "and grouped is null";
			
			$grouped_inventory_subquery = 'or exists(
				select 
					* 
				from 
					shop_products grouped_products 
				where 
					grouped_products.product_id is not null 
					and grouped_products.product_id=shop_products.id 
					and grouped_products.enabled=1 
					and not (
						grouped_products.track_inventory is not null 
						and grouped_products.track_inventory=1 
						and grouped_products.hide_if_out_of_stock is not null 
						and grouped_products.hide_if_out_of_stock=1 
						and (
							(grouped_products.stock_alert_threshold is not null 
							and grouped_products.in_stock <= grouped_products.stock_alert_threshold)
							or (grouped_products.stock_alert_threshold is null and grouped_products.in_stock=0)
						)
					)
			)';
			
			if ($search_in_grouped_products)
			{
				$grouped_products_filter = "and ifnull((if (grouped is null, shop_products.disable_completely, (select disable_completely from shop_products parent_list where parent_list.id=shop_products.product_id))), 0) = 0";
				$grouped_inventory_subquery = null;
			}
			
			$group_filter_field = "if (grouped is null, enable_customer_group_filter, (select enable_customer_group_filter from shop_products parent_list where parent_list.id=shop_products.product_id))";
			$search_visibility_field = "if (grouped is null, visibility_search, (select visibility_search from shop_products parent_list where parent_list.id=shop_products.product_id))";

			$query_template = "
				select 
					shop_products.name, 
					shop_products.created_at, 
					shop_products.id, 
					shop_products.price, 
					shop_products.on_sale,
					shop_products.sale_price_or_discount,
					shop_products.tax_class_id, 
					shop_products.price_rules_compiled, 
					shop_products.tier_price_compiled, 
					shop_products.product_rating, 
					shop_products.product_rating_all 
				from 
					shop_products 
				left join shop_products_categories on shop_products_categories.shop_product_id=shop_products.id
				%TABLES%
				where 
					(
						(shop_products.enabled=1 and not 
							(shop_products.track_inventory is not null 
								and shop_products.track_inventory=1 
								and shop_products.hide_if_out_of_stock is not null 
								and shop_products.hide_if_out_of_stock=1 
								and (
									(shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) 
									or (shop_products.stock_alert_threshold is null and shop_products.in_stock=0)
								)
							)
						) 
						$grouped_inventory_subquery
					) 
					$grouped_products_filter
					and (
						ifnull($group_filter_field, 0) = 0
						or 
						(
							exists(select * from shop_products_customer_groups where shop_product_id=if(grouped is null, shop_products.id, shop_products.product_id) and customer_group_id='$customer_group_id')
						)
					)
					and ifnull($search_visibility_field, 0) <> 0
					and (shop_products.disable_completely is null or shop_products.disable_completely = 0)
					and %FILTER% order by shop_products.name";

			$product_ids = array();
			
			/*
			 * Apply categories
			 */
			
			$category_ids = isset($options['category_ids']) ? $options['category_ids'] : array();
			if ($category_ids)
			{
				$valid_ids = array();
				foreach ($category_ids as $category_id)
				{
					if (strlen($category_id) && preg_match('/^[0-9]+$/', $category_id))
						$valid_ids[] = $category_id;
				}
				
				if ($valid_ids)
				{
					$valid_ids = "('".implode("','", $valid_ids)."')";
					$query_template = self::set_search_query_params($query_template, '%TABLES%', 'shop_products_categories.shop_category_id in '.$valid_ids.' and %FILTER%');
				}
			}
			
			/*
			 * Apply manufacturers
			 */
			
			$manufacturer_ids = isset($options['manufacturer_ids']) ? $options['manufacturer_ids'] : array();
			if ($manufacturer_ids)
			{
				$valid_ids = array();
				foreach ($manufacturer_ids as $manufacturer_id)
				{
					if (strlen($manufacturer_id) && preg_match('/^[0-9]+$/', $manufacturer_id))
						$valid_ids[] = $manufacturer_id;
				}
				
				if ($valid_ids)
				{
					$valid_ids = "('".implode("','", $valid_ids)."')";
					$query_template = self::set_search_query_params($query_template, '%TABLES%', 'shop_products.manufacturer_id is not null and shop_products.manufacturer_id in '.$valid_ids.' and %FILTER%');
				}
			}
			
			/*
			 * Apply options
			 */
			
			$product_options = isset($options['options']) ? $options['options'] : array();
			if ($product_options)
			{
				$options_queries = array();
				foreach ($product_options as $name=>$value)
				{
					$value = trim($value);
					if (!strlen($value))
						continue;
						
					if ($value == '*')
						$value = '';

					$name = mysql_real_escape_string($name);
					$value = mysql_real_escape_string($value);
					if (substr($value, 0, 1) != '!')
						$options_queries[] = "(exists(select id from shop_custom_attributes where name='".$name."' and attribute_values like '%".$value."%' and product_id=shop_products.id))";
					else
					{
						$value = substr($value, 1);
						$options_queries[] = "(exists(select id from shop_custom_attributes where name='".$name."' and find_in_set('".$value."', replace(attribute_values, '"."\n"."', ',')) > 0 and product_id=shop_products.id))";
					}
				}
				
				if ($options_queries)
				{
					$options_queries = implode(' and ', $options_queries);
					$query_template = self::set_search_query_params($query_template, '%TABLES%', $options_queries.' and %FILTER%');
				}
			}
			
			/*
			 * Apply attributes
			 */
			
			$product_attributes = isset($options['attributes']) ? $options['attributes'] : array();
			if ($product_attributes)
			{
				$attribute_queries = array();
				foreach ($product_attributes as $name=>$values)
				{
					if (!is_array($values))
						$values = array($values);
						
					$product_attribute_queries = array();
						
					foreach ($values as $value)
					{
						$value = trim($value);
						if (!strlen($value))
							continue;
							
						if ($value == '*')
							$value = '';

						$name = mysql_real_escape_string($name);
						$value = mysql_real_escape_string($value);
						if (substr($value, 0, 1) != '!')
							$product_attribute_queries[] = "(exists(select id from shop_product_properties where name='".$name."' and value like '%".$value."%' and product_id=shop_products.id))";
						else {
							$value = substr($value, 1);
							$product_attribute_queries[] = "(exists(select id from shop_product_properties where name='".$name."' and value= '".$value."' and product_id=shop_products.id))";
						}
					}
					
					if($product_attribute_queries)
						$attribute_queries[] = '('.implode(' or ', $product_attribute_queries).')';
				}

				if ($attribute_queries)
				{
					$attribute_queries = implode(' and ', $attribute_queries);
					$query_template = self::set_search_query_params($query_template, '%TABLES%', $attribute_queries.' and %FILTER%');
				}
 			}
 			
 			/*
			* Apply custom product groups
			*/
			
			$custom_groups = isset($options['custom_groups']) ? $options['custom_groups'] : array();
			if($custom_groups)
			{
				$valid_groups = array();
				foreach($custom_groups as $custom_group)
				{
					if(strlen($custom_group))
						$valid_groups[] = mysql_real_escape_string($custom_group);
				}
				if(count($valid_groups))
				{
					$custom_groups = "('".implode("','", $valid_groups)."')";
					$custom_group_filter = " shop_products.id in (select shop_product_id from shop_products_customgroups inner join shop_custom_group on (shop_products_customgroups.shop_custom_group_id = shop_custom_group.id) where code in ".$custom_groups.") ";
					$query_template = self::set_search_query_params($query_template, '%TABLES%', $custom_group_filter.' and %FILTER%');
				}
			}

			/*
			 * Apply third-party search functions
			 */
			
			$search_events = Backend::$events->fireEvent('shop:onRegisterProductSearchEvent', $options);
			
			if ($search_events)
			{
				foreach ($search_events as $event) 
				{
					if ($event)
					{
						$query_template_update = Backend::$events->fireEvent($event, $options, $query_template);
						if ($query_template_update)
						{
							foreach ($query_template_update as $template_update)
							{
								if ($template_update)
									$query_template = $template_update;
							}
						}
					}
				}
			}

			/*
			 * Search in product names
			 */

			if ($search_in_product_names)
			{
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'shop_products.name', 2)));
				foreach ($records as $record)
					$product_ids[$record->id] = $record;
			}

			/*
			 * Search in short descriptions
			 */

			if ($configuration->search_in_short_descriptions && $query_presented)
			{
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'short_description', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;

					$product_ids[$record->id] = $record;
				}
			}
			
			/*
			 * Search in long descriptions
			 */

			if ($configuration->search_in_long_descriptions && $query_presented)
			{
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'pt_description', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;
			
					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in keywords
			 */

			if ($configuration->search_in_keywords && $query_presented)
			{
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, Db_DbHelper::formatSearchQuery($query, 'meta_keywords', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;
			
					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Search in categories
			 */
			
			if ($configuration->search_in_categories && $query_presented)
			{
				$category_query_template = 'select id from shop_categories where %s and (category_is_hidden is null or category_is_hidden=0) order by name';
				$category_records = Db_DbHelper::objectArray(sprintf($category_query_template, Db_DbHelper::formatSearchQuery($query, 'name', 2)));
			
				foreach ($category_records as $category)
				{
					$product_records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, 'shop_products_categories.shop_category_id='.$category->id));

					foreach ($product_records as $record)
					{
						if (array_key_exists($record->id, $product_ids))
							continue;

						$product_ids[$record->id] = $record;
					}
				}
			}
			
			/*
			 * Search in manufacturers
			 */
			
			if ($configuration->search_in_manufacturers && $query_presented)
			{
				$manufacturer_join = 'left join shop_manufacturers on shop_manufacturers.id = shop_products.manufacturer_id';
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, $manufacturer_join, Db_DbHelper::formatSearchQuery($query, 'shop_manufacturers.name', 2)));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;
			
					$product_ids[$record->id] = $record;
				}
			}
			
			/*
			 * Search in product SKU
			 */

			if ($configuration->search_in_sku && $query_presented)
			{
				$filter = "(%s or (exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id and %s)))";
				$filter = sprintf($filter, 
					Db_DbHelper::formatSearchQuery($query, 'shop_products.sku', 2),
					Db_DbHelper::formatSearchQuery($query, 'grouped_products.sku', 2)
				);
				
				$records = Db_DbHelper::objectArray(self::set_search_query_params($query_template, null, $filter));
				foreach ($records as $record)
				{
					if (array_key_exists($record->id, $product_ids))
						continue;
			
					$product_ids[$record->id] = $record;
				}
			}

			/*
			 * Apply price range filter
			 */
			
			$min_price = isset($options['min_price']) ? trim($options['min_price']) : null;
			$max_price = isset($options['max_price']) ? trim($options['max_price']) : null;
			
			if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $min_price))
				$min_price = null;

			if (!preg_match('/^([0-9]+\.[0-9]+|[0-9]+)$/', $max_price))
				$max_price = null;

			if (strlen($min_price) || strlen($max_price))
			{
				/*
				 * Load grouped products
				 */

				if (count($product_ids))
				{
					$grouped_products = Db_DbHelper::objectArray("
						select
							grouped_products.product_id, 
							grouped_products.id, 
							grouped_products.price, 
							grouped_products.on_sale,
							grouped_products.sale_price_or_discount,
							grouped_products.price_rules_compiled, 
							grouped_products.tier_price_compiled,
							grouped_products.tax_class_id
						from 
							shop_products grouped_products 
						where 
							grouped_products.product_id is not null 
							and grouped_products.product_id in (:parent_product_ids) 
							and grouped_products.enabled=1 
							and not (
								grouped_products.track_inventory is not null 
								and grouped_products.track_inventory=1 
								and grouped_products.hide_if_out_of_stock is not null
								and grouped_products.hide_if_out_of_stock=1 
								and (
									(
										grouped_products.stock_alert_threshold is not null 
										and grouped_products.in_stock <= grouped_products.stock_alert_threshold
									) or (
										grouped_products.stock_alert_threshold is null 
										and grouped_products.in_stock=0
										)
									)
							)", array(
						'parent_product_ids'=>array_keys($product_ids)
					));
				} else
					$grouped_products = array();

				$grouped_products_sorted = array();
				foreach ($grouped_products as $grouped_product)
				{
					if (!array_key_exists($grouped_product->product_id, $grouped_products_sorted))
						$grouped_products_sorted[$grouped_product->product_id] = array();

					$grouped_products_sorted[$grouped_product->product_id][$grouped_product->id] = $grouped_product;
				}
				
				$test_product = Shop_Product::create();
				$filtered_product_ids = array();
				foreach ($product_ids as $id=>$product)
				{
					if (self::check_price_range($test_product, $product, $min_price, $max_price))
						$filtered_product_ids[$id] = $product;
					else
					{
						if (array_key_exists($product->id, $grouped_products_sorted))
						{
							foreach ($grouped_products_sorted[$product->id] as $grouped_product)
							{
								if (self::check_price_range($test_product, $grouped_product, $min_price, $max_price))
								{
									$filtered_product_ids[$id] = $product;
									continue;
								}
							}
						}
					}
				}
				
				$product_ids = $filtered_product_ids;
			}
			
			/*
			 * Apply product sorting
			 */
			
			$sorting = array_key_exists('sorting', $options) ? trim($options['sorting']) : 'relevance';
			
			if (!$sorting)
				$sorting = 'relevance';

			$allowed_sorting_columns = array('relevance', 'name', 'price', 'created_at', 'product_rating', 'product_rating_all');

			$normalized_search_expr = mb_strtolower($sorting);
			$normalized_search_expr = trim(str_replace('desc', '', str_replace('asc', '', $normalized_search_expr)));
			
			if (!in_array($normalized_search_expr, $allowed_sorting_columns))
				$sorting = 'relevance';
				
			global $shop_find_products_sorting;
			global $shop_find_products_sorting_direction;
			global $shop_find_products_test_product;
			
			$shop_find_products_sorting = $normalized_search_expr;
			$shop_find_products_sorting_direction = strpos($sorting, 'desc') === false ? 1 : -1;
			
			$shop_find_products_test_product = Shop_Product::create();

			if ($sorting != 'relevance')
				uasort($product_ids, array('Shop_Product', 'sort_search_result'));

			/*
			 * Paginate and return the data collection
			 */

			$pagination->setRowCount(count($product_ids));
			$pagination->setCurrentPageIndex($page-1);
			$product_ids = array_keys($product_ids);

			$product_ids = array_slice($product_ids, $pagination->getFirstPageRowIndex(), $pagination->getPageSize(), true);

			$result_array = array();
			if (count($product_ids))
			{
				$order_str = array();
				foreach ($product_ids as $index=>$product_id)
					$order_str[] = 'when shop_products.id='.$product_id.' then '.$index;
				
				$order_str = 'case '.implode(' ', $order_str).' end';
				
				return Shop_Product::create()->where('shop_products.id in (?)', array($product_ids))->order($order_str)->find_all();
			}

 			return new Db_DataCollection(array());
		}
		
		public static function eval_static_product_price($test_product, $product)
		{
			$test_product->price = $product->price;
			$test_product->price_rules_compiled = $product->price_rules_compiled;
			$test_product->tier_price_compiled = $product->tier_price_compiled;
			$test_product->tax_class_id = $product->tax_class_id;
			$test_product->on_sale = $product->on_sale;
			$test_product->sale_price_or_discount = $product->sale_price_or_discount;
			
			return $test_product->get_discounted_price();
		}
		
		public static function sort_search_result($product_1, $product_2)
		{
			global $shop_find_products_sorting;
			global $shop_find_products_sorting_direction;
			global $shop_find_products_test_product;
			
			if ($shop_find_products_sorting != 'price')
			{
				if ($shop_find_products_sorting == 'name')
					return strcmp(mb_strtolower($product_1->name), mb_strtolower($product_2->name))*$shop_find_products_sorting_direction;
				
				if (!strlen($product_1->$shop_find_products_sorting) && !strlen($product_2->$shop_find_products_sorting))
					return strcmp($product_1->name, $product_2->name);
				
				if ($product_1->$shop_find_products_sorting == $product_2->$shop_find_products_sorting)
					return 0;

				if ($product_1->$shop_find_products_sorting > $product_2->$shop_find_products_sorting)
					return 1*$shop_find_products_sorting_direction;

				return -1*$shop_find_products_sorting_direction;
			} else 
			{
				$product_1_price = self::eval_static_product_price($shop_find_products_test_product, $product_1);
				$product_2_price = self::eval_static_product_price($shop_find_products_test_product, $product_2);

				if ($product_1_price == $product_2_price)
					return 0;

				if ($product_1_price > $product_2_price)
					return 1*$shop_find_products_sorting_direction;

				return -1*$shop_find_products_sorting_direction;
			}
		}
		
		public static function check_price_range($test_product, $product, $min_price, $max_price)
		{
			$product_price = self::eval_static_product_price($test_product, $product);

			if (strlen($min_price) && $product_price < $min_price)
				return false;

			if (strlen($max_price) && $product_price > $max_price)
				return false;

			return true;
		}
		
		/**
		 * @deprecated use list_on_sale instead
		 */
		public static function list_discounted($options = array())
		{
			return self::list_on_sale($options);
		}

		/*
		 * Returns a list of products that are on sale
		 * @param array $options Specifies an options. Example:
		 * list_products(array(
		 * 'sorting'=>array('name asc', 'price asc')
		 * ))
		 * See the Shop_Product class description in the documentation for more details.
		 * @return Shop_Product Returns an object of the Shop_Product. 
		 * Call the find_all() method of this object to obtain a list of products (Db_DataCollection object).
		 */
		public static function list_on_sale($options = array())
		{
			$obj = self::create();
			$obj->apply_filters();
			$obj->where(sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()).' < shop_products.price');

			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');

			if (!is_array($sorting))
				$sorting = array('name');

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, Shop_Product::$allowed_sorting_columns))
					continue;
				
				if (strpos($sorting_column, 'price') !== false)
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				elseif(strpos($sorting_column, 'manufacturer') !== false)
					$sorting_column = str_replace('manufacturer', 'manufacturer_link_calculated', $sorting_column);
				elseif (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}
			
			if (!$sorting)
				$sorting = array('name');

			$obj->reset_order();
			$sort_str = implode(', ', $sorting);
			$obj->order($sort_str);

			return $obj;
		}
		
		public static function set_search_query_params($template, $tables, $filter)
		{
			$result = str_replace('%TABLES%', $tables, $template);
			$result = str_replace('%FILTER%', $filter, $result);
			return $result;
		}
		
		public function visible_for_customer_group($group_id)
		{
			if (!$this->enable_customer_group_filter)
				return true;

			return Db_DbHelper::scalar('select count(*) from shop_products_customer_groups where shop_product_id=:product_id and customer_group_id=:group_id', array(
				'product_id'=>$this->id,
				'group_id'=>$group_id
			));
		}

		public function list_related_products()
		{
			if ($this->grouped)
				$product_obj = $this->master_grouped_product->related_product_list_list;
			else
				$product_obj = $this->related_product_list_list;
			
			$product_obj->apply_customer_group_visibility()->apply_catalog_visibility();

			return $product_obj;
		}

		/**
		 * Returns the full list of grouped products, including this product, regardless of 
		 * the products stock availability
		 * @return array
		 */
		public function list_grouped_products()
		{
			$master_product = ($this->grouped) ? $this->master_grouped_product : $this;

			$all_grouped_products = array($master_product);
			foreach($master_product->grouped_products_all as $grouped_product)
			    $all_grouped_products[] = $grouped_product;

			usort($all_grouped_products, array('Shop_Product', 'sort_grouped_products'));

			return $all_grouped_products;
		}
		
		public function list_extra_option_groups()
		{
			$extras = $this->extra_options;
			$groups = array();
			foreach ($extras as $extra)
			{
				if (!array_key_exists($extra->group_name, $groups))
					$groups[$extra->group_name] = array();
					
				$groups[$extra->group_name][] = $extra;
			}
			
			return $groups;
		}

		/**
		 * Returns value of a product attribute (managed on the Attributes tab of the Create/Edit Product page),
		 * specified using the attribute name.
		 */
		public function get_attribute($name)
		{
			$name = mb_strtolower($name);
			$attribtues = $this->properties;
			foreach ($attribtues as $attribute)
			{
				if (mb_strtolower($attribute->name) == $name)
					return $attribute->value;
			}
			
			return null;
		}
		
		/**
		 * Returns a list of catalog price rules applied to the product.
		 * @param int $group_id Customer group identifier. Omit this parameter
		 * to use a group of the current customer.
		 */
		public function list_applied_catalog_rules($group_id = null)
		{
			if (!strlen($this->price_rule_map_compiled))
				return array();
			
			try
			{
				$rule_map = unserialize($this->price_rule_map_compiled);
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error loading price rule list for the "'.$this->name.'" product');
			}
			
			if ($group_id === null)
				$group_id = Cms_Controller::get_customer_group_id();
				
			if (!array_key_exists($group_id, $rule_map))
				return array();

			$result = array();
			foreach ($rule_map[$group_id] as $rule_id)
				$result[] = Shop_CatalogPriceRule::find_rule_by_id($rule_id);
				
			return $result;
		}
		
		/**
		 * Returns a list of categories the product belongs to.
		 * This method is more effective in terms of memory usage 
		 * than the Shop_Product::$categories and Shop_Product::$category_list fields.
		 * Use it when you need to load category lists for multiple products a time.
		 * @return Db_DataCollection
		 */
		public function list_categories()
		{
			if ($this->category_cache !== null)
				return $this->category_cache;

			$master_product_id = $this->grouped ? $this->product_id : $this->id;
			$category_ids = Db_DbHelper::scalarArray('select shop_category_id from shop_products_categories where shop_product_id=:id', array('id'=>$master_product_id));

			$this->category_cache = array();
			foreach ($category_ids as $category_id)
			{
				$category = Shop_Category::find_category($category_id, false);
				if ($category)
					$this->category_cache[] = $category;
			}
			
			$this->category_cache = new Db_DataCollection($this->category_cache);
			return $this->category_cache;
		}

		/**
		 * Returns a list of all product reviews
		 */
		public function list_all_reviews()
		{
			$product_id = $this->grouped ? $this->product_id : $this->id;
			
			return Shop_ProductReview::create()->where('prv_product_id=?', $product_id)->order('created_at')->find_all();
		}

		/**
		 * Returns a list of approved product reviews
		 */
		public function list_reviews()
		{
			$product_id = $this->grouped ? $this->product_id : $this->id;

			$obj = Shop_ProductReview::create()->where('prv_product_id=?', $product_id);
			$obj->where('prv_moderation_status=?', Shop_ProductReview::status_approved);
			return $obj->order('created_at')->find_all();
		}

		/*
		 * Uploaded files support (product customization)
		 */
		
		public function list_uploaded_files($session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');
				
			return $this->list_related_records_deferred('uploaded_files', $session_key);
		}
		
		public function add_file_from_post($file_info, $session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');
				
			if (!array_key_exists('error', $file_info) || $file_info['error'] == UPLOAD_ERR_NO_FILE)
				return;
				
			Phpr_Files::validateUploadedFile($file_info);

			$file = Db_File::create();
			$file->is_public = false;

			$file->fromPost($file_info);
			$file->master_object_class = get_class($this);
			$file->field = 'uploaded_files';
			$file->save();
			
			Backend::$events->fireEvent('shop:onBeforeProductFileAdded', $this, $file);
			$this->uploaded_files->add($file, $session_key);
			Backend::$events->fireEvent('shop:onAfterProductFileAdded', $this, $file);
		}
		
		public function delete_uploaded_file($file_id, $session_key = null)
		{
			if (!$session_key)
				$session_key = post('ls_session_key');

			if (!strlen($file_id))
				return;
				
			if ($file = Db_File::create()->find($file_id))
				$this->uploaded_files->delete($file, $session_key);
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public static function get_set_sale_price($original_price, $sale_price_or_discount)
		{
			if(!isset($sale_price_or_discount) || !strlen($sale_price_or_discount) || $error = self::is_sale_price_or_discount_invalid($sale_price_or_discount))
				return $original_price;
			
			$price = $original_price;
			$percentage_sign = strpos($sale_price_or_discount, '%');
			if($percentage_sign !== false)
			{
				if($percentage_sign == 0)
					$sale_discount = substr($sale_price_or_discount, 1);
				else
					$sale_discount = substr($sale_price_or_discount, 0, strlen($sale_price_or_discount)-1);
				$price = $original_price - $sale_discount*$original_price/100;
			}
			elseif(Core_Number::is_valid($sale_price_or_discount))
			{
				$price = min($sale_price_or_discount, $original_price);
			}
			elseif(preg_match('/^\-[0-9]*?\.?[0-9]*$/', $sale_price_or_discount))
			{
				$price = $original_price + $sale_price_or_discount;
			}
			
			return $price > 0 ? $price : 0;
		}
		
		/**
		* Checks the sale price or discount value and returns false when it's valid
		* Valid values are numbers (as the set sale price e.g. 5.5, 6, 12.54), negative numbers (as the set sale discount e.g. -5, -12.22) 
		* or percentages (as the discount percentage, values between 0% and 100%).
		* @param string sale price or discount to check
		* @param numeric product price, optional (when this parameter is supplied, function checks that the sale price or discount is not greater than the original price)
		* @return mixed, returns false when sale price or discount is valid or the error string when it's not.
		*/
		public static function is_sale_price_or_discount_invalid($value, $price=null)
		{
			$percentage_sign = strpos($value, '%');
			if($percentage_sign !== false)
			{
				if($percentage_sign == 0)
					$sale_discount = substr($value, 1);
				else
					$sale_discount = substr($value, 0, strlen($value)-1);
				//should be a number and less or equal to 100%
				if(!Core_Number::is_valid($sale_discount) || $sale_discount > 100)
					return 'Sale discount should be a valid number between 0% and 100%.';
			}
			else
			{
				if(!Core_Number::is_valid($value))
				{
					//if it's a negative number, it could be valid
					if(!preg_match('/^\-[0-9]*?\.?[0-9]*$/', $value))
						return 'Sale price or discount amount should be a valid number or percentage.';
					//if negative value, should be greater than price
					elseif($price && (-1*$value > $price))
						return 'Sale discount is greater than the product price.';
				}
				elseif($price && $value > $price)
					return 'Sale price is greater than the product price.';
			}
			return false;
		}
	}

?>