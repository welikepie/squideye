<?

	class Shop_TableRateShipping extends Shop_ShippingType
	{
		/**
		 * Returns information about the shipping type
		 * Must return array with key 'name': array('name'=>'FedEx')
		 * Also the result can contain an optional 'description'
		 * @return array
		 */
		public function get_info()
		{
			return array(
				'name'=>'Table Rate',
				'description'=>'Allows to configure shipping quotes depending on customer location. You do not need any shipping service accounts in order to use this method.'
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
		 * @param mixed $host_obj ActiveRecord object to add fields to
		 * @param string $context Form context. In preview mode its value is 'preview'
		 */
		public function build_config_ui($host_obj, $context = null)
		{
			if ($context == 'preview')
				return;
			
			$host_obj->add_field('rates', 'Rates')->tab('Rates')->renderAs(frm_grid)->gridColumns(array(
				'country'=>array('title'=>'Country Code', 'align'=>'left', 'autocomplete'=>array('type'=>'local', 'tokens'=>$this->get_country_list())), 
				'state'=>array('title'=>'State Code', 'align'=>'left', 'autocomplete'=>array('type'=>'local', 'depends_on'=>'country', 'tokens'=>$this->get_state_list())), 
				'zip'=>array('title'=>'ZIP/Postal Code', 'align'=>'left'), 
				'city'=>array('title'=>'City', 'align'=>'left', 'width'=>'60', 'autocomplete'=>array('type'=>'local', 'tokens'=>array('* - Any city||*'), 'autowidth'=>true)), 
				'min_weight'=>array('title'=>'Min Weight', 'align'=>'right', 'width'=>50), 
				'max_weight'=>array('title'=>'Max Weight', 'align'=>'right', 'width'=>50), 
				'min_volume'=>array('title'=>'Min Volume', 'align'=>'right', 'width'=>50), 
				'max_volume'=>array('title'=>'Max Volume', 'align'=>'right', 'width'=>50), 
				'min_subtotal'=>array('title'=>'Min Subtotal', 'align'=>'right', 'width'=>50), 
				'max_subtotal'=>array('title'=>'Max Subtotal', 'align'=>'right', 'width'=>50), 
				'min_items'=>array('title'=>'Min Items', 'align'=>'right', 'width'=>30), 
				'max_items'=>array('title'=>'Max Items', 'align'=>'right', 'width'=>30), 
				'price'=>array('title'=>'Rate', 'align'=>'right')
			))->gridSettings(array('allow_disable_manual_edit'=>1));
		}
		
		protected function get_country_list()
		{
			$countries = Shop_Country::get_object_list();
			$result = array();
			$result[] = '* - Any country||*';
			foreach ($countries as $country)
				$result[] = $country->code.' - '.$country->name.'||'.$country->code;

			return $result;
		}
		
		protected function get_state_list()
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

		/**
		 * Validates configuration data before it is saved to database
		 * Use host object field_error method to report about errors in data:
		 * $host_obj->field_error('max_weight', 'Max weight should not be less than Min weight');
		 * @param $host_obj ActiveRecord object containing configuration fields values
		 */
		public function validate_config_on_save($host_obj)
		{
			if (!is_array($host_obj->rates) || !count($host_obj->rates))
				$host_obj->field_error('rates', 'Please specify shipping rates.');
				
			$numeric_fields = array(
				'min_weight'=>'Min Weight',
				'max_weight'=>'Max Weight',
				'min_volume'=>'Min Volume',
				'max_volume'=>'Max Volume',
				'min_subtotal'=>'Min Subtotal',
				'max_subtotal'=>'Max Subtotal',
				'min_items'=>'Min Items',
				'max_items'=>'Max Items',
			);

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

			$processed_rates = array();
			$input_rates = $host_obj->rates;
			
			$is_manual_disabled = isset($input_rates['disabled']);
			if ($is_manual_disabled)
				$input_rates = unserialize($input_rates['serialized']);
			
			foreach ($input_rates as $row_index=>&$rates)
			{
				$empty = true;
				foreach ($rates as $value)
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
				$country = $rates['country'] = trim(mb_strtoupper($rates['country']));
				if (!strlen($country))
					$host_obj->field_error('rates', 'Please specify country code. Available codes are: '.implode(', ', $country_codes).'. Line: '.($row_index+1), $row_index, 'country');
				
				if (!array_key_exists($country, $countries) && $country != '*')
					$host_obj->field_error('rates', 'Invalid country code. Available codes are: '.implode(', ', $country_codes).'. Line: '.($row_index+1), $row_index, 'country');
					
				/*
				 * Validate state
				 */
				if ($country != '*')
				{
					$country_obj = $countries[$country];
					$country_states = $states[$country_obj->id];
					$state_codes = array_keys($country_states);

					$state = $rates['state'] = trim(mb_strtoupper($rates['state']));
					if (!strlen($state))
						$host_obj->field_error('rates', 'Please specify state code. State codes, available for '.$country_obj->name.' are: '.implode(', ', $state_codes).'. Line: '.($row_index+1), $row_index, 'state');

					if (!in_array($state, $state_codes) && $state != '*')
						$host_obj->field_error('rates', 'Invalid state code. State codes, available for '.$country_obj->name.' are: '.implode(', ', $state_codes).'. Line: '.($row_index+1), $row_index, 'state');
				} else {
					$state = $rates['state'] = trim(mb_strtoupper($rates['state']));
					if (!strlen($state) || $state != '*')
						$host_obj->field_error('rates', 'Please specify state code as wildcard (*) to indicate "Any state in any country" condition. Line: '.($row_index+1), $row_index, 'state');
				}
				
				/*
				 * Process ZIP code
				 */
				
				$rates['zip'] = trim(mb_strtoupper($rates['zip']));

				/*
				 * Validate numeric fields
				 */

				$prev_value = null;
				$prev_code = null;
				$index = 0;
				foreach ($numeric_fields as $code=>$name)
				{
					$value = $rates[$code] = trim(mb_strtoupper($rates[$code]));
					
					$value_specified = strlen($value);
					if ($value_specified)
					{
					 	if (!Core_Number::is_valid($value))
							$host_obj->field_error('rates', 'Invalid numeric value in column '.$name.'. Line: '.($row_index+1), $row_index, $code);
							
						$rates[$code] = (float)$value;
					}

					if ($index % 2)
					{
						if ($value_specified && !strlen($prev_value))
							$host_obj->field_error('rates', 'Please specify both minimum and maximum value. Line: '.($row_index+1), $row_index, $prev_code);
							
						if (!$value_specified && strlen($prev_value))
							$host_obj->field_error('rates', 'Please specify both minimum and maximum value. Line: '.($row_index+1), $row_index, $code);
							
						if ($prev_value > $value)
							$host_obj->field_error('rates', 'Minimum value must be less than maximum value. Line: '.($row_index+1), $row_index, $code);
					}

					$prev_value = $rates[$code];
					$prev_code = $code;
					$index++;
				}
				
				/*
				 * Validate price
				 */
				
				$price = $rates['price'] = trim(mb_strtoupper($rates['price']));
				if (!strlen($price))
					$host_obj->field_error('rates', 'Please specify shipping rate. Line: '.($row_index+1), $row_index, 'price');

			 	if (!Core_Number::is_valid($price))
					$host_obj->field_error('rates', 'Invalid numeric value in column Rate. Line: '.($row_index+1), $row_index, 'price');

				$processed_rates[] = $rates;
			}

			if (!count($processed_rates))
				$host_obj->field_error('rates', 'Please specify shipping rates.');
				
			$host_obj->rates = $processed_rates;
		}
		
		/**
		 * Determines whether a list of countries should be displayed in the 
		 * configuration form. For most payment methods the country list should be displayed.
		 * But for the table rate shipping countries are configured using the table content.
		 */
		public function config_countries()
		{
			return true;
		}

 		public function get_quote($parameters) 
		{
			extract($parameters);

			$country = Shop_Country::create(true)->find($country_id);
			if (!$country)
				return null;

			$state = null;
			if (strlen($state_id))
				$state = Shop_CountryState::create(true)->find($state_id);
				
			$country_code = $country->code;
			$state_code = $state ? mb_strtoupper($state->code) : '*';
			
			$zip = trim(mb_strtoupper(str_replace(' ', '', $zip)));
			
			$city = str_replace('-', '', str_replace(' ', '', trim(mb_strtoupper($city))));
			if (!strlen($city))
				$city = '*';

			/*
			 * Find shipping rate
			 */

			$rate = null;

			foreach ($host_obj->rates as $row)
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

				// if ($row['zip'] != $zip && $row['zip'] != '' && $row['zip'] != '*')
				// 	continue;

				$row_city = isset($row['city']) && strlen($row['city']) ? str_replace('-', '', str_replace(' ', '', mb_strtoupper($row['city']))) : '*';
				if ($row_city != $city && $row_city != '*')
					continue;

				if (strlen($row['min_weight']) && strlen($row['max_weight']))
				{
					if (!($row['min_weight'] <= $total_weight && $row['max_weight'] >= $total_weight))
						continue;
				}

				if (strlen($row['min_volume']) && strlen($row['max_volume']))
				{
					if (!($row['min_volume'] <= $total_volume && $row['max_volume'] >= $total_volume))
						continue;
				}

				if (strlen($row['min_subtotal']) && strlen($row['max_subtotal']))
				{
					if (!($row['min_subtotal'] <= $total_price && $row['max_subtotal'] >= $total_price))
						continue;
				}

				if (strlen($row['min_items']) && strlen($row['max_items']))
				{
					if (!($row['min_items'] <= $total_item_num && $row['max_items'] >= $total_item_num))
						continue;
				}
				
				$rate = $row['price'];
				break;
			}

			return $rate;
		}
	}

?>