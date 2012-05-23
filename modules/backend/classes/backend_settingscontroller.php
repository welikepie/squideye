<?php

	/**
	 * Back-end settings controller generic class
	 */
	class Backend_SettingsController extends Backend_Controller
	{
		public function __construct()
		{
			parent::__construct();
			
			$this->app_module = 'system';
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
			$this->app_page = 'settings';
		}
	}
	
?>