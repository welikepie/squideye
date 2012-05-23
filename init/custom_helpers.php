<?php

	/*
	 * String helpers
	 */

	function h($Str)
	{
		return Phpr_Html::encode($Str);
	}

	function plainText($Str)
	{
		return Phpr_Html::plainText($Str);  
	}

	/*
	 * Date helpers
	 */

	/**
	 * @return string
	 */
	function displayDate( $Date, $Format = '%x' )
	{
		return Phpr_Date::display( $Date, $Format );
	}
	
	/**
	 * @return Phpr_DateTime
	 */
	function gmtNow()
	{
		return Phpr_DateTime::gmtNow();
	}
	
	/*
	 * Other helpers
	 */

	function traceLog($Str, $Listener = 'INFO')
	{
		if (Phpr::$traceLog)
			Phpr::$traceLog->write($Str, $Listener);
	}
	
	function flash()
	{
		return Backend_Html::flash();
	}
	
	function post($Name, $Default = null)
	{
		return Phpr::$request->post($Name, $Default);
	}
	
	function post_array_item( $ArrayName, $Name, $Default = null )	
	{
		return Phpr::$request->post_array_item($ArrayName, $Name, $Default);
	}
	
	/*
	 * Form helpers
	 */
	
	function option_state($CurrentValue, $SelectedValue)
	{
		return PHpr_Form::optionState( $SelectedValue, $CurrentValue );
	}
	
	function checkbox_state($Value)
	{
		return Phpr_Form::checkboxState($Value);
	}
	
	function radio_state($Value)
	{
		return Phpr_Form::checkboxState($Value);
	}
	
	/*
	 * URL helpers
	 */
	
	/**
	 * Returns an URL of a specified resource relative to the LemonStand domain root
	 */
	function root_url($value = '/', $add_host_name_and_protocol = false, $protocol = null)
	{
		return Phpr_Url::root_url($value, $add_host_name_and_protocol, $protocol);
	}