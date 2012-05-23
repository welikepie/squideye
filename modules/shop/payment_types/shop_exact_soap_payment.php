<?

	class Shop_Exact_Soap_Payment extends Shop_PaymentType
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
				'name'=>'E-xact Web Service',
				'description'=>'E-xact Web Service payment method. This payment method also works with VersaPay payment gateway (Canada).'
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
			if ($context != 'preview')
			{
				if (!class_exists('SoapClient'))
					throw new Phpr_ApplicationException('PHP SOAP library required for this payment method is not available.');
			}
			
			$host_obj->add_field('gateway', 'Gateway')->tab('Configuration')->renderAs(frm_dropdown);

			if ($context !== 'preview')
			{
				$host_obj->add_field('exact_id', 'ExactID', 'left')->tab('Configuration')->renderAs(frm_text)->comment('This number is provided by E-xact/VersaPay upon set-up.', 'above')->validation()->fn('trim')->required('Please provide ExactID.');
				$host_obj->add_field('password', 'Password', 'right')->tab('Configuration')->renderAs(frm_password)->comment('E-xact password that is uniquely associated with each ExactID.', 'above')->validation()->fn('trim');
			}

			$host_obj->add_field('language', 'Language')->tab('Configuration')->renderAs(frm_dropdown)->comment('Gateway response language.', 'above')->validation()->fn('trim');
			
			$host_obj->add_field('transaction_type', 'Transaction type', 'left')->tab('Configuration')->renderAs(frm_dropdown)->comment('The type of transaction request the payment should perform.', 'above');
			$host_obj->add_field('order_status', 'Order Status', 'right')->tab('Configuration')->renderAs(frm_dropdown)->comment('Select status to assign the order in case of successful payment.', 'above');
		}
		
		public function get_transaction_type_options($current_key_value = -1)
		{
			$options = array(
				'00'=>'Purchase',
				'01'=>'Pre-Authorization'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		public function get_gateway_options($current_key_value = -1)
		{
			$options = array(
				'exact'=>'E-xact',
				'versapay'=>'VersaPay'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}

		public function get_language_options($current_key_value = -1)
		{
			$options = array(
				'en'=>'English',
				'fr'=>'French'
			);
			
			if ($current_key_value == -1)
				return $options;

			return isset($options[$current_key_value]) ? $options[$current_key_value] : null;
		}
		
		public function get_order_status_options($current_key_value = -1)
		{
			if ($current_key_value == -1)
				return Shop_OrderStatus::create()->order('name')->find_all()->as_array('name', 'id');

			return Shop_OrderStatus::create()->find($current_key_value)->name;
		}

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			$hash_value = trim($host_obj->password);

			if (!strlen($hash_value))
			{
				if (!isset($host_obj->fetched_data['password']) || !strlen($host_obj->fetched_data['password']))
					$host_obj->validation->setError('Please enter the Password', 'password', true);

				$host_obj->password = $host_obj->fetched_data['password'];
			}
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
			$host_obj->add_field('FIRSTNAME', 'First Name', 'left')->renderAs(frm_text)->comment('Cardholder first name', 'above')->validation()->fn('trim')->required('Please specify a cardholder first name');
			$host_obj->add_field('LASTNAME', 'Last Name', 'right')->renderAs(frm_text)->comment('Cardholder last name', 'above')->validation()->fn('trim')->required('Please specify a cardholder last name');
			$host_obj->add_field('ACCT', 'Credit Card Number', 'left')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a credit card number')->regexp('/^[0-9]+$/', 'Credit card number can contain only digits.');
			$host_obj->add_field('CVV2', 'CVV2', 'right')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify Card Verification Number')->numeric();

			$host_obj->add_field('EXPDATE_MONTH', 'Expiration Month', 'left')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration month')->numeric();
			$host_obj->add_field('EXPDATE_YEAR', 'Expiration Year', 'right')->renderAs(frm_text)->renderAs(frm_text)->validation()->fn('trim')->required('Please specify card expiration year')->numeric();
		}

		/*
		 * Payment processing
		 */

		private function post_data($fields, $host_obj, $call = 'SendAndCommit')
		{
			if (!class_exists('SoapClient'))
				throw new Phpr_ApplicationException('PHP SOAP library is not available.');

			if ($host_obj->gateway != 'versapay')
				$endpoint = "https://secure2.e-xact.com/vplug-in/transaction/rpc-enc/service.asmx?wsdl";
			else
				$endpoint = "https://api.e-xact.com/vplug-in/transaction/rpc-enc/service.asmx?wsdl";

			$trxn = array("Transaction"=>$fields);

			ini_set('default_socket_timeout', 30);
			$client = new SoapClient($endpoint, array('connection_timeout'=>30, 'exceptions'=>true));
			$result = $client->__soapCall($call, $trxn);

			return $result;
		}
		
		private function prepare_fields_log($fields)
		{
			unset($fields['ExactID']);
			unset($fields['Password']);
			unset($fields['VerificationStr2']);
			$fields['Card_Number'] = '...'.substr($fields['Card_Number'], -4);
			
			return $fields;
		}

		protected function get_avs_status_text($status_code)
		{
			$status_code = strtoupper($status_code);
			
			if (!strlen($status_code))
				return 'AVS response code is empty';
			
			$status_names = array(
				'X	Exact match, 9 digit zip',
				'Y'=>'Exact match, 5 digit zip',
				'A'=>'Address match only',
				'W'=>'9-digit zip match only',
				'Z'=>'5-digit zip match only',
				'N'=>'No address or zip match', 
				'U'=>'Address unavailable',
				'G'=>'Global non-AVS participant',
				'R'=>'Issuer system unavailable',
				'E'=>'Not a mail/phone order',
				'S'=>'Service not supported',
				'B'=>'Address matches only',
				'C'=>'Address and Postal Code do not match',
				'D'=>'Address and Postal Code match',
				'F'=>'Address and Postal Code match',
				'I'=>'Address information not verified for international transaction',
				'M'=>'Address and Postal Code match',
				'P'=>'Postal Code matches only',
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
				'M'=>'CVV2 / CVC2/CVD Match',
				'N'=>'CVV2 / CVC2/CVD No Match',
				'P'=>'Not Processed',
				'S'=>'Merchant has indicated that CVV2 / CVC2/CVD is not present on the card',
				'U'=>'Issuer is not certified and / or has not provided Visa encryption keys'
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
			$validation->add('FIRSTNAME', 'Cardholder first name')->fn('trim')->required('Please specify a cardholder first name.');
			$validation->add('LASTNAME', 'Cardholder last name')->fn('trim')->required('Please specify a cardholder last name.');
			$validation->add('EXPDATE_MONTH', 'Expiration month')->fn('trim')->required('Please specify a card expiration month.')->regexp('/^[0-9]*$/', 'Credit card expiration month can contain only digits.');
			$validation->add('EXPDATE_YEAR', 'Expiration year')->fn('trim')->required('Please specify a card expiration year.')->regexp('/^[0-9]*$/', 'Credit card expiration year can contain only digits.');

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
			
			$fields = array();
			$response = null;
			$error_logged = false;
			$response_fields = array();

			try
			{
				$expMonth = $validation->fieldValues['EXPDATE_MONTH'] < 10 ? '0'.$validation->fieldValues['EXPDATE_MONTH'] : $validation->fieldValues['EXPDATE_MONTH'];
				$expYear = $validation->fieldValues['EXPDATE_YEAR'] > 2000 ? $validation->fieldValues['EXPDATE_YEAR'] - 2000 : $validation->fieldValues['EXPDATE_YEAR'];
				if ($expYear < 10)
					$expYear = '0'.$expYear;

				$userIp = Phpr::$request->getUserIp();
				if ($userIp == '::1')
					$userIp = '192.168.0.1';

				$fields['Secure_AuthResult'] = '';
				$fields['Ecommerce_Flag'] = '';
				$fields['User_Name'] = '';
				$fields['XID'] = '';
				$fields['ExactID'] = $host_obj->exact_id;
				$fields['CAVV'] = '';
				$fields['Password'] = $host_obj->password;
				$fields['CAVV_Algorithm'] = '';
				$fields['Transaction_Type'] = $host_obj->transaction_type;
				$fields['Reference_No'] = $order->id;
				$fields['Customer_Ref'] = $order->customer_id;
				$fields['Reference_3'] = '';
				$fields['Client_IP'] = $userIp;
				$fields['Track1'] = '';
				$fields['Track2'] = '';
				$fields['PAN'] = '';
				$fields['Authorization_Num'] = '';
				$fields['VerificationStr1'] = '';
				
				$fields['Client_Email'] = $order->billing_email;
				$fields['Language'] = $host_obj->language;

				$fields['Card_Number'] = $validation->fieldValues['ACCT'];
				$fields['Expiry_Date'] = $expMonth.$expYear;
				$fields['CardHoldersName'] = $validation->fieldValues['FIRSTNAME'].' '.$validation->fieldValues['LASTNAME'];
				
				$fields['VerificationStr2'] = $validation->fieldValues['CVV2'];
				$fields['DollarAmount'] = $order->total;
				$fields['CVD_Presence_Ind'] = '1';
				$fields['Secure_AuthRequired'] = '';
				
				$fields['ZipCode'] = $order->billing_zip;
				$fields['Tax1Amount'] = $order->goods_tax;
				$fields['Tax1Number'] = 'Sales Tax';
				$fields['Tax2Amount'] = $order->shipping_tax;
				$fields['Tax2Number'] = 'Shipping tax';
				
				$fields['SurchargeAmount'] = '';
				$fields['Transaction_Tag'] = $order->id + time() - 1251679000;

				try
				{
					$response_fields = $this->post_data($fields, $host_obj);
					if (!is_object($response_fields) && !is_array($response_fields))
						throw new Phpr_ApplicationException('Invalid payment gateway response');
						
					if (is_object($response_fields))
						$response_fields = (array)$response_fields;
				}
				catch (Exception $ex)
				{
					$error_logged = true;
					
					$fields = $this->prepare_fields_log($fields);
					$this->log_payment_attempt($order, $ex->getMessage(), 0, $fields, array(), null);
					throw new Phpr_ApplicationException('Error connecting to the payment gateway.');
				}

				/*
				 * Process result
				 */
		
				if (!isset($response_fields['Transaction_Approved']))
					throw new Phpr_ApplicationException('Invalid payment gateway response.');
					
				if (!$response_fields['Transaction_Approved'])
					throw new Phpr_ApplicationException('The card was declined by the payment gateway.');
		
				/*
				 * Successful payment. Set order status and mark it as paid.
				 */

				$this->log_payment_attempt(
					$order, '
					Successful payment', 
					1, 
					$this->prepare_fields_log($fields), 
					$this->prepare_fields_log($response_fields), 
					null,
					$response_fields['CVV2'],
					$this->get_ccv_status_text($response_fields['CVV2']),
					$response_fields['AVS'], 
					$this->get_avs_status_text($response_fields['AVS'])
				);

				/*
				 * Log transaction create/change
				 */
				// $this->update_transaction_status($host_obj, $order, $response_fields['Transaction_Tag'], $this->get_transaction_type_name($response_fields['Transaction_Type']), $response_fields['Transaction_Type']);

				/*
				 * Change order status
				 */

				Shop_OrderStatusLog::create_record($host_obj->order_status, $order);
				
				/*
				 * Mark order as paid
				 */
				
				$order->set_payment_processed();
			}
			catch (Exception $ex)
			{
				$error_message = $ex->getMessage();
				if (!$error_logged)
				{
					if (isset($response_fields['EXact_Message']))
						$error_message = $response_fields['EXact_Message'];
						
					$cvv_code = null;
					$cvv_message = null;
					$avs_code = null;
					$avs_message = null;

					if (array_key_exists('CVV2', $response_fields))
					{
						$cvv_code = $response_fields['CVV2'];
						$cvv_message = $this->get_ccv_status_text($response_fields['CVV2']);
						$avs_code = $response_fields['AVS'];
						$avs_message = $this->get_avs_status_text($response_fields['AVS']);
					}
					
					$fields = $this->prepare_fields_log($fields);
					$this->log_payment_attempt(
						$order, 
						$error_message, 
						0, 
						$fields, 
						$this->prepare_fields_log($response_fields), 
						null,
						$cvv_code,
						$cvv_message,
						$avs_code,
						$avs_message
					);
				}
				
				if (!$back_end)
					throw new Phpr_ApplicationException($ex->getMessage());
				else
					throw new Phpr_ApplicationException($error_message);
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
				throw new Phpr_ApplicationException('Status cannot be deleted because it is used in E-xact SOAP payment method.');
		}
		
		protected function get_transaction_type_name($type)
		{
			$types = array(
				'00'=>'Purchase',
				'01'=>'Pre-Authorization',
				'02'=>'Pre-Authorization Completion',
				'04'=>'Refund',
				'13'=>'Void',
				'03'=>'Forced Post',
				'40'=>'Recurring Seed',
				'41'=>'Recurring Seed Purchase',
				'30'=>'Tagged Purchase',
				'31'=>'Tagged Pre-Authorization',
				'32'=>'Tagged Completion',
				'34'=>'Tagged Refund',
				'50'=>'Debit Purchase',
				'54'=>'Debit Refund ',
				'35'=>'Debit Online Tagged Refund',
				'05'=>'Pre-Authorization Only',
				'11'=>'Purchase Correction',
				'12'=>'Refund Correction',
				'60'=>'Secure Storage',
				'CR'=>'Transaction Details'
			);
			
			if (array_key_exists($type, $types))
				return $types[$type];
				
			return 'Unknown';
		}
		
		/*
		 * Transaction management methods
		 */
		
		/**
		 * This method should return TRUE if the payment gateway supports requesting a status of a specific transaction
		 */
		public function supports_transaction_status_query()
		{
			return false;
		}

		/**
		 * Returns a list of available transitions from a specific transaction status
		 * The method returns an associative array with keys corresponding transaction statuses 
		 * and values corresponding transaction status actions: array('V'=>'Void', 'S'=>'Submit for settlement')
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaction status code returned by the payment gateway. Example: authorized
		 */
		public function list_available_transaction_transitions($host_obj, $transaction_id, $transaction_status_code)
		{
			switch ($transaction_status_code)
			{
				case '00' :
				case '30' :
					return array(
						'13' => 'Void',
						'04' => 'Refund'
					);
				break;
				
				case '01' :
				case '31' :
					return array(
						'13' => 'Void',
						'02' => 'Pre-Authorization Completion'
					);
				break;
			}
			
			return array();
		}
		
		protected function format_request_fields($host_obj, $fields)
		{
			$all_fields = array();
			
			$all_fields['User_Name'] = '';
			$all_fields['ExactID'] = $host_obj->exact_id;
			$all_fields['Password'] = $host_obj->password;
			$all_fields['Transaction_Type'] = '';
			$all_fields['SurchargeAmount'] = '';
			$all_fields['DollarAmount'] = '';
			$all_fields['Secure_AuthResult'] = '';
			$all_fields['Ecommerce_Flag'] = '';
			$all_fields['User_Name'] = '';
			$all_fields['XID'] = '';
			$all_fields['CAVV'] = '';
			$all_fields['CAVV_Algorithm'] = '';
			$all_fields['Reference_No'] = '';
			$all_fields['Customer_Ref'] = '';
			$all_fields['Reference_3'] = '';
			$all_fields['Client_IP'] = '';
			$all_fields['Track1'] = '';
			$all_fields['Track2'] = '';
			$all_fields['PAN'] = '';
			$all_fields['Authorization_Num'] = '';
			$all_fields['VerificationStr1'] = '';
			$all_fields['Client_Email'] = '';
			$all_fields['Language'] = $host_obj->language;
			$all_fields['Card_Number'] = '';
			$all_fields['Expiry_Date'] = '';
			$all_fields['CardHoldersName'] = '';
			$all_fields['VerificationStr2'] = '';
			$all_fields['CVD_Presence_Ind'] = '';
			$all_fields['Secure_AuthRequired'] = '';
			$all_fields['ZipCode'] = '';
			$all_fields['Tax1Amount'] = '';
			$all_fields['Tax1Number'] = '';
			$all_fields['Tax2Amount'] = '';
			$all_fields['Tax2Number'] = '';
			$all_fields['Transaction_Tag'] = '';
			
			
			$result = array();
			foreach ($all_fields as $key=>$value)
				$result[$key] = array_key_exists($key, $fields) ? $fields[$key] : $value;
				
			return $result;
		}
		
		/**
		 * Request a status of a specific transaction a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function request_transaction_status($host_obj, $transaction_id)
		{
			try
			{
				$fields = $this->format_request_fields($host_obj, array('Transaction_Tag'=>$transaction_id, 'Transaction_Type'=>'CR'));
				
				$response_fields = $this->post_data($fields, $host_obj, 'TransactionInfo');
				if (!is_object($response_fields) && !is_array($response_fields))
					throw new Phpr_ApplicationException('Invalid payment gateway response');

				if (is_object($response_fields))
					$response_fields = (array)$response_fields;

				return new Shop_TransactionUpdate(
					$response_fields['Transaction_Type'],
					$response_fields['Transaction_Type']
				);
			}
			catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error requesting transaction status. '.$ex->getMessage());
			}
		}
		
		/**
		 * Contacts the payment gateway and sets specific status on a specific transaction.
		 * The method must return an instance of the Shop_TransactionUpdate class
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 * @param Shop_Order $order LemonStand order object the transaction is bound to
		 * @param string $transaction_id Specifies a transaction identifier returned by the payment gateway. Example: kjkls
		 * @param string $transaction_status_code Specifies a transaction status code returned by the payment gateway. Example: authorized
		 * @param string $new_transaction_status_code Specifies a destination transaction status code
		 * @return Shop_TransactionUpdate Transaction update information
		 */
		public function set_transaction_status($host_obj, $order, $transaction_id, $transaction_status_code, $new_transaction_status_code)
		{
			$override_status_name = false;

			$fields = $this->format_request_fields($host_obj, array('Transaction_Tag'=>$transaction_id, 'Transaction_Type'=>$new_transaction_status_code));


			// switch ($new_transaction_status_code)
			// {
			// 	case 'prior_auth_capture' : 
			// 		        	$submitResult = $aim_request->priorAuthCapture();
			// 	break;
			// 	case 'void' : 
			// 		
			// 	        		$submitResult = $aim_request->void();
			// 	break;
			// 	case 'credit' : 
			// 		$aim_request->setFields(array(
			// 			'card_num' => substr((string)$transaction_details->xml->transaction->payment->creditCard->cardNumber, -4),
			// 			'amount' => (string)$transaction_details->xml->transaction->authAmount
			// 		));
			//         			$submitResult = $aim_request->credit();
			// 
			// 		$override_status_name = 'Refund requested';
			// 	break;
			// 	default:
			// 		throw new Phpr_ApplicationException('Unknown transaction status code: '.$new_transaction_status_code);
			// }

			if (!$submitResult->approved)
			{
				$error_str = $submitResult->error_message;

				if ($error_str)
					throw new Phpr_ApplicationException($error_str);
				else
					throw new Phpr_ApplicationException('Error updating transaction status.');
			} else {
				$result = $this->request_transaction_status($host_obj, $transaction_id);
				if ($override_status_name)
					$result->transaction_status_name = $override_status_name;

				return $result;
			}
		}
	}

?>