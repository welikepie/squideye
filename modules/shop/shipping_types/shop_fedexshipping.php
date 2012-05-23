<?

	class Shop_FedExShipping extends Shop_ShippingType {
		const TEST_URL = "https://gatewaybeta.fedex.com:443/web-services";
		const LIVE_URL = "https://gateway.fedex.com:443/web-services";

		private $host;
		
		private static $response_cache = array();

		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * @return array
		 */
		public function get_info() {
			return array(
				'name' => 'FedEx',
				'description' => 'This shipping method allows to request quotes and shipping options from FedEx. You must have a FedEx account in order to use this method.'
			);
		}
		
		/**
		 * Builds the shipping type administration user interface 
		 * For drop-down and radio fields you should also add methods returning 
		 * options. For example, of you want to have Sizes drop-down:
		 * public function get_sizes_options();
		 * This method should return array with keys corresponding your option identifiers
		 * and values corresponding its titles.
		 * 
		 * @param mixed $host ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host, $context = null) {
			$this->host = $host;
			
			$this->host->add_field('test_mode', 'Developer Mode')->tab('User Credentials')->renderAs(frm_onoffswitcher)->comment('Use the FedEx Test Environment to try out API requests.', 'above', true);

			$this->host->add_field('account_number', 'Account Number', 'left')->tab('User Credentials')->comment('Your FedEx account number.', 'above')->renderAs(frm_text)->validation()->required('Please specify your FedEx Account Number');
			
			$this->host->add_field('meter_number', 'Meter Number', 'right')->tab('User Credentials')->comment('Your FedEx meter number.', 'above')->renderAs(frm_text)->validation()->required('Please specify your FedEx Meter Number');
			
			if($context !== 'preview') {
				$this->host->add_field('user_key', 'Key', 'left')->tab('User Credentials')->comment('Your FedEx Web Service Key', 'above')->renderAs(frm_text)->validation()->required('Please specify your key');
				$this->host->add_field('user_password', 'Password', 'right')->tab('User Credentials')->comment('Your FedEx Web Service Password', 'above')->renderAs(frm_password)->validation();
			}

			$this->host->add_field('quote_type', 'Quote Type', 'full')->tab('Shipping Parameters')->comment('The quoted FedEx prices can be the list prices, or they can be the account prices (discounted). Note: there is no separate list price for international orders, so the account prices will be used.',	'above')->renderAs(frm_dropdown)->validation()->required('Please specify quote type');
			
			//$this->host->add_field('delivery_confirmation', 'Delivery Confirmation', 'full')->tab('Shipping Parameters')->comment('The confirmation your customers will need to go through to verify the delivery.',	'above')->renderAs(frm_dropdown)->validation()->required('Please specify quote type');
			
			$this->host->add_field('container', 'Container Type', 'left')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify container type');
			$this->host->add_field('pickup_type', 'Pickup Type', 'right')->tab('Shipping Parameters')->renderAs(frm_dropdown)->validation()->required('Please specify pickup type');
			$this->host->add_field('allowed_methods', 'Allowed methods')->tab('Shipping Parameters')->renderAs(frm_checkboxlist)->validation();
			$this->host->add_field('min_weight', 'Minimum Item Weight')->tab('Shipping Parameters')->comment('Minimum weight for one package. FedEx requires a minimum weight of 1 lb per item.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a minimum weight.')->float();
			$this->host->add_field('max_weight', 'Maximum Item Weight')->tab('Shipping Parameters')->comment('Maximum weight for one package. FedEx requires a maximum weight of 150 lb per item.', 'above')->renderAs(frm_text)->validation()->fn('trim')->required('Please specify a maximum weight')->float();	
			
			$this->host->add_field('free_shipping_enabled', 'Enable free shipping option')->tab('Free Shipping')->renderAs(frm_checkbox)->validation();
			$this->host->add_field('free_shipping_option', 'Free shipping method')->tab('Free Shipping')->renderAs(frm_dropdown)->validation();
			$this->host->add_field('free_shipping_min_amount', 'Minimum order amount for free shipping', 'full', $type = db_number)->tab('Free Shipping')->renderAs(frm_text)->validation();
		}

		public function get_quote_type_options($current_key_value = -1) {
			$container_types = array(
				'LIST' => 'List Prices',
				'ACCOUNT' => 'Account Prices (discounted)'
			);
			
			if($current_key_value == -1)
				return $container_types;

			return array_key_exists($current_key_value, $container_types) ? $container_types[$current_key_value] : null;
		}

		public function get_container_options($current_key_value = -1) {
			$container_types = array(
				'YOUR_PACKAGING' => 'Customer Packaging',
				'FEDEX_ENVELOPE' => 'Envelope',
				'FEDEX_PAK' => 'FedEx Pak',
				'FEDEX_BOX' => 'FedEx Box',
				'FEDEX_TUBE' => 'FedEx Tube',
				'FEDEX_10KG_BOX' => 'FedEx 10KG Box',
				'FEDEX_25KG_BOX' => 'FedEx 25KG Box'
			);
			
			if($current_key_value == -1)
				return $container_types;

			return array_key_exists($current_key_value, $container_types) ? $container_types[$current_key_value] : null;
		}
		
		// not implemented
		public function get_delivery_confirmation_options($current_key_value = -1) {
			$signature_types = array(
				'SERVICE_DEFAULT' => 'Default',
				'DIRECT' => 'Required',
				'NO_SIGNATURE_REQUIRED' => 'No Signature Required',
				'ADULT' => 'Adult'
			);
			
			if($current_key_value == -1)
				return $container_types;

			return array_key_exists($current_key_value, $signature_types) ? $signature_types[$current_key_value] : null;
		}
		
		// not implemented
		public function get_printer_options($current_key_value = -1) {
			$printer_types = array(
				'PDF' => 'PDF',
				'EPL2' => 'Thermal',
				'PNG' => 'Image',
			);
			
			if($current_key_value == -1)
				return $printer_types;

			return array_key_exists($current_key_value, $printer_types) ? $printer_types[$current_key_value] : null;
		}
		
		protected function get_service_list() {
			$services = array(
				'EUROPE_FIRST_INTERNATIONAL_PRIORITY' => 'Europe First International Priority',
				'FEDEX_1_DAY_FREIGHT' => 'FedEx 1-Day Freight',
				'FEDEX_2_DAY' => 'FedEx 2-Day',
				'FEDEX_2_DAY_FREIGHT' => 'FedEx 2-Day Freight',
				'FEDEX_3_DAY_FREIGHT' => 'FedEx 3-Day Freight',
				'FEDEX_EXPRESS_SAVER' => 'FedEx Express Saver',
				'FEDEX_GROUND' => 'FedEx Ground',
				'FIRST_OVERNIGHT' => 'First Overnight',
				'GROUND_HOME_DELIVERY' => 'Ground Home Delivery',
				'INTERNATIONAL_ECONOMY' => 'International Economy',
				'INTERNATIONAL_ECONOMY_FREIGHT' => 'International Economy Freight',
				'INTERNATIONAL_FIRST' => 'International First',
				'INTERNATIONAL_GROUND' => 'International Ground',
				'INTERNATIONAL_PRIORITY' => 'International Priority',
				'INTERNATIONAL_PRIORITY_FREIGHT' => 'International Priority Freight',
				'PRIORITY_OVERNIGHT' => 'Priority Overnight',
				'SMART_POST' => 'Smart Post',
				'STANDARD_OVERNIGHT' => 'Standard Overnight'
			);

			return $services;
		}

		public function get_pickup_type_options($current_key_value = -1) {
			$types = array(
				'DROP_BOX' => 'Drop Box',
				'BUSINESS_SERVICE_CENTER' => 'Business Service Center',
				'REGULAR_PICKUP' => 'Regular Pickup',
				'REQUEST_COURIER' => 'Request Courier',
				'STATION' => 'Station'
			);
			
			if($current_key_value == -1)
				return $types;

			return array_key_exists($current_key_value, $types) ? $types[$current_key_value] : null;
		}
		
		public function get_allowed_methods_option_state($value = 1) {
			return is_array($this->host->allowed_methods) && in_array($value, $this->host->allowed_methods);
		}

		public function get_free_shipping_option_options($current_key_value = -1) {
			$options = $this->get_service_list();
			
			if($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}

		public function get_allowed_methods_options($current_key_value = -1) {
			$options = $this->get_service_list();

			if($current_key_value == -1)
				return $options;

			return array_key_exists($current_key_value, $options) ? $options[$current_key_value] : null;
		}
		
		/**
		 * The Discount Engine uses this method for displaying a list of available shipping options on the 
		 * Free Shipping tab of the Cart Price Rule form. 
		 * 
		 * For multi-options shipping methods (like USPS or UPS) this method should return a list of
		 * enabled options in the following format:
		 * array(
		 * 	array('name'=>'First class', 'id'=>'first_class'),
		 * 	array('name'=>'Priority', 'id'=>'priority'),
		 * )
		 * The options identifiers must match the identifiers returned by the get_quote() method.
		 * @param $host ActiveRecord object containing configuration fields values
		 * @return array
		 */
		public function list_enabled_options($host) {
			$result = array();
			
			$options = $this->get_allowed_methods_options();
			
			foreach($options as $option_id => $option_name) {
				if($this->get_allowed_methods_option_state($option_id))
					$result[] = array('id' => $option_id, 'name' => $option_name);
			}

			return $result;
		}
		
		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host ActiveRecord Object containing configuration fields values
		 */
		public function validate_config_on_save($host) {
			if($host->free_shipping_enabled && !strlen($host->free_shipping_min_amount))
				$host->validation->setError('Please specify minimum order amount for free shipping or disable the free shipping option', 'free_shipping_min_amount', true);
				
			$hash_value = trim($host->user_password); 
			
			if(!strlen($hash_value)) { 
				if(!isset($host->fetched_data['user_password']) || !strlen($host->fetched_data['user_password'])) 
					$host->validation->setError('Please enter your FedEx User password', 'user_password', true); 

				$host->user_password = $host->fetched_data['user_password']; 
			}
		}
		
		/**
		 * Initializes configuration data when the shipping method is first created
		 * Use host object to access and set fields previously added with build_config_ui method.
		 * @param $host ActiveRecord object containing configuration fields values
		 */
		public function init_config_data($host) {
			$this->host->test_mode = true;
			$this->host->min_weight = 1;
			$this->host->max_weight = 150;
		}
		
		public function validate_config_on_load($host) {
			$host->quote_type = str_replace('RATED_', '',	$host->quote_type); // remove old setting
		}
		
		private function parse_date($date) {
		
		
			return $time;
		}
		
 		public function get_quote($parameters) {
			extract($parameters);

			$host = $host_obj;

			$shipping_params = Shop_ShippingParams::get();
			$currency = Shop_CurrencySettings::get();
			
			if($currency->code == 'GBP')
				$currency_code = 'UKL';
			else $currency_code = $currency->code;

			if(!$country = Shop_Country::create()->find($country_id))
				throw new Phpr_SystemException('Could not find the specified country.');

			$state = $state_id ? Shop_CountryState::create()->find($state_id) : null;

			$result = array();

			$allowed_services = $host->allowed_methods;
			$all_services = $this->get_service_list();

			if($this->host->free_shipping_enabled 
			&& $total_price >= $this->host->free_shipping_min_amount
			&& array_key_exists($this->host->free_shipping_option, $all_services)
			&& in_array($this->host->free_shipping_option, $allowed_services)) {
				$option_name = $all_services[$this->host->free_shipping_option];
				$result[$option_name] = array('id' => $this->host->free_shipping_option, 'quote' => 0);
			}

			$currency_converter = Shop_CurrencyConverter::create();

			$client = new SoapClient(PATH_APP . "/modules/shop/shipping_types/shop_fedexshipping/wsdl/RateService_v9.wsdl", array(
				'trace' => true,
				'location' => $this->host->test_mode ? self::TEST_URL : self::LIVE_URL,
				'uri' => "http://fedex.com/ws/rate/v9"
			));

			$total_weight = $total_weight > $this->host->min_weight ? $total_weight : $this->host->min_weight;

			$units = array(
				'KGS' => 'KG',
				'LBS' => 'LB'
			);
			
			$weight_unit = $units[h($shipping_params->weight_unit)];
			$dimension_unit = h($shipping_params->dimension_unit);
			
			$request['WebAuthenticationDetail'] = array('UserCredential' => array(
				'Key' => $this->host->user_key, 
				'Password' => $this->host->user_password
			));
			/*
			$request['RateRequestPackageSummary'] = array(
				'TotalWeight' => array('Value' => $total_weight, 'Units' => $weight_unit),
				'TotalInsuredValue' => array('Amount' => $total_price, 'Currency' => $currency->code),
				'PieceCount' => 1,
				'SpecialServicesRequested' => array('SpecialServiceTypes' => 'NON_STANDARD_CONTAINER')
			);
			*/

			$request['ClientDetail'] = array(
				'AccountNumber' => $this->host->account_number, 
				'MeterNumber' => $this->host->meter_number
			);
			
			$request['TransactionDetail'] = array('CustomerTransactionId' => 'LemonStand');
			
			$request['Version'] = array(
				'ServiceId' => 'crs', 
				'Major' => '9', 
				'Intermediate' => '0', 
				'Minor' => '0'
			);
			
			$request['ReturnTransitAndCommit'] = true;
			$request['RequestedShipment']['DropoffType'] = $this->host->pickup_type;
			
			$request['RequestedShipment']['ShipTimestamp'] = time();
					
			$request['RequestedShipment']['Shipper'] = array('Address' => array(
					'StreetLines' => array('Street Address 1'),
					'City' => h($shipping_params->city),
					'StateOrProvinceCode' => ($shipping_params->state && strlen(h($shipping_params->state->code)) >= 2) ? substr(h($shipping_params->state->code), 0, 2) : '',
					'PostalCode' => h($shipping_params->zip_code),
					'CountryCode' => h($shipping_params->country->code)
				)
			);
			
			$request['RequestedShipment']['Recipient'] = array(
				'Address' => array(
					'StreetLines' => array('Street Address 1'),
					'City' => h($city),
					'StateOrProvinceCode' => ($state && strlen(h($state->code)) >=2) ? substr(h($state->code), 0, 2) : '',
					'PostalCode' => h($zip),
					'CountryCode' => h($country->code),
					'Residential' => $is_business ? false : true,
					'ResidentialSpecified' => true
				)
			);
			
			$request['RequestedShipment']['ShippingChargesPayment'] = array(
				'PaymentType' => 'SENDER',
				'Payor' => array(
					'AccountNumber' => $this->host->account_number,
					'CountryCode' => h($shipping_params->country->code)
				)
			);
			
			$request['RequestedShipment']['RateRequestTypes'] = $this->host->quote_type;
			$request['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
			$request['RequestedShipment']['RequestedPackageLineItems'] = array();
			
			if($shipping_params->country->code !== $country->code) {
				$request['RequestedShipment']['CustomsClearanceDetail'] = array(
					'CustomsValue' => array(
						'Currency' => $currency_code,
						'Amount' => round($total_price, 2),
					),
					'DutiesPayment' => array(
						'PaymentType' => 'SENDER',
						'Payor' => array(
							'AccountNumber' => $this->host->account_number,
							'CountryCode' => $shipping_params->country->code
						)
					),
					'Commodities' => array(
						'Name'=>1,
						'NumberOfPieces' => 1,
						'Description' => "items",
						'CountryOfManufacture' => $shipping_params->country->code,
						'Weight' => array(
							'Units' => $weight_unit,
							'Value' => $total_weight
						),
						'Quantity' => $total_item_num,
						'QuantityUnits' => 'pcs',
						'UnitPrice' => array(
							'Currency' => $currency_code,
							'Amount' => round($total_price, 6)
						),
						'CustomsValue' => array(
							'Currency' => $currency_code,
							'Amount' => round($total_price, 2)
						)
					),
					'SpecialServicesRequested' => array(
						'SpecialServiceTypes' => 'SIGNATURE_OPTION',
						'SignatureOptionDetail' => array('OptionType' => $this->host->delivery_confirmation)
					)
				);
			}

			$total_volume = 0;
			
			foreach($cart_items as $cart_item) {
				$total_volume += ($cart_item->product->depth * $cart_item->product->width * $cart_item->product->height) * $cart_item->quantity;
			}

			for($i = 0, $l = ceil($total_weight / $this->host->max_weight); $i < $l; ++$i) {
				if($i == $l - 1) 
					$weight = $total_weight % $this->host->max_weight; // use the remainder weight, rather than the max weight
				else
					$weight = $this->host->max_weight;
				
				if($weight < $this->host->min_weight)
					$weight = $this->host->min_weight;
					
				$divided_volume = pow($total_volume, 1/3) / $l;

				$request['RequestedShipment']['RequestedPackageLineItems'][] = array(
					'SequenceNumber' => $i + 1,
					'Weight' => array(
						'Value' => ceil($weight),
						'Units' => $weight_unit
					),
					'Dimensions' => array(
						'Length' => ceil($divided_volume),
						'Width' => ceil($divided_volume),
						'Height' => ceil($divided_volume),
						'Units' => $dimension_unit
					),
					'InsuredValue' => array(
						'Amount' => round($total_price / $l, 2), # split the price of each package for the insured value
						'Currency' => $currency_code
					)/*,
					'SpecialServicesRequested' => array(
						'SpecialServiceTypes' => 'NON_STANDARD_CONTAINER'
					)*/
				);
			}
			
			$request['RequestedShipment']['PackageCount'] = count($request['RequestedShipment']['RequestedPackageLineItems']);

			$cache_key = md5(serialize($request));
			if (array_key_exists($cache_key, self::$response_cache))
				return self::$response_cache[$cache_key];
			
			$request['RequestedShipment']['ShipTimestamp'] = date('c');

			$response = $client->getRates($request);

			if(!$response || !isset($response->RateReplyDetails) || !count($response->RateReplyDetails) || in_array($response->HighestSeverity, array('ERROR', 'FAILURE'))) {
				if(isset($response->Notifications)) {
					if(is_array($response->Notifications)) {
						$codes = array();
						$messages = array();
						
						foreach($response->Notifications as $notification) {
							$codes[] = $notification->Code;
							$messages[] = $notification->Message;
						}
						
						throw new Phpr_SystemException('Received error codes from FedEx: ' . implode('/', $codes) . '. Message: ' . implode('Message: ', $messages));
					}
					else {
						throw new Phpr_SystemException('Received error code ' . $response->Notifications->Code . ' from FedEx: ' . $response->Notifications->Message);
					}
				}
				else {
					throw new Phpr_SystemException('Invalid response received from FedEx.');
				}
			}

			if (!is_array($response->RateReplyDetails))
				$response->RateReplyDetails = array($response->RateReplyDetails);

			foreach($response->RateReplyDetails as $service) {
				$id = $service->ServiceType;
				
				if(!in_array($id, $allowed_services)
				|| !array_key_exists($id, $all_services))
					continue;
				
				if(is_array($service->RatedShipmentDetails)) {
					$types = array();
					
					foreach($service->RatedShipmentDetails as $rated) {
						$types[$rated->ShipmentRateDetail->RateType] = $rated;
					}
					
					if(isset($types['RATED_' . $this->host->quote_type . '_SHIPMENT'])) {
						$details = isset($types['RATED_' . $this->host->quote_type . '_SHIPMENT']) ? $types['RATED_' . $this->host->quote_type . '_SHIPMENT'] : $types[$this->host->quote_type . '_PACKAGE'];
					}
					// we didn't find the desired quote type, so we'll fall back onto payor account
					else {
						$details = isset($types['PAYOR_ACCOUNT_SHIPMENT']) ? $types['PAYOR_ACCOUNT_SHIPMENT'] : $types['PAYOR_ACCOUNT_PACKAGE'];
					}
				}
				else // we have no control over the rate type
					$details = $service->RatedShipmentDetails;
				
				$total = $details->ShipmentRateDetail->TotalNetCharge->Amount;
				$currency_code = $details->ShipmentRateDetail->TotalNetCharge->Currency;
				if($currency_code == 'UKL')
					$currency_code = 'GBP';

				$total = $currency_converter->convert_from($total, $currency_code);
				if(array_key_exists('DeliveryTimestamp', $service)) {
					$delivery_date = date('Y-m-d', strtotime($service->DeliveryTimestamp));
				}
				else if(array_key_exists('TransitTime', $service)) {
					$delivery_date = date(
						'Y-m-d', strtotime(
							'+' . str_replace(
								array(
									'_', 'eighteen', 'seventeen', 'fifteen', 'fourteen', 'thirteen', 'twelve', 'eleven', 'ten', 'nine', 'eight', 'seven', 'six', 'five', 'four', 'three', 'two', 'one',
								), 
								array(
									' ', 18, 17, 16, 15, 14, 13, 12, 11, 10, 9, 8, 7, 6, 5, 4, 3, 2, 1
								), 
								strtolower($service->TransitTime)
							)
						)
					);
				} 
				else {
					$delivery_date = null;
				}
				
				$option_name = $all_services[$id];

				if(!array_key_exists($option_name, $result))
					$result[$option_name] = array('id' => $id, 'quote' => $total, 'delivery_date' => $delivery_date);
				else
					$result[$option_name]['delivery_date'] = $delivery_date;
			}

			return self::$response_cache[$cache_key] = $result;
		}
	}
