<?php

	/*
	 * Backend module events object
	 */

	Backend::$events = new Backend_Events();

	/*
	 * Load and initialize modules
	 */

	Core_ModuleManager::listModules();
	
	Backend::$events->fireEvent('core:onInitialize');

	/**
	 * Custom functions
	 */

	/**
	 * Outputs a backend button.
	 * This function is shortcut for Backend_Html::button helper method.
	 */
	function backend_button($caption, $attributes = array(), $ajaxHandler=null, $ajaxParams = null, $formElement = null)
	{
		return Backend_Html::button($caption, $attributes, $ajaxHandler, $ajaxParams, $formElement);
	}
	
	/**
	 * Outputs a backend AJAX button.
	 * This function is shortcut for Backend_Html::ajaxButton helper method.
	 */
	function backend_ajax_button($caption, $ajaxHandler, $attributes = array(), $ajaxParams = null)
	{
		return Backend_Html::ajaxButton($caption, $ajaxHandler, $attributes, $ajaxParams);
	}
	
	/**
	 * Outputs a control panel button.
	 * This function is shortcut for Backend_Html::ctr_button helper method.
	 */
	function backend_ctr_button($caption, $button_class, $attributes = array(), $ajaxHandler=null, $ajaxParams = null, $formElement = null)
	{
		return Backend_Html::ctr_button($caption, $button_class, $attributes, $ajaxHandler, $ajaxParams, $formElement);
	}
	
	/**
	 * Outputs a control panel AJAX button.
	 * This function is shortcut for Backend_Html::ctr_ajaxButton helper method.
	 */
	function backend_ctr_ajax_button($caption, $button_class, $ajaxHandler, $attributes = array(), $ajaxParams = null)
	{
		return Backend_Html::ctr_ajaxButton($caption, $button_class, $ajaxHandler, $attributes, $ajaxParams);
	}
	
	/**
	 * Returns an URL relative to the U-Turn CMS back-end root
	 * This function is shortcut for Backend_Html::url helper method.
	 */
	function url($url)
	{
		return Backend_Html::url($url);
	}
	
	/**
	 * Returns word "even" each even call for a specified counter.
	 * Example: <tr class="<?= zebra('customer') ?>">
	 * This function is shortcut for Backend_Html::zebra helper method.
	 */
	function zebra($counterName)
	{
		return Backend_Html::zebra($counterName);
	}
	
	/**
	 * Returns module version string
	 * @param string $moduleId Specifies a module identifier
	 * @return string
	 */
	function module_build($moduleId)
	{
		return Core_Version::getModuleVersionCached($moduleId);
	}
	
	/**
	 * Returns the onClick handler for redirecting a browser to a specified URL
	 * Example: <td <?= click_link('http://www.my-site.com') ?>>
	 * This function is shortcut for Backend_Html::click_link helper method.
	 */
	function click_link($url)
	{
		return Backend_Html::click_link($url);
	}
	
	/**
	 * Returns the onClick handler code for redirecting a browser to an URL
	 * which depends on whether the ALT key was pressed
	 * Example: <td onclick="<?= alt_click_link('http://www.my-site.com', 'http://www.my-site2.com') ?>">
	 * This function is shortcut for Backend_Html::alt_click_link helper method.
	 */
	function alt_click_link($url, $alt_url)
	{
		return Backend_Html::alt_click_link($url, $alt_url);
	}
?>