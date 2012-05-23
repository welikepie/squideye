<?php

	class Shop_Manufacturer extends Db_ActiveRecord
	{
		public $table_name = 'shop_manufacturers';
		
		public $belongs_to = array(
			'country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'country_id'),
			'state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'state_id')
		);

		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public $calculated_columns = array( 
			'product_num'=>array('sql'=>'select count(*) from shop_products where
				shop_products.manufacturer_id=shop_manufacturers.id and (grouped is null or grouped=0)', 'type'=>db_number)
		);
		
		public $has_many = array(
			'logo'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Manufacturer' and field='logo'", 'order'=>'id', 'delete'=>true),
			'products'=>array('class_name'=>'Shop_Product', 'order'=>'shop_products.name', 'conditions'=>'((shop_products.enabled=1 and (shop_products.grouped is null or shop_products.grouped=0) and not (
				shop_products.track_inventory is not null and shop_products.track_inventory=1 and shop_products.hide_if_out_of_stock is not null and shop_products.hide_if_out_of_stock=1 and ((shop_products.stock_alert_threshold is not null and shop_products.in_stock <= shop_products.stock_alert_threshold) or (shop_products.stock_alert_threshold is null and shop_products.in_stock=0))
				)) or exists(select * from shop_products grouped_products where grouped_products.product_id is not null and grouped_products.product_id=shop_products.id  and grouped_products.enabled=1 
				and not (
					grouped_products.track_inventory is not null and grouped_products.track_inventory=1 and grouped_products.hide_if_out_of_stock is not null and grouped_products.hide_if_out_of_stock=1 and ((grouped_products.stock_alert_threshold is not null and grouped_products.in_stock <= grouped_products.stock_alert_threshold) or (grouped_products.stock_alert_threshold is null and grouped_products.in_stock=0))
			)))', 'foreign_key'=>'manufacturer_id')
		);

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required();
			$this->define_column('description', 'Description')->invisible()->validation()->fn('trim');
			$this->define_multi_relation_column('logo', 'logo', 'Logo', '@name')->invisible();
			
			$this->define_column('address', 'Street Address')->validation()->fn('trim');
			$this->define_column('city', 'City')->validation()->fn('trim');
			$this->define_column('zip', 'ZIP/Postal Code')->validation()->fn('trim');
			$this->define_column('phone', 'Phone Number')->validation()->fn('trim');
			$this->define_column('fax', 'Fax Number')->validation()->fn('trim');
			$this->define_relation_column('country', 'country', 'Country ', db_varchar, '@name');
			$this->define_relation_column('state', 'state', 'State ', db_varchar, '@name');
			$this->define_column('email', 'Email')->validation()->fn('trim')->email(true);
			$this->define_column('url', 'Website URL')->validation()->fn('trim');
			$this->define_column('url_name', 'URL Name')->validation()->fn('trim')->fn('mb_strtolower')->regexp('/^[0-9a-z_-]*$/i', 'URL Name can contain only latin characters, numbers and signs -, _, -')->unique('The URL Name "%s" already in use. Please select another URL Name.')->required('Please specify the manufacturer URL name.');
			$this->define_column('product_num', 'Products');

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendManufacturerModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name', 'left')->tab('Name and Description');
			$this->add_form_field('url_name', 'right')->tab('Name and Description');

			$field = $this->add_form_field('description')->tab('Name and Description')->renderAs(frm_html)->size('small');
			$editor_config = System_HtmlEditorConfig::get('shop', 'shop_manufacturers');
			$editor_config->apply_to_form_field($field);
			
			$this->add_form_field('address')->tab('Address and Contacts')->renderAs(frm_textarea)->size('small');
			$this->add_form_field('city', 'left')->tab('Address and Contacts');
			$this->add_form_field('zip', 'right')->tab('Address and Contacts');
			$this->add_form_field('country', 'left')->tab('Address and Contacts')->emptyOption('<select>');
			$this->add_form_field('state', 'right')->tab('Address and Contacts')->emptyOption('<select>');

			$this->add_form_field('phone', 'left')->tab('Address and Contacts');
			$this->add_form_field('fax', 'right')->tab('Address and Contacts');
			$this->add_form_field('email', 'left')->tab('Address and Contacts');
			$this->add_form_field('url', 'right')->tab('Address and Contacts');
			
			$this->add_form_field('logo')->renderAs(frm_file_attachments)->renderFilesAs('single_image')->addDocumentLabel('Upload logo')->tab('Logo')->noAttachmentsLabel('Logo is not uploaded')->noLabel()->imageThumbSize(150)->fileDownloadBaseUrl(url('ls_backend/files/get/'));
			Backend::$events->fireEvent('shop:onExtendManufacturerForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
				{
					$form_field->optionsMethod('get_added_field_options');
					$form_field->optionStateMethod('get_added_field_option_state');
				}
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetManufacturerFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function get_added_field_option_state($db_name, $key_value)
		{
			$result = Backend::$events->fireEvent('shop:onGetManufacturerFieldState', $db_name, $key_value, $this);
			foreach ($result as $value)
			{
				if ($value !== null)
					return $value;
			}
			
			return false;
		}
		
		public function get_country_options($key_value=-1)
		{
			return $this->list_countries($key_value);
		}
		
		protected function list_countries($key_value=-1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_Country::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$records = Db_DbHelper::objectArray('select * from shop_countries order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		public function get_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			return $this->list_states($this->country_id);
		}
		
		public function list_states($country_id)
		{
			if (!$country_id || !Shop_Country::create()->find($country_id))
			{
				$obj = Shop_Country::create()->order('name')->find();
				if ($obj)
					$country_id = $obj->id;
			}

			$states = Db_DbHelper::objectArray(
				'select * from shop_states where country_id=:country_id order by name',
				array('country_id'=>$country_id)
			);
			
			$result = array();
			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}
		
		public function set_default_country()
		{
			$this->country_id = Db_UserParameters::get('manufacturer_def_country');
			$this->state_id = Db_UserParameters::get('manufacturer_def_state');
		}

		public function after_save()
		{
			Db_UserParameters::set('manufacturer_def_country', $this->country_id);
			Db_UserParameters::set('manufacturer_def_state', $this->state_id);
		}
		
		public function before_delete($id=null)
		{
			if ($this->product_num)
				throw new Phpr_ApplicationException("The manufacturer cannot be deleted because {$this->product_num} products(s) refer to it.");
		}
		
		public function logo_url($width, $height, $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			if (!$this->logo->count)
				return null;

			return $this->logo[0]->getThumbnailPath($width, $height, $returnJpeg, $params);
		}
		
		/**
		 * Returns a list of the manufacturer products
		 * @param array $options Specifies an options. Example:
		 * list_products(array(
		 * 'sorting'=>array('name asc', 'price asc')
		 * ))
		 * See the Shop_Manufacturer class description in the documentation for more details.
		 * @return Shop_Product Returns an object of the Shop_Product. 
		 * Call the find_all() method of this object to obtain a list of products (Db_DataCollection object).
		 */
		public function list_products($options = array())
		{
			$sorting = array_key_exists('sorting', $options) ? 
				$options['sorting'] : 
				array('name');

			if (!is_array($sorting))
				$sorting = array('name');
				
			$allowed_sorting_columns = Shop_Product::list_allowed_sort_columns();

			foreach ($sorting as &$sorting_column)
			{
				$test_name = mb_strtolower($sorting_column);
				$test_name = trim(str_replace('desc', '', str_replace('asc', '', $test_name)));
				
				if (!in_array($test_name, $allowed_sorting_columns))
					continue;

				if (strpos($sorting_column, 'price') !== false)
				{
					$sorting_column = str_replace('price', sprintf(Shop_Product::$price_sort_query, Cms_Controller::get_customer_group_id()), $sorting_column);
				}
				if (strpos($sorting_column, '.') === false && strpos($sorting_column, 'rand()') === false)
					$sorting_column = 'shop_products.'.$sorting_column;
			}

			$product_obj = $this->products_list;
			$product_obj->reset_order();
			$product_obj->apply_customer_group_visibility()->apply_catalog_visibility();

			$sort_str = implode(', ', $sorting);

			$product_obj->order($sort_str);

			return $product_obj;
		}
		
		/**
		 * Returns a list of categories of products belonging to the manufacturer
		 * @return Db_DataCollection
		 */
		public function list_categories()
		{
			$obj = new Shop_Category();
			$obj->join('shop_products', 'shop_products.manufacturer_id = '.$this->id);
			$obj->join('shop_products_categories', 'shop_products_categories.shop_product_id=shop_products.id');
			$obj->group('shop_categories.id');
			$obj->order('shop_categories.name');
			$obj->where('shop_products_categories.shop_category_id=shop_categories.id');

			return $obj->find_all();
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>