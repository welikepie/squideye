<?

	function format_currency($num, $decimals = 2)
	{
		return Shop_CurrencySettings::format_currency($num, $decimals);
	}
	
	function customer_logout($redirect = null)
	{
		Phpr::$frontend_security->logout($redirect);
	}
	
	function tax_incl_label($order = null)
	{
		$display_tax_included = Shop_CheckoutData::display_prices_incl_tax($order);
		if (!$display_tax_included)
			return null;
		
		$config = Shop_ConfigurationRecord::get();

		if (!$order)
		{
			$shipping_info = Shop_CheckoutData::get_shipping_info();
			$shipping_country_id = $shipping_info->country;
			$shipping_state_id = $shipping_info->state;
		} else
		{
			$shipping_country_id = $order->shipping_country_id;
			$shipping_state_id = $order->shipping_state_id;
		}
		
		if (!$config->tax_inclusive_country_id)
			return $config->tax_inclusive_label;

		if ($config->tax_inclusive_country_id != $shipping_country_id)
			return null;
			
		if (!$config->tax_inclusive_state_id)
			return $config->tax_inclusive_label;
			
		if ($config->tax_inclusive_state_id != $shipping_state_id)
			return null;

		return $config->tax_inclusive_label;
	}

?>