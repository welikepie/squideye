<?php

	class Core_Module extends Core_ModuleBase
	{
		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Core",
				"LemonStand core module",
				"Limewheel Creative Inc." );
		}

		public function subscribeEvents()
		{
			Backend::$events->addEvent('onLogin', $this, 'onUserLogin');
		}
		
		public function onUserLogin()
		{
			$handler_path = PATH_APP.'/handlers/login.php';
			if (file_exists($handler_path))
				include $handler_path;
		}
		
		/**
		 * Returns a list of email template variables provided by the module.
		 * The method must return an array of section names, variable names, 
		 * descriptions and demo-values:
		 * array('Shop variables'=>array(
		 * 	'order_total'=>array('Outputs order total value', '$99.99')
		 * ))
		 * @return array
		 */
		public function listEmailVariables()
		{
			return array(
				'System variables'=>array(
					'recipient_email'=>array('Outputs the email recipient email address', '{recipient_email}')
				)
			);
		}
		
		public function listSettingsItems()
		{
			$eula_info = Core_EulaInfo::get();
			$eula_update_str = null;
			if ($eula_info->accepted_on)
				$eula_update_str = sprintf(' Last updated on %s.', Phpr_Date::display($eula_info->accepted_on));
				
			$user = Phpr::$security->getUser();
			$is_unread = Core_EulaInfo::is_unread($user->id);

			return array(
				array(
					'icon'=>'/modules/core/resources/images/new_page.png', 
					'title'=>'License Agreement', 
					'url'=>'/core/viewlicenseagreement',
					'description'=>'View LemonStand End User License Agreement.'.$eula_update_str,
					'sort_id'=>200,
					'section'=>'System',
					'class'=>($is_unread ? 'unread' : null)
				)
			);
		}
	}
?>