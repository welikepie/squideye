<?php

	class Users_Module extends Core_ModuleBase
	{
		/**
		 * Creates the module information object
		 * @return Core_ModuleInfo
		 */
		protected function createModuleInfo()
		{
			return new Core_ModuleInfo(
				"Users",
				"LemonStand user management",
				"Limewheel Creative Inc." );
		}
	}
?>