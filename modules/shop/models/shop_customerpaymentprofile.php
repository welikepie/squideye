<?php

	/**
	 * Represents a customer payment profile.
	 * Actual data is stored on a payment gateway,
	 * so objects of this class keep only identifiers
	 * of gateway specific payment profiles.
	 */
	class Shop_CustomerPaymentProfile extends Db_ActiveRecord
	{
		public $table_name = 'shop_customer_payment_profiles';

		public $encrypted_columns = array('profile_data', 'cc_four_digits_num');

		public static function create()
		{
			return new self();
		}
		
		public function before_save($deferred_session_key = null)
		{
			$this->profile_data = serialize($this->profile_data);
			$this->cc_four_digits_num = substr($this->cc_four_digits_num, -4);
		}
		
		protected function after_fetch()
		{
			if (strlen($this->profile_data))
			{
				try
				{
					$this->profile_data = @unserialize($this->profile_data);
				}
				catch (exception $ex) {
					$this->profile_data = array();
				}
			} else
				$this->profile_data = array();
		}

		/**
		 * Sets the gateway specific profile information and 4 last digits of the credit card number (PAN)
		 * and saves the profile to the database
		 * @param mixed $profile_data Profile data
		 * @param string $cc_four_digits_num Last four digits of the CC number
		 */
		public function set_profile_data($profile_data, $cc_four_digits_num)
		{
			$this->profile_data = $profile_data;
			$this->cc_four_digits_num = $cc_four_digits_num;
			$this->save();
		}
		
		/**
		 * Sets the 4 last digits of the credit card number (PAN)
		 * and saves the profile to the database
		 * @param string $cc_four_digits_num Last four digits of the CC number
		 */
		public function set_cc_num($cc_four_digits_num)
		{
			$this->cc_four_digits_num = $cc_four_digits_num;
			$this->save();
		}
	}
	
?>