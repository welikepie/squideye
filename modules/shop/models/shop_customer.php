<?php

	class Shop_Customer extends Db_ActiveRecord
	{
		public $table_name = 'shop_customers';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		public $auto_footprints_default_invisible = true;

		public $custom_columns = array('password_confirm'=>db_varchar, 'full_name'=>db_varchar);

		public $belongs_to = array(
			'shipping_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'shipping_country_id'),
			'billing_country'=>array('class_name'=>'Shop_Country', 'foreign_key'=>'billing_country_id'),
			
			'shipping_state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'shipping_state_id'),
			'billing_state'=>array('class_name'=>'Shop_CountryState', 'foreign_key'=>'billing_state_id'),
			
			'group'=>array('class_name'=>'Shop_CustomerGroup', 'foreign_key'=>'customer_group_id'),
		);
		
		public $has_many = array(
			'orders'=>array('class_name'=>'Shop_Order', 'foreign_key'=>'customer_id', 'order'=>'order_datetime desc'),
    		'image'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Shop_Customer' and field='image'", 'order'=>'sort_order, id', 'delete'=>true)
		);
		
		protected $plain_password;
		protected $product_quantity_cache = array();
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('first_name', 'First Name')->order('asc')->validation()->fn('trim')->required("Please specify a first name");
			$this->define_column('last_name', 'Last Name')->validation()->fn('trim')->required("Please specify a last name");
			$this->define_column('email', 'Email')->validation()->fn('trim')->fn('mb_strtolower')->required()->Email('Please provide valid email address.')->method('validate_email');
			$this->define_column('company', 'Company')->validation()->fn('trim');
			$this->define_column('phone', 'Phone')->validation()->fn('trim');
			$this->define_column('password', 'Password')->invisible()->validation()->fn('trim');
			$this->define_column('password_confirm', 'Password Confirmation')->invisible()->validation()->matches('password', 'Password and confirmation password do not match.');
			$this->define_column('guest', 'Guest');
			
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->define_relation_column('group', 'group', 'Customer Group ', db_varchar, $front_end ? null : '@name');
			$this->define_relation_column('billing_country', 'billing_country', 'Country ', db_varchar, $front_end ? null : '@name')->listTitle('Bl. Country')->defaultInvisible();
			$this->define_relation_column('billing_state', 'billing_state', 'State ', db_varchar, $front_end ? null : '@name')->listTitle('Bl. State')->defaultInvisible();
			
			$this->define_column('billing_street_addr', 'Street Address')->listTitle('Bl. Address')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('billing_city', 'City')->listTitle('Bl. City')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('billing_zip', 'Zip/Postal Code')->listTitle('Bl. Zip/Postal Code')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('shipping_first_name', 'First Name')->listTitle('Sh. First Name')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_last_name', 'Last Name')->listTitle('Sh. Last Name')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_company', 'Company')->listTitle('Sh. Company')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_phone', 'Phone')->listTitle('Sh. Phone')->defaultInvisible()->validation()->fn('trim');

			$this->define_relation_column('shipping_country', 'shipping_country', 'Country ', db_varchar, $front_end ? null : '@name')->defaultInvisible()->listTitle('Sh. Country');
			$this->define_relation_column('shipping_state', 'shipping_state', 'State ', db_varchar, $front_end ? null : '@name')->listTitle('Sh. State')->defaultInvisible();

			$this->define_column('shipping_street_addr', 'Street Address')->listTitle('Sh. Address')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_city', 'City')->listTitle('Sh. City')->defaultInvisible()->validation()->fn('trim');
			$this->define_column('shipping_zip', 'Zip/Postal Code')->listTitle('Sh. Zip/Postal Code')->defaultInvisible()->validation()->fn('trim');

			$this->define_column('shipping_addr_is_business', 'Business address')->invisible();

			$this->define_column('deleted_at', 'Deleted')->defaultInvisible()->dateFormat('%x %H:%M');
			$this->define_column('notes', 'Notes')->listTitle('Notes')->defaultInvisible();

			$this->defined_column_list = array();
			Backend::$events->fireEvent('shop:onExtendCustomerModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}

		public function define_form_fields($context = null)
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';

			$this->add_form_field('first_name', 'left')->tab('Customer');
			$this->add_form_field('last_name', 'right')->tab('Customer');
			$this->add_form_field('email')->tab('Customer');
			$this->add_form_field('company', 'left')->tab('Customer');
			$this->add_form_field('phone', 'right')->tab('Customer');
			$this->add_form_field('password', 'left')->tab('Customer')->renderAs(frm_password)->noPreview();
			$this->add_form_field('password_confirm', 'right')->tab('Customer')->renderAs(frm_password)->noPreview();

			if (!$this->guest && !$front_end)
				$this->add_form_field('group')->tab('Customer');

			if (!$front_end)
			{
				$country_field = $this->add_form_field('billing_country', 'left')->tab('Billing Address');
				if ($context != 'preview')
					$country_field->renderAs('country');

				$this->add_form_field('billing_state', 'right')->tab('Billing Address');
			}
			
			$this->add_form_field('billing_street_addr')->tab('Billing Address')->nl2br(true)->renderAs(frm_textarea)->size('small');
			$this->add_form_field('billing_city', 'left')->tab('Billing Address');
			$this->add_form_field('billing_zip', 'right')->tab('Billing Address');

			if ($context != 'preview')
				$this->add_form_custom_area('copy_shipping_address')->tab('Shipping Address');

			$this->add_form_field('shipping_first_name', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_last_name', 'right')->tab('Shipping Address');

			$this->add_form_field('shipping_company', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_phone', 'right')->tab('Shipping Address');
			
			if ($context != 'preview' || $this->shipping_addr_is_business)
				$this->add_form_field('shipping_addr_is_business')->tab('Shipping Address');

			if (!$front_end)
			{
				$country_field = $this->add_form_field('shipping_country', 'left')->tab('Shipping Address');
				if ($context != 'preview')
					$country_field->renderAs('country');

				$this->add_form_field('shipping_state', 'right')->tab('Shipping Address');
			}

			$this->add_form_field('shipping_street_addr')->tab('Shipping Address')->nl2br(true)->renderAs(frm_textarea)->size('small');
			$this->add_form_field('shipping_city', 'left')->tab('Shipping Address');
			$this->add_form_field('shipping_zip', 'right')->tab('Shipping Address');
			
			if (!$front_end && $context != 'preview')
				$this->add_form_field('notes')->tab('Notes')->noLabel();
			else if (!$front_end)
				$this->add_form_field('notes')->tab('Customer');
			
			Backend::$events->fireEvent('shop:onExtendCustomerForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('shop:onGetCustomerFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function validate_email($name, $value)
		{
			if ($this->guest)
				return true;

			$value = trim(strtolower($value));
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('email=?', $value);
			if ($this->id)
				$customer->where('id <> ?', $this->id);

			$customer = $customer->find();

			if ($customer)
				$this->validation->setError("Email ".$value." is already in use. Please specify another email address.", $name, true);
			
			return true;
		}
		
		public static function find_registered_by_email($email)
		{
			$value = trim(strtolower($email));
			$customer = self::create()->where('(shop_customers.guest <> 1 or shop_customers.guest is null)')->where('email=?', $value);

			return $customer->find();
		}
		
		public function get_group_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CustomerGroup::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$groups = Shop_CustomerGroup::create()->where('(code is null or code<>?)', Shop_CustomerGroup::guest_group)->order('name')->find_all();
			return $groups->as_array('name', 'id');
		}
		
		public function get_shipping_country_options($key_value=-1)
		{
			return $this->list_countries($key_value, $this->shipping_country_id);
		}
		
		public function get_billing_country_options($key_value=-1)
		{
			return $this->list_countries($key_value, $this->billing_country_id);
		}
		
		protected function list_countries($key_value=-1, $default = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_Country::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			$records = Db_DbHelper::objectArray('select * from shop_countries where enabled_in_backend=1 or id=:id order by name', array('id'=>$default));
			$result = array(null=>'<please select>');
			foreach ($records as $country)
				$result[$country->id] = $country->name;

			return $result;
		}
		
		public function get_shipping_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;

				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}
			
			return $this->list_states($this->shipping_country_id);
		}
		
		public function get_billing_state_options($key_value = -1)
		{
			if ($key_value != -1)
			{
				if (!strlen($key_value))
					return null;
					
				$obj = Shop_CountryState::create()->find($key_value);
				return $obj ? $obj->name : null;
			}

			return $this->list_states($this->billing_country_id);
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
			
			if (!count($states))
				$result = array(null=>'<no states available>');

			foreach ($states as $state)
				$result[$state->id] = $state->name;
				
			return $result;
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->plain_password = $this->password;

			if (!$this->guest)
			{
				if (!strlen($this->password))
				{
					if ($this->is_new_record())
						$this->validation->setError('Please provide a password.', 'password', true);
					else
						$this->password = $this->fetched['password'];
				} else
					$this->password = Phpr_SecurityFramework::create()->salted_hash($this->password);
			}
		}

		public function __get($name)
		{
			if ($name == 'name')
				return $this->first_name.' '.$this->last_name;
			
			return parent::__get($name);
		}

		public function copy_to_order($order)
		{
			$order->billing_first_name = $this->first_name;
			$order->billing_last_name = $this->last_name;
			$order->billing_email = $this->email;
			$order->billing_phone = $this->phone;
			$order->billing_company = $this->company;
			$order->billing_street_addr = $this->billing_street_addr;
			$order->billing_city = $this->billing_city;
			$order->billing_state_id = $this->billing_state_id;
			$order->billing_zip = $this->billing_zip;
			$order->billing_country_id = $this->billing_country_id;
			
			$order->shipping_first_name = $this->shipping_first_name;
			$order->shipping_last_name = $this->shipping_last_name;
			$order->shipping_phone = $this->shipping_phone;
			$order->shipping_company = $this->shipping_company;
			$order->shipping_street_addr = $this->shipping_street_addr;
			$order->shipping_city = $this->shipping_city;
			$order->shipping_state_id = $this->shipping_state_id;
			$order->shipping_zip = $this->shipping_zip;
			$order->shipping_country_id = $this->shipping_country_id;
			$order->shipping_addr_is_business = $this->shipping_addr_is_business;
		}
		
		public function copy_from_order($order)
		{
			$this->first_name = $order->billing_first_name;
			$this->last_name = $order->billing_last_name;
			$this->email = $order->billing_email;
			$this->phone = $order->billing_phone;
			$this->company = $order->billing_company;
			$this->billing_street_addr = $order->billing_street_addr;
			$this->billing_city = $order->billing_city;
			$this->billing_state_id = $order->billing_state_id;
			$this->billing_zip = $order->billing_zip;
			$this->billing_country_id = $order->billing_country_id;
			
			$this->shipping_first_name = $order->shipping_first_name;
			$this->shipping_last_name = $order->shipping_last_name;
			$this->shipping_phone = $order->shipping_phone;
			$this->shipping_company = $order->shipping_company;
			$this->shipping_street_addr = $order->shipping_street_addr;
			$this->shipping_city = $order->shipping_city;
			$this->shipping_state_id = $order->shipping_state_id;
			$this->shipping_zip = $order->shipping_zip;
			$this->shipping_country_id = $order->shipping_country_id;
			$this->shipping_addr_is_business = $order->shipping_addr_is_business;
		}

		public function delete_customer()
		{
			if ($this->orders->count)
			{
				if ($this->deleted_at)
					return false;
				
				$this->deleted_at = Phpr_DateTime::now();
				Db_DbHelper::query(
					'update shop_customers set deleted_at=:deleted_at where id=:id', array(
						'deleted_at'=>$this->deleted_at,
						'id'=>$this->id
					)
				);
				
				return false;
			}
			
			$this->delete();
			return true;
		}
		
		public function restore_customer()
		{
			$this->deleted_at = null;

			Db_DbHelper::query(
				'update shop_customers set deleted_at=:deleted_at where id=:id', array(
					'deleted_at'=>$this->deleted_at,
					'id'=>$this->id
				)
			);
		}

		public function before_delete($id=null) 
		{
			if ($order_num = $this->orders->count)
				throw new Phpr_ApplicationException("Error deleting customer. There are $order_num order(s) belonging to this customer.");
		}

		public function generate_password()
		{
			$letters = 'abcdefghijklmnopqrstuvwxyz';
			$password = null;
			for ($i = 1; $i <= 6; $i++)
				$password .= $letters[rand(0,25)];
				
			$this->password_confirm = $password;
			$this->password = $password;
		}

		public static function reset_password($email)
		{
			$customer = self::create()->where('(guest <> 1 or guest is null)')->where('email=?', $email)->find(null, array(), 'front_end');
			if (!$customer)
				throw new Phpr_ApplicationException('Customer with specified email is not found.');
				
			$customer->generate_password();
			$customer->save();

			$template = System_EmailTemplate::create()->find_by_code('shop:password_reset');
			if ($template)
			{
				$message = $customer->set_customer_email_vars($template->content);
				$template->send_to_customer($customer, $message);
			}
		}

		/**
		 * Returns quantity of the item previously purchased by the customer.
		 * The function calculates only paid orders.
		 * @param Shop_Product $product A product object to return a quantity for
		 * @return int
		 */
		public function get_purchased_item_quantity($product)
		{
			if (array_key_exists($product->id, $this->product_quantity_cache))
				return $this->product_quantity_cache[$product->id];
			
			return $this->product_quantity_cache[$product->id] = Db_DbHelper::scalar('select sum(quantity) from shop_order_items, shop_orders, shop_order_status_log_records, shop_order_statuses 
			where 
			shop_order_items.shop_order_id=shop_orders.id
			and shop_order_statuses.id=shop_order_status_log_records.status_id 
			and shop_order_status_log_records.order_id=shop_orders.id
			and shop_order_statuses.code=:paid_status
			and shop_order_items.shop_product_id=:product_id
			and shop_orders.customer_id=:customer_id', array(
				'paid_status'=>Shop_OrderStatus::status_paid,
				'product_id'=>$product->id,
				'customer_id'=>$this->id
			));
		}

		/**
		 * Sets values for common customer email template variables
		 * @param string $message_text Specifies a message text to substitute variables in
		 * @return string
		 */
		public function set_customer_email_vars($message_text)
		{
			$message_text = str_replace('{customer_name}', h($this->name), $message_text);
			$message_text = str_replace('{customer_first_name}', h($this->first_name), $message_text);
			$message_text = str_replace('{customer_last_name}', h($this->last_name), $message_text);
			$message_text = str_replace('{customer_email}', $this->email, $message_text);
			$message_text = str_replace('{customer_password}', h($this->plain_password), $message_text);
			
			$email_scope_vars = array('customer'=>$this);
			$message_text = System_CompoundEmailVar::apply_scope_variables($message_text, 'shop:customer', $email_scope_vars);
			
			return $message_text;
		}
		
		public function send_registration_confirmation()
		{
			$template = System_EmailTemplate::create()->find_by_code('shop:registration_confirmation');
			if ($template)
			{
				$message = $this->set_customer_email_vars($template->content);
				$template->send_to_customer($this, $message);
			}
		}
		
		public function convert_to_registered($send_notification, $group_id)
		{
			if (Shop_Customer::find_registered_by_email($this->email))
				throw new Phpr_ApplicationException("Registered customer with email {$obj->email} already exists.");

			if ($send_notification)
				$this->generate_password();
			else
				$this->password = null;

			$this->customer_group_id = $group_id;
			$this->guest = 0;
			$this->save();
			
			if ($send_notification)
				$this->send_registration_confirmation();
		}
		
		public function before_create($deferred_session_key = null)
		{
			if ($this->guest)
			{
				$group = Shop_CustomerGroup::create()->find_by_code(Shop_CustomerGroup::guest_group);
				if ($group)
					$this->customer_group_id = $group->id;
			} else
			{
				if (!$this->customer_group_id)
				{
					$group = Shop_CustomerGroup::create()->find_by_code(Shop_CustomerGroup::registered_group);
					if ($group)
						$this->customer_group_id = $group->id;
				}
			}
		}
		
		public function after_delete()
		{
			Backend::$events->fireEvent('shop:onCustomerAfterDelete', $this);
		}

		public function set_api_fields($fields)
		{
			if (!is_array($fields))
				return;

			foreach ($fields as $field=>$value)
			{
				if (in_array($field, $this->api_added_columns))
					$this->$field = $value;
			}
		}

		public function get_display_name()
		{
			return $this->first_name.' '.$this->last_name;
		}
		
		public function eval_full_name()
		{
			return $this->first_name.' '.$this->last_name;
		}
		
		public function after_update() 
		{
			Backend::$events->fireEvent('shop:onCustomerUpdated', $this);
		}

		public function after_create() 
		{
			Backend::$events->fireEvent('shop:onCustomerCreated', $this);
		}
		
		/*
		 * Customer CSV import functions
		 */
		
		public function get_csv_import_columns()
		{
			$columns = $this->get_column_definitions();

			$columns['billing_country']->listTitle = 'Billing Country';
			$columns['billing_state']->listTitle = 'Billing State';
			$columns['billing_street_addr']->listTitle = 'Billing Street Address';
			$columns['billing_city']->listTitle = 'Billing City';
			$columns['billing_zip']->listTitle = 'Billing Zip/Postal Code';

			$columns['shipping_country']->listTitle = 'Shipping Country';
			$columns['shipping_state']->listTitle = 'Shipping State';
			$columns['shipping_street_addr']->listTitle = 'Shipping Street Address';
			$columns['shipping_city']->listTitle = 'Shipping City';
			$columns['shipping_zip']->listTitle = 'Shipping Zip/Postal Code';

			$columns['shipping_first_name']->listTitle = 'Shipping First Name';
			$columns['shipping_last_name']->listTitle = 'Shipping Last Name';
			$columns['shipping_company']->listTitle = 'Shipping Company';
			$columns['shipping_phone']->listTitle = 'Shipping Phone';
			$columns['shipping_addr_is_business']->listTitle = 'Shipping address is business';

			$this->validation->add('billing_country')->required();
			
			$non_required = array('billing_country', 'billing_state', 'shipping_state', 'shipping_street_addr', 'shipping_city', 'shipping_zip', 'shipping_first_name', 'shipping_last_name');
			foreach ($non_required as $field)
			{
				$rules = $this->validation->getRule($field);
				if ($rules) 
					$rules->required = false;
			}

			unset($columns['guest']);
			unset($columns['password_confirm']);
			unset($columns['created_at']);
			unset($columns['created_user_name']);
			unset($columns['updated_at']);
			unset($columns['updated_user_name']);

			return $columns;
		}

		/*
		 * Security functions
		 */
		
		public function findUser($email, $password)
		{
			$event_result = Backend::$events->fireEvent('shop:onAuthenticateCustomer', $email, $password);
			foreach ($event_result as $event_customer)
			{
				if ($event_customer)
					return $event_customer;
			}
			
			$email = mb_strtolower($email);
			return $this->where('email=?', $email)->where('password=?', Phpr_SecurityFramework::create()->salted_hash($password))->where('(guest is null or guest=0)')->where('deleted_at is null')->find();
		}
	}

?>