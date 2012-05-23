<?

	class Shop_PaymentMethod extends Db_ActiveRecord
	{
		public $table_name = 'shop_payment_methods';
		public $enabled = 1;
		public $backend_enabled = 1;
		public $order;

		protected $payment_type_obj = null;
		protected $added_fields = array();
		protected $hidden_fields = array();
		protected $form_context = null;

		public $custom_columns = array('payment_type_name'=>db_text, 'receipt_page'=>db_text);
		public $encrypted_columns = array('config_data');
		
		public $fetched_data = array();
		
		protected $form_fields_defined = false;
		protected static $cache = array();

		public $has_and_belongs_to_many = array(
			'countries'=>array('class_name'=>'Shop_Country', 'join_table'=>'shop_paymentmethods_countries', 'order'=>'name')
		);
		
		public $belongs_to = array(
			'receipt_page_link'=>array('class_name'=>'Cms_Page', 'foreign_key'=>'receipt_page_id')
		);

		public static function create()
		{
			return new self();
		}
		
		public static function find_by_api_code($code)
		{
			$code = mb_strtolower($code);
			return self::create()->where('ls_api_code=?', $code)->find();
		}

		public static function find_by_id($id)
		{
			if (!array_key_exists($id, self::$cache))
				self::$cache[$id] = self::create()->find($id);
				
			return self::$cache[$id];
		}

		public static function list_applicable($country_id, $amount, $backend_only = false)
		{
			if($backend_only)
				$backend_where = 'backend_enabled = 1';
			else
				$backend_where = 'enabled = 1';
			$methods = self::create()->order('shop_payment_methods.name')->where($backend_where)->where('(select count(*) from shop_paymentmethods_countries where shop_country_id=? and shop_paymentmethods_countries.shop_payment_method_id=shop_payment_methods.id) > 0 or (select count(*) from shop_paymentmethods_countries where shop_paymentmethods_countries.shop_payment_method_id=shop_payment_methods.id) = 0', $country_id)->find_all();
			
			$result = array();
			foreach ($methods as $method)
			{
				$method->define_form_fields();
				if ($method->get_paymenttype_object()->is_applicable($amount, $method))
					$result[] = $method;
			}
			
			return new Db_DataCollection($result);
		}

		public static function page_deletion_check($page)
		{
			$methods = self::create()->find_all();
			
			foreach ($methods as $method)
			{
				$method->define_form_fields();
				$method->get_paymenttype_object()->page_deletion_check($method, $page);
			}
		}

		public static function order_status_deletion_check($status)
		{
			$methods = self::create()->find_all();
			
			foreach ($methods as $method)
			{
				$method->define_form_fields();
				$method->get_paymenttype_object()->status_deletion_check($method, $status);
			}
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required('Please specify the payment method name.');
			$this->define_column('payment_type_name', 'Payment Gateway');
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('enabled', 'Enabled on the front-end website');
			$this->define_column('backend_enabled', 'Enabled in the Administration Area');
			$this->define_column('ls_api_code', 'LemonStand API Code')->defaultInvisible()->validation()->fn('trim')->fn('mb_strtolower')->unique('Payment method with the specified LemonStand API code already exists.');
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if (!$front_end)
			{
				$this->define_relation_column('receipt_page_link', 'receipt_page_link', 'Receipt Page ', db_varchar, 'concat(@title, \' [\', @url, \']\')')->validation();
				$this->define_multi_relation_column('countries', 'countries', 'Countries', '@name')->defaultInvisible();
			}
		}

		public function define_form_fields($context = null)
		{
			if ($this->form_fields_defined)
				return false;

			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
				
			$this->form_fields_defined = true;
			
			$this->form_context = $context;
			
			if ($context != 'backend_payment_form')
			{
				$this->add_form_field('enabled')->tab('General Parameters');
				$backend_enabled = $this->add_form_field('backend_enabled')->tab('General Parameters');
				if($this->enabled)
					$backend_enabled->disabled();
				$this->add_form_field('name')->comment('Name of the payment method. It will be displayed on the front-end website.', 'above')->tab('General Parameters');
				$this->add_form_field('description')->comment('If provided, it will be displayed on the front-end website.', 'above')->tab('General Parameters')->size('small');

				$obj = $this->get_paymenttype_object();
				$method_info = $obj->get_info();
				
				$has_receipt_page = array_key_exists('has_receipt_page', $method_info) ? $method_info['has_receipt_page'] : true;
				if ($has_receipt_page && !$front_end)
					$this->add_form_field('receipt_page_link')->comment('Page to which the customer’s browser is redirected after successful payment.', 'above')->tab('General Parameters')->previewNoRelation()->optionsHtmlEncode(false);
			
				$this->add_form_field('ls_api_code')->comment('You can use the API Code for identifying the payment method in the API calls.', 'above')->tab('General Parameters');

				if (!$front_end)
					$this->add_form_field('countries')->tab('Countries')->comment('Countries the payment method is applicable to. Uncheck all countries to make the payment method applicable to any country.', 'above')->referenceSort('name');

				$this->get_paymenttype_object()->build_config_ui($this, $context);

				if (!$this->is_new_record())
					$this->load_xml_data();
				else
					$this->get_paymenttype_object()->init_config_data($this);
			} else
			{
				$this->load_xml_data();
				$this->add_form_partial(PATH_APP.'/modules/shop/controllers/shop_orders/_pay_hidden_fields.htm')->tab('Payment Information');
				$this->get_paymenttype_object()->build_payment_form($this, $context);
			}
		}
		
		public function get_receipt_page_options($key_value=-1)
		{
			return Cms_Page::create()->get_page_tree_options($key_value);
		}
		
		public function get_countries_options($key_value=1)
		{
			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 order by name');
			$result = array();
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		/**
		 * Throws validation exception on a specified field
		 * @param $field Specifies a field code (previously added with add_field method)
		 * @param $message Specifies an error message text
		 * @param $grid_row Specifies an index of grid row, for grid controls
		 * @param $grid_column Specifies a name of column, for grid controls
		 */
		public function field_error($field, $message, $grid_row = null, $grid_column = null)
		{
			if ($grid_row != null)
			{
				$rule = $this->validation->getRule($field);
				if ($rule)
					$rule->focusId($field.'_'.$grid_row.'_'.$grid_column);
			}
			
			$this->validation->setError($message, $field, true);
		}
		
		public function before_save($deferred_session_key = null)
		{
			if ($this->enabled)
				$this->backend_enabled = 1;
			
			$this->get_paymenttype_object()->validate_config_on_save($this);
			
			$document = new SimpleXMLElement('<payment_type_settings></payment_type_settings>');
			foreach ($this->added_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', base64_encode(serialize($this->$code)));
			}
			
			foreach ($this->hidden_fields as $code=>$form_field)
			{
				$field_element = $document->addChild('field');
				$field_element->addChild('id', $code);
				$field_element->addChild('value', serialize($this->$code));
			}

			$this->config_data = $document->asXML();
		}

		public function add_field($code, $title, $side = 'full', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $title)->validation();

			$form_field = $this->add_form_field($code, $side)->optionsMethod('get_added_field_options');
			if ($this->form_context != 'backend_payment_form')
				$form_field->tab('Configuration');
			else
				$form_field->tab('Payment Information');
			
			$this->added_fields[$code] = $form_field;
			
			return $form_field;
		}
		
		public function add_hidden_field($code, $value = '', $type = db_text)
		{
			$this->custom_columns[$code] = $type;
			$this->_columns_def = null;
			$this->define_column($code, $code);
			$this->hidden_fields[$code] = $code;
			//$this->$code = $value;
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$obj = $this->get_paymenttype_object();
			$method_name = "get_{$db_name}_options";
			if (!method_exists($obj, $method_name))
				throw new Phpr_SystemException("Method {$method_name} is not defined in {$this->class_name} class.");

			return $obj->$method_name($current_key_value);
		}
		
		public function get_paymenttype_object()
		{
			if ($this->payment_type_obj !== null)
			 	return $this->payment_type_obj;
			
			$payment_types = Core_ModuleManager::findById('shop')->listPaymentTypes();
			foreach ($payment_types as $class_name)
			{
				if ($this->class_name == $class_name)
					return $this->payment_type_obj = new $class_name();
			}
			
			throw new Phpr_ApplicationException("Class {$this->class_name} not found.");
		}
		
		public function eval_payment_type_name()
		{
			$obj = $this->get_paymenttype_object();
			$info = $obj->get_info();
			if (array_key_exists('name', $info))
				return $info['name'];
			
			return null;
		}
		
		public function eval_receipt_page()
		{
			$page_info = Cms_PageReference::get_page_info($this, 'receipt_page_id', null);
			if (!$page_info)
				return $this->receipt_page_link;
			
			if (is_object($page_info))
				return Cms_Page::create()->find($page_info->page_id);
				
			return null;
		}
		
		public function render_payment_form($controller)
		{
			$obj = $this->get_paymenttype_object();
			if ($obj)
			{
				$obj->before_render_payment_form($this);

				$class = get_class($obj);
				$pos = strpos($class, '_');
				$payment_type_file = strtolower(substr($class, $pos+1, -8));
				$partial_name = 'payment:'.$payment_type_file;

				if (Cms_Partial::create()->find_by_name($partial_name))
					$controller->render_partial($partial_name);
			}
		}
		
		public function render_payment_profile_form($controller)
		{
			$obj = $this->get_paymenttype_object();
			if ($obj)
			{
				$obj->before_render_payment_profile_form($this);

				$class = get_class($obj);
				$pos = strpos($class, '_');
				$payment_type_file = strtolower(substr($class, $pos+1, -8));
				$partial_name = 'payment_profile:'.$payment_type_file;

				if (Cms_Partial::create()->find_by_name($partial_name))
					$controller->render_partial($partial_name);
			}
		}

		public function before_delete($id=null) 
		{
			$bind = array(
				'id'=>$this->id
			);

			$count = Db_DbHelper::scalar('select count(*) from shop_orders where payment_method_id=:id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete this payment method because there are orders referring to it.');

			$count = Db_DbHelper::scalar('select count(*) from shop_payment_transactions where payment_method_id=:id', $bind);
			if ($count)
				throw new Phpr_ApplicationException('Cannot delete this payment method because there are transactions referring to it.');
		}
		
		/**
		 * This method returns true for non-offline payment types
		 */
		public function has_payment_form()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->has_payment_form();
		}
		
		public function pay_offline_message()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->pay_offline_message();
		}

		protected function load_xml_data()
		{
			if (!strlen($this->config_data))
				return;

			$object = new SimpleXMLElement($this->config_data);
			foreach ($object->children() as $child)
			{
				$code = $child->id;
				$value = base64_decode($child->value, true);
				$this->$code = unserialize($value !== false ? $value : $child->value);

				$code_array = (array)$code;
				$this->fetched_data[$code_array[0]] = $this->$code;
			}

			$this->get_paymenttype_object()->validate_config_on_load($this);
		}

		public function after_modify($operation, $deferred_session_key)
		{
			Shop_Module::update_catalog_version();
		}
		
		public function get_partial_path($partial_name = null)
		{
			$class_name = get_class($this->get_paymenttype_object());
			$classInfo = new ReflectionClass($class_name);
			return dirname($classInfo->getFileName()).'/'.strtolower($class_name).'/'.$partial_name;
		}
		
		/*
		 * Transaction management functions
		 */
		
		/**
		 * This method returns TRUE if the payment gateway supports requesting a status of a specific transaction
		 */
		public function supports_transaction_status_query()
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return false;

			return $obj->supports_transaction_status_query();
		}
		
		/**
		 * Returns a list of available transitions from a specific transaction status
		 * The method returns an associative array with keys corresponding transaction statuses 
		 * and values corresponding transaction status actions: array('V'=>'Void', 'S'=>'Submit for settlement')
		 * @param string $transaction_id Gateway-specific transaction identifier
		 * @param string $transaction_code Gateway-specific transaction status code
		 */
		public function list_available_transaction_transitions($transaction_id, $transaction_status_code)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return array();
				
			$this->define_form_fields();

			$result = $obj->list_available_transaction_transitions($this, $transaction_id, $transaction_status_code);
			if (!is_array($result))
				$result = array();

			return $result;
		}

		/**
		 * Contacts the payment gateway and sets specific status on a specific transaction
		 * @param Shop_Order $order LemonStand order object the transaction is bound to
		 * @param string $transaction_id Gateway-specific transaction identifier
		 * @param string $transaction_code Current gateway-specific transaction status code
		 * @param string $new_transaction_code Destination gateway-specific transaction status code
		 */
		public function set_transaction_status($order, $transaction_id, $transaction_status_code, $new_transaction_status_code)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return null;
				
			return $obj->set_transaction_status($this, $order, $transaction_id, $transaction_status_code, $new_transaction_status_code);
		}
		
		/**
		 * Returns status of a specific transaction
		 */
		public function request_transaction_status($transaction_id)
		{
			$obj = $this->get_paymenttype_object();
			if (!$obj)
				return null;
				
			return $obj->request_transaction_status($this, $transaction_id);
		}
		
		public static function create_partials()
		{
			$partial_list = Db_DbHelper::objectArray('select name, theme_id from partials');
			$partials = array();
			foreach ($partial_list as $partial)
			{
				if (!$partial->theme_id)
					$partial->theme_id = 0;
					
				if (!array_key_exists($partial->theme_id, $partials))
					$partials[$partial->theme_id] = array();
					
				$partials[$partial->theme_id][$partial->name] = $partial;
			}

			$payment_methods = self::create()->find_all();

			foreach ($payment_methods as $payment_method)
			{
				$class = $payment_method->class_name;

				if (preg_match('/_Payment$/i', $class) && get_parent_class($class) == 'Shop_PaymentType')
				{
					$pos = strpos($class, '_');
					$payment_type_file = strtolower(substr($class, $pos+1, -8));
					$payment_partial_name = 'payment:'.$payment_type_file;
					$payment_profile_partial_name = 'payment_profile:'.$payment_type_file;
					$classInfo = null;
					
					foreach ($partials as $theme_id=>$partial_list)
					{
						$theme = Cms_Theme::get_theme_by_id($theme_id);
						$extension = 'htm';
						if ($theme)
						{
							if ($theme->templating_engine == 'twig')
								$extension = 'twig';
						}
						else {
							if (Cms_SettingsManager::get()->default_templating_engine == 'twig')
								$extension = 'twig';
						}

						$payment_partial_exists = array_key_exists($payment_partial_name, $partial_list);
						$payment_profile_partial_exists = array_key_exists($payment_profile_partial_name, $partial_list);

						if (!$payment_partial_exists || !$payment_profile_partial_exists)
						{
							$classInfo = $classInfo ? $classInfo : new ReflectionClass($class);

							if (!$payment_partial_exists)
							{
								$file_path = dirname($classInfo->getFileName()).'/'.strtolower($class).'/front_end_partial.'.$extension;
								self::create_partial_from_file($payment_partial_name, "Payment form partial", $file_path, $theme_id);
							}

							if (!$payment_profile_partial_exists)
							{
								$file_path = dirname($classInfo->getFileName()).'/'.strtolower($class).'/payment_profile_partial.'.$extension;
								self::create_partial_from_file($payment_profile_partial_name, "Payment profile partial", $file_path, $theme_id);
							}
						}
					}
				}
			}
		}
		
		protected static function create_partial_from_file($name, $description, $file_path, $theme_id)
		{
			if (file_exists($file_path))
			{
				if ($theme_id == 0)
					$theme_id = null;

				$partial = Cms_Partial::create();
				$partial->name = $name;
				$partial->theme_id = $theme_id;
				$partial->description = $description;
				$partial->html_code = file_get_contents($file_path);
				$partial->save();
			}
		}
		
		/*
		 * Customer payment profiles support
		 */

		/**
		 * Finds and returns a customer payment profile for this payment method
		 * @param Shop_Customer $customer A customer object to find a profile for
		 * @return Shop_CustomerPaymentProfile Returns the customer profile object or NULL
		 */
		public function find_customer_profile($customer)
		{
			if (!$customer)
				return null;
			
			return Shop_CustomerPaymentProfile::create()->where('customer_id=?', $customer->id)->where('payment_method_id=?', $this->id)->find();
		}
		
		/**
		 * Initializes a new customer payment profile object
		 * @return Shop_CustomerPaymentProfile Returns the customer profile object
		 */
		public function init_customer_profile($customer)
		{
			$obj = Shop_CustomerPaymentProfile::create();
			$obj->customer_id = $customer->id;
			$obj->payment_method_id = $this->id;

			return $obj;
		}
		
		/**
		 * Returns TRUE if a customer profile for this payment method and a given customer exists.
		 * @param Shop_Customer $customer A customer object to find a profile for
		 * @return boolean
		 */
		public function profle_exists($customer)
		{
			return $this->find_customer_profile($customer) ? true : false;
		}
		
		/**
		 * Return TRUE if the payment module supports customer payment profiles.
		 */
		public function supports_payment_profiles()
		{
			return $this->get_paymenttype_object()->supports_payment_profiles();
		}
		
		/**
		 * Deletes a customer payment profile
		 * @param Shop_Customer $customer A customer object which payment profile should be deleted
		 */
		public function delete_customer_profile($customer)
		{
			$payment_method_obj = $this->get_paymenttype_object();
			
			$profile = $this->find_customer_profile($customer);
			if (!$profile)
				throw new Phpr_ApplicationException('Customer profile not found');
			
			$payment_method_obj->delete_customer_profile($this, $customer, $profile);
			
			$profile->delete();
		}
	}

?>