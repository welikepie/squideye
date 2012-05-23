<?

	class Shop_PayPal_Pro_Payment extends Shop_PaymentType
	{
		/**
		 * Returns information about the payment type
		 * Must return array: array(
		 *		'name'=>'Authorize.net', 
		 *		'custom_payment_form'=>false,
		 *		'offline'=>false,
		 *		'pay_offline_message'=>null
		 * ).
		 * Use custom_paymen_form key to specify a name of a partial to use for building a back-end
		 * payment form. Usually it is needed for forms which ACTION refer outside web services, 
		 * like PayPal Standard. Otherwise override build_payment_form method to build back-end payment
		 * forms.
		 * If the payment type provides a front-end partial (containing the payment form), 
		 * it should be called in following way: payment:name, in lower case, e.g. payment:authorize.net
		 *
		 * Set index 'offline' to true to specify that the payments of this type cannot be processed online 
		 * and thus they have no payment form. You may specify a message to display on the payment page
		 * for offline payment type, using 'pay_offline_message' index.
		 *
		 * @return array
		 */
		public function get_info()
		{
			return array(
				'name'=>'PayPal Pro',
				'description'=>'PayPal Pro payment method, with payment form hosted on your server.'
			);
		}

		/**
		 * Builds the payment type administration user interface 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host_obj, $context = null)
		{
			$host_obj->add_field('test_mode', 'Sandbox Mode')->tab('Configuration')->renderAs(frm_onoffswitcher)->comment('Use the PayPal Sandbox Test Environment to try out Website Payments. You should be logged into the PayPal Sandbox to work in test mode.', 'above');

			if ($context !== 'preview')
			{
				$host_obj->add_form_partial($host_obj->get_partial_path('hint.htm'))->tab('Configuration');

				$host_obj->add_field('api_signature', 'API Signature')->tab('Configuration')->renderAs(frm_text)->comment('You can find your API signature, user name and password on PayPal profile page in Account Information/API Access section.', 'above', true)->validation()->fn('trim')->required('Please provide PayPal API signature.');
				$host_obj->add_field('api_user_name', 'API User Name', 'left')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide PayPal API user name.');
				$host_obj->add_field('api_password', 'API Password', 'right')->tab('Configuration')->renderAs(frm_text)->validation()->fn('trim')->required('Please provide PayPal API password.');
			}
			
			$host_obj->add_field('paypal_action', 'PayPal Action', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('Action PayPal should perform with customer\'s credit card.', 'above');

			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}
		
		public function get_paypal_action_options($current_key_value = -1)
		{
			$options = array(
				'Sale'=>'Capture',
				'Authorization'=>'Authorization only'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			
		}
		
		/**
		 * Validates configuration data after it is loaded from database
		 * Use host object to access fields previously added with build_config_ui method.
		 * You can alter field values if you need
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_load($host_obj)
		{
		}

		/**
		 * Initializes configuration data when the payment method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host_obj)
		{
			$host_obj->test_mode = 1;
			$host_obj->order_status = Shop_OrderStatus::get_status_paid()->id;
		}

		/**
		 * Builds the back-end payment form 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param $host_obj ActiveRecord object to add fields to
		 */
		public function build_payment_form($host_obj)
		{
			$host_obj->add_field('CREDITCARDTYPE', 'Credit Card Type')->renderAs(frm_dropdown)->comment('Please select a credit card type.', 'above')->validation()->fn('trim')->required();
			$host_obj->add_field('FIRSTNAME', 'First Name', 'left')->renderAs(frm_text)->comment('Cardholder first name', 'above')->validation()->fn('trim')->required('Please specify a cardholder first name');
			$host_obj->add_field('LASTNAME', 'Last Name', 'right')->renderAs(frm_text)->comment('Cardholder last name', 'above')->validation()->fn('trim')->required('Please specify a cardholder last name');
			$host_obj->add_field('ACCT', 'Credit Card Number', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
			$host_obj->add_field('CVV2', 'CVV2', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
			
			$host_obj->add_field('ISSUENUMBER', 'Issue Number')->comment('Please specify the Issue Number or Start Date for Solo and Maestro cards', 'above')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();

			$host_obj->add_field('STARTDATE_MONTH', 'Start Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
			$host_obj->add_field('STARTDATE_YEAR', 'Start Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->numeric();
		}
		
		public function get_CREDITCARDTYPE_options()
		{
			return array(
				'Visa'=>'Visa',
				'MasterCard'=>'Master Card',
				'Discover'=>'Discover',
				'Amex'=>'American Express',
				'Maestro'=>'Maestro',
				'Solo'=>'Solo'
			);
		}

		/**
		 * Returns true if the payment type is applicable for a specified order amount
		 * @param float $amount Specifies an order amount
		 * @param $host_obj ActiveRecord object to add fields to
		 * @return true
		 */
		public function is_applicable($amount, $host_obj)
		{
			$currency_converter = Shop_CurrencyConverter::create();
			$currency = Shop_CurrencySettings::get();

			return $currency_converter->convert($amount, $currency->code, 'USD') <= 10000;
		}

		/*
		 * Payment processing
		 */

		private function format_form_fields(&$fields)
		{
			$result = array();
			foreach($fields as $key=>$val)
			    $result[] = urlencode($key)."=".urlencode($val); 
			
			return implode('&', $result);
		}
		
		private function post_data($endpoint, $fields)
		{
			$errno = null;
			$errorstr = null;

			$fp = null;
			try
			{
				$fp = @fsockopen('ssl://'.$endpoint, 443, $errno, $errorstr, 60);
			}
			catch (Exception $ex) {}
			if (!$fp)
				throw new Phpr_SystemException("Error connecting to PayPal server. Error number: $errno, error: $errorstr");

			$poststring = $this->format_form_fields($fields);

			fputs($fp, "POST /nvp HTTP/1.1\r\n"); 
			fputs($fp, "Host: $endpoint\r\n"); 
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n"); 
			fputs($fp, "Content-length: ".strlen($poststring)."\r\n"); 
			fputs($fp, "Connection: close\r\n\r\n"); 
			fputs($fp, $poststring . "\r\n\r\n"); 

			$response = null;
			while(!feof($fp))
				$response .= fgets($fp, 4096);
				
			return $response;
		}

		private function parse_response($response)
		{
			$matches = array();
			preg_match('/Content\-Length:\s([0-9]+)/i', $response, $matches);
			if (!count($matches))
				throw new Phpr_ApplicationException('Invalid PayPal response');

			$elements = substr($response, $matches[1]*-1);
			$elements = explode('&', $elements);

			$result = array();
			foreach ($elements as $element)
			{
				$element = explode('=', $element);
				if (isset($element[0]) && isset($element[1]))
					$result[$element[0]] = urldecode($element[1]);
			}
			
			return $result;
		}

		private function prepare_fields_log($fields)
		{
			unset($fields['PWD']);
			unset($fields['USER']);
			unset($fields['SIGNATURE']);
			unset($fields['VERSION']);
			unset($fields['METHOD']);
			unset($fields['CVV2']);
			$fields['ACCT'] = '...'.substr($fields['ACCT'], -4);
			
			return $fields;
		}
		
		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'A'=>'Address only match (no ZIP)',
				'B'=>'Address only match (no ZIP)',
				'C'=>'No match',
				'D'=>'Address and Postal Code match',
				'E'=>'Not allowed for MOTO (Internet/Phone) transactions',
				'F'=>'Address and Postal Code match',
				'G'=>'Not applicable',
				'I'=>'Not applicable',
				'N'=>'No match',
				'P'=>'Postal Code only match (no Address)',
				'R'=>'Retry/not applicable',
				'S'=>'Service not Supported',
				'U'=>'Unavailable/Not applicable',
				'W'=>'Nine-digit ZIP code match (no Address)',
				'X'=>'Exact match',
				'Y'=>'Address and five-digit ZIP match',
				'Z'=>'Five-digit ZIP code match (no Address)',
				'0'=>'All the address information matched',
				'1'=>'None of the address information matched',
				'2'=>'Part of the address information matched',
				'3'=>'The merchant did not provide AVS information. Not processed.',
				'4'=>'Address not checked, or acquirer had no response',
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown AVS response code';
		}
		
		protected function get_ccv_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'CCV response code is empty';

			$status_names = array(
				'M'=>'Match',
				'N'=>'No match',
				'P'=>'Not processed',
				'S'=>'Service not supported',
				'U'=>'Service not available',
				'X'=>'No response',
				'0'=>'Match',
				'1'=>'No match',
				'2'=>'The merchant has not implemented CVV2 code handling',
				'3'=>'Merchant has indicated that CVV2 is not present on card',
				'4'=>'Service not available'
			);
			
			if (array_key_exists($status_code, $status_names))
				return $status_names[$status_code];

			return 'Unknown CCV response code';
		}

		/**
		 * Processes payment using passed data
		 * @param array $data Posted payment form data
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param $order Order object
		 */
		public function process_payment_form($data, $host_obj, $order, $back_end = false)
		{
			/*
			 * Validate input data
			 */

			$validation = new Phpr_Validation();
			$validation->add('CREDITCARDTYPE', 'Credit card type')->fn('trim')->required('Please specify a credit card type.');
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');
			
			$validation->add('ISSUENUMBER', 'Issue Number')->fn('trim')->numeric();

			$validation->add('STARTDATE_MONTH', 'Start Month', 'left')->fn('trim')->numeric();
			$validation->add('STARTDATE_YEAR', 'Start Year', 'right')->fn('trim')->numeric();

			$validation->add('ACCT', 'Credit card number')->fn('trim')->required('Please specify a credit card number.')->regexp('/^[0-9]*$/', 'Please specify a valid credit card number. Credit card number can contain only digits.');
			$validation->add('CVV2', 'CVV2')->fn('trim')->required('Please specify CVV2 value.')->regexp('/^[0-9]*$/', 'Please specify a CVV2 number. CVV2 can contain only digits.');

			try
			{
				if (!$validation->validate($data))
					$validation->throwException();
			} catch (Exception $ex)
			{
				$this->log_payment_attempt($order, $ex->getMessage(), 0, array(), array(), null);
				throw $ex;
			}
				
			/*
			 * Send request
			 */
			
			@set_time_limit(3600);
			$endpoint = $host_obj->test_mode ? "api-3t.sandbox.paypal.com" : "api-3t.paypal.com";
			$fields = array();
			$response = null;
			$response_fields = array();

			try
			{
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				
				if (strlen($validation->fieldValues['STARTDATE_MONTH']))
					$startMonth = $validation->fieldValues['STARTDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['STARTDATE_MONTH'] : $validation->fieldValues['STARTDATE_MONTH'];
				else
					$startMonth = null;

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				$fields['PWD'] = $host_obj->api_password;
				$fields['USER'] = $host_obj->api_user_name;
				$fields['SIGNATURE'] = $host_obj->api_signature;
				$fields['VERSION'] = '3.0';
				$fields['METHOD'] = 'DoDirectPayment';

				$fields['CREDITCARDTYPE'] = $validation->fieldValues['CREDITCARDTYPE'];
				$fields['ACCT'] = $validation->fieldValues['ACCT'];
				$fields['EXPDATE'] = $expMonth.$validation->fieldValues['EXPDATE_YEAR'];
				$fields['STARTDATE'] = $startMonth.$validation->fieldValues['STARTDATE_YEAR'];
				$fields['CVV2'] = $validation->fieldValues['CVV2'];
				$fields['AMT'] = $order->total;
				$fields['ISSUENUMBER'] = $validation->fieldValues['ISSUENUMBER'];
				$fields['CURRENCYCODE'] = Shop_CurrencySettings::get()->code;
				
				$fields['FIRSTNAME'] = $validation->fieldValues['FIRSTNAME'];
				$fields['LASTNAME'] = $validation->fieldValues['LASTNAME'];
				$fields['IPADDRESS'] = $userIp;
				$fields['STREET'] = $order->billing_street_addr;
				
				if ($order->billing_state)
					$fields['STATE'] = $order->billing_state->code;
					
				$fields['COUNTRY'] = $order->billing_country->name;
				$fields['CITY'] = $order->billing_city;
				$fields['ZIP'] = $order->billing_zip;
				$fields['COUNTRYCODE'] = $order->billing_country->code;
				$fields['PAYMENTACTION'] = $host_obj->paypal_action;
				
				$fields['ITEMAMT'] = $order->subtotal;
				$fields['SHIPPINGAMT'] = $order->shipping_quote;
				
				$fields['TAXAMT'] = number_format($order->goods_tax + $order->shipping_tax, 2, '.', '');

				$item_index = 0;
				foreach ($order->items as $item)
				{
					$fields['L_NAME'.$item_index] = mb_substr($item->output_product_name(true, true), 0, 127);
					$fields['L_AMT'.$item_index] = number_format($item->unit_total_price, 2, '.', '');
					$fields['L_QTY'.$item_index] = $item->quantity;
					$item_index++;
				}
				
				if (!ceil($order->subtotal) && $order->shipping_quote)
				{
					$fields['SHIPPINGAMT'] = '0.00';
					
				 	$fields['L_NAME'.$item_index] = 'Shipping';
				 	$fields['L_AMT'.$item_index] = number_format($order->shipping_quote, 2, '.', '');
				 	$fields['L_QTY'.$item_index] = 1;
				 	$item_index++;
				 	
				 	$fields['ITEMAMT'] = $order->shipping_quote;
				}
				
				// if ($order->discount)
				// {
				// 	$fields['L_NAME'.$item_index] = 'Discount';
				// 	$fields['L_AMT'.$item_index] = number_format(-1*$order->discount, 2, '.', '');
				// 	$fields['L_QTY'.$item_index] = 1;
				// 	$item_index++;
				// }

				$fields['SHIPTONAME'] = $order->shipping_first_name.' '.$order->shipping_last_name;
				$fields['SHIPTOSTREET'] = $order->shipping_street_addr;
				$fields['SHIPTOCITY'] = $order->shipping_city;
				$fields['SHIPTOCOUNTRYCODE'] = $order->shipping_country->code;
				
				if ($order->shipping_state)
					$fields['SHIPTOSTATE'] = $order->shipping_state->code;

				$fields['SHIPTOPHONENUM'] = $order->shipping_phone;
				$fields['SHIPTOZIP'] = $order->shipping_zip;
				
				$fields['INVNUM'] = $order->id;
				$fields['ButtonSource'] = 'LemonStand_Cart_DP';

				$response = $this->post_data($endpoint, $fields);

				/*
				 * Process result
				 */
		
				$response_fields = $this->parse_response($response);
				if (!isset($response_fields['ACK']))
					throw new Phpr_ApplicationException('Invalid PayPal response.');
					
				if ($response_fields['ACK'] !== 'Success' && $response_fields['ACK'] !== 'SuccessWithWarning')
				{
					for ($i=5; $i>=0; $i--)
					{
						if (isset($response_fields['L_LONGMESSAGE'.$i]))
							throw new Phpr_ApplicationException($response_fields['L_LONGMESSAGE'.$i]);
					}

					throw new Phpr_ApplicationException('Invalid PayPal response.');
				}
		
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$fields = $this->prepare_fields_log($fields);
				
				$this->log_payment_attempt(
					$order, 
					'Successful payment', 
					1, 
					$fields, 
					$response_fields, 
					$response,
					$response_fields['CVV2MATCH'],
					$this->get_ccv_status_text($response_fields['CVV2MATCH']),
					$response_fields['AVSCODE'], 
					$this->get_avs_status_text($response_fields['AVSCODE'])
				);

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$fields = $this->prepare_fields_log($fields);
				
				$cvv_code = null;
				$cvv_message = null;
				$avs_code = null;
				$avs_message = null;
				
				if (array_key_exists('CVV2MATCH', $response_fields))
				{
					$cvv_code = $response_fields['CVV2MATCH'];
					$cvv_message = $this->get_ccv_status_text($response_fields['CVV2MATCH']);
					$avs_code = $response_fields['AVSCODE'];
					$avs_message = $this->get_avs_status_text($response_fields['AVSCODE']);
				}
				
				$this->log_payment_attempt(
					$order, 
					$ex->getMessage(), 
					0, 
					$fields, 
					$response_fields, 
					$response,
					$cvv_code,
					$cvv_message,
					$avs_code,
					$avs_message
				);

				throw new Phpr_ApplicationException($ex->getMessage());
			}
		}
		
		/**
		 * This function is called before an order status deletion.
		 * Use this method to check whether the payment method
		 * references an order status. If so, throw Phpr_ApplicationException 
		 * with explanation why the status cannot be deleted.
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_OrderStatus $status Specifies a status to be deleted
		 */
		public function status_deletion_check($host_obj, $status)
		{
			if ($host_obj->order_status == $status->id)
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in PayPal Pro payment method.');
		}
	}

?>