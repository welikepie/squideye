<?php

	class Shop_CompanyInformation extends Db_ActiveRecord 
	{
		protected $logo_dimensions = array(
			100=>100,
			200=>200,
			300=>300,
			400=>400,
			'auto'=>'Auto'
		);
		
		public $table_name = 'shop_company_information';
		public static $loadedInstance = null;
		
		public $has_many = array(
			'logo'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_CompanyInformation'", 'order'=>'id', 'delete'=>true),
		);

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Company Name')->validation()->fn('trim')->required("Please enter a company name");
			$this->define_column('address_contacts', 'Company Address and Contacts');
			$this->define_multi_relation_column('logo', 'logo', 'Logo', '@name')->invisible();
			$this->define_column('invoice_header_text', 'Invoice Header Text');
			$this->define_column('invoice_footer_text', 'Invoice Footer Text');
			$this->define_column('invoice_template', 'Invoice Template');
			$this->define_column('invoice_date_source', 'Invoice Date');
			$this->define_column('invoice_due_date_interval', 'Due Date Interval')->validation();
			$this->define_column('logo_width', 'Width');
			$this->define_column('logo_height', 'Height');
			$this->define_column('packing_slip_template', 'Packing Slip Template');
			$this->define_column('packing_slip_separate_pages', 'Separate pages');
			$this->define_column('shipping_label_template', 'Shipping Label Template');
			$this->define_column('shipping_label_labels_per_page', 'Shipping Labels Per Page')->validation('Shipping Labels Per Page must contain an integer')->required();
			$this->define_column('shipping_label_print_border', 'Print border');
			
			$this->define_column('shipping_label_width', 'Shipping Label Width');
			$this->define_column('shipping_label_height', 'Shipping Label Height');
			$this->define_column('shipping_label_padding', 'Shipping Label Padding');
			$this->define_column('shipping_label_css_units', 'Shipping Label Units')->validation()->required('Please select units');
			$this->define_column('shipping_label_font_size_factor', 'Shipping Label Font Size Factor');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('name')->tab('Company Information');
			$this->add_form_field('address_contacts')->tab('Company Information')->comment('Company contacts will be displayed on the invoices, packing slips and other printable documents', 'above');

			$this->add_form_field('logo', 'left')->tab('Logo')->renderAs(frm_file_attachments)->renderFilesAs('single_image')->addDocumentLabel('Upload a logo')->noAttachmentsLabel('Logo is not uploaded')->imageThumbSize(250)->noLabel()->fileDownloadBaseUrl(url('ls_backend/files/get/'));
			$this->add_form_field('logo_width', 'right')->tab('Logo')->comment('Width of the logo image, in pixels, on invoices and other printable documents. Select "Auto" for automatic proportional scaling.', 'above')->renderAs(frm_dropdown);
			$this->add_form_field('logo_height', 'right')->tab('Logo')->comment('Height of the logo image, in pixels, on invoices and other printable documents. Select "Auto" for automatic proportional scaling.', 'above')->renderAs(frm_dropdown);

			$this->add_form_field('invoice_template')->tab('Invoices')->renderAs(frm_dropdown);

			$this->add_form_field('invoice_date_source', 'left')->tab('Invoices')->comment('What date you want to use as an invoice date for your orders? You can select a date when the order gets into a specific state.', 'above')->renderAs(frm_dropdown);
			$this->add_form_field('invoice_due_date_interval', 'right')->tab('Invoices')->comment('The Due Date will be calculated as invoice date + number of days specified in this field. Leave this field empty if you do not need a due date in your invoices.', 'above');

			$editor_config = System_HtmlEditorConfig::get('shop', 'shop_printable');
			$field = $this->add_form_field('invoice_header_text')->tab('Invoices')->renderAs(frm_html)->size('small');
			$editor_config->apply_to_form_field($field);

			$field = $this->add_form_field('invoice_footer_text')->tab('Invoices')->renderAs(frm_html)->size('small');
			$editor_config->apply_to_form_field($field);
			
			$this->add_form_field('packing_slip_template')->tab('Packing Slips')->renderAs(frm_dropdown);
			$this->add_form_field('packing_slip_separate_pages')->tab('Packing Slips')->comment('In batch mode print packing slips on separate pages', 'above');
			
			$this->add_form_section('The settings here are used for printing default, shipping method independent shipping labels. They will not affect shipping labels printed with the shipping provider (like USPS).')->tab('Shipping Labels');
			
			$this->add_form_field('shipping_label_template')->tab('Shipping Labels')->renderAs(frm_dropdown);
			$this->add_form_field('shipping_label_labels_per_page')->tab('Shipping Labels');
			$this->add_form_field('shipping_label_print_border')->tab('Shipping Labels')->comment('Select to print a border around the shipping labels', 'above');
			
			$this->add_form_field('shipping_label_width', 'left')->tab('Shipping Labels');
			$this->add_form_field('shipping_label_height', 'right')->tab('Shipping Labels');
			$this->add_form_field('shipping_label_padding', 'left')->tab('Shipping Labels');
			$this->add_form_field('shipping_label_css_units', 'right')->tab('Shipping Labels')->renderAs(frm_dropdown);
			$this->add_form_field('shipping_label_font_size_factor')->tab('Shipping Labels')->comment('You can use this to adjust the size of label text. 1 is 100% of the default size, use 2 for 200% or 0.5 for 50%', 'above');
		}
		
		public function get_shipping_label_css_units_options($key_value = -1)
		{
			$result = array(
				'in' => 'inches (in)',
				'cm' => 'centimeters (cm)',
				'mm' => 'millimetres (mm)',
				'pt' => 'points (pt)',
				'px' => 'pixels (px)'
			);
			return $result;
		}
		
		public function get_invoice_template_options($key_value = -1)
		{
			$templates = $this->list_invoice_templates();
			$result = array();
			foreach ($templates as $template_id=>$template)
				$result[$template_id] = isset($template['name']) ? $template['name'] : 'Unknown template';
				
			return $result;
		}
		
		public function get_shipping_label_template_options($key_value = -1)
		{
			$templates = $this->list_shipping_label_templates();
			$result = array();
			foreach ($templates as $template_id=>$template)
				$result[$template_id] = isset($template['name']) ? $template['name'] : 'Unknown template';
			
			return $result;
		}
		
		public function get_packing_slip_template_options($key_value = -1)
		{
			$templates = $this->list_packing_slip_templates();
			$result = array();
			foreach ($templates as $template_id=>$template)
				$result[$template_id] = isset($template['name']) ? $template['name'] : 'Unknown template';
				
			return $result;
		}
		
		public function get_invoice_date_source_options($key_value = -1)
		{
			$result = array();
			$result['order_date'] = 'Order date';
			$result['print_date'] = 'Invoice printing date';
			
			$statues = Shop_OrderStatus::list_all_statuses();
			foreach ($statues as $status)
				$result['status:'.$status->id] = 'Order status: '.$status->name;
			
			return $result;
		}

		public function get_logo_width_options($key_value = -1)
		{
			return $this->logo_dimensions;
		}
		
		public function get_logo_height_options($key_value = -1)
		{
			return $this->logo_dimensions;
		}
		
		public static function list_invoice_templates()
		{
			$result = array();
			
			$template_path = PATH_APP."/modules/shop/invoice_templates";
			$iterator = new DirectoryIterator( $template_path );
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$dirPath = $template_path."/".$dir->getFilename();
					$template_id = $dir->getFilename();

					$infoPath = $dirPath."/"."info.php";

					if (!file_exists($infoPath))
						continue;

					include($infoPath);
					$template_info['template_id'] = $template_id;
					$result[$template_id] = $template_info;
				}
			}

			return $result;
		}
		
		public static function list_shipping_label_templates()
		{
			$result = array();
			
			$template_path = PATH_APP."/modules/shop/shippinglabel_templates";
			$iterator = new DirectoryIterator( $template_path );
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$dirPath = $template_path."/".$dir->getFilename();
					$template_id = $dir->getFilename();

					$infoPath = $dirPath."/"."info.php";

					if (!file_exists($infoPath))
						continue;

					include($infoPath);
					$template_info['template_id'] = $template_id;
					$result[$template_id] = $template_info;
				}
			}
			return $result;
		}
		
		public static function list_packing_slip_templates()
		{
			$result = array();
			
			$template_path = PATH_APP."/modules/shop/packingslip_templates";
			$iterator = new DirectoryIterator( $template_path );
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$dirPath = $template_path."/".$dir->getFilename();
					$template_id = $dir->getFilename();

					$infoPath = $dirPath."/"."info.php";

					if (!file_exists($infoPath))
						continue;

					include($infoPath);
					$template_info['template_id'] = $template_id;
					$result[$template_id] = $template_info;
				}
			}

			return $result;
		}

		public function get_custom_logo_url($width = 100, $height = 'auto')
		{
			if (!$this->logo->count)
				return null;

			return $this->logo[0]->getThumbnailPath($width, $height, false);
		}

		public function get_logo_url()
		{
			if (!$this->logo->count)
				return null;

			return $this->logo[0]->getThumbnailPath($this->logo_width, $this->logo_height, false);
		}
		
		public function get_invoice_template()
		{
			$template_id = $this->invoice_template;
			$templates = $this->list_invoice_templates();

			if (!array_key_exists($template_id, $templates))
				throw new Phpr_ApplicationException('Invoice template '.$template_id.' not found. Please select existing invoice template on the System/Settings/Company Information and Settings page.');

			return $templates[$template_id];
		}
		
		public function get_packing_slip_template()
		{
			$template_id = $this->packing_slip_template;
			$templates = $this->list_packing_slip_templates();

			if (!array_key_exists($template_id, $templates))
				throw new Phpr_ApplicationException('Packing slip template '.$template_id.' not found. Please select existing packing slip template on the System/Settings/Company Information and Settings page.');

			return $templates[$template_id];
		}
		
		public function get_shipping_label_template()
		{
			$template_id = $this->shipping_label_template;
			$templates = $this->list_shipping_label_templates();

			if (!array_key_exists($template_id, $templates))
				throw new Phpr_ApplicationException('Shipping label template '.$template_id.' not found. Please select existing shipping label template on the System/Settings/Company Information and Settings page.');

			return $templates[$template_id];
		}
		
		public function get_invoice_due_date($invoice_date)
		{
			if (!$invoice_date)
				return null;

			if ($this->invoice_due_date_interval <= 0)
				return $invoice_date;
				
			return $invoice_date->addInterval(new Phpr_DateTimeInterval($this->invoice_due_date_interval));
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
	}

?>