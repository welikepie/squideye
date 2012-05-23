<?php

	/**
	 * PHP Road
	 *
	 * PHP application framework
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov, Andy Chentsov
	 * @since		Version 1.0
	 * @filesource
	 */

	/**
	 * PHP Road module class
	 *
	 * This class assists in working with the PHP Road modules.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Module
	{
		/**
		 * Returns a module directory location.
		 * @param string $Module Specifies a module name, case-sensitive.
		 * @return mixed Returns a module directory path. If the module specified could not be located returns null.
		 */
		public static function findModule( $Module )
		{
			$Module = strtolower($Module);
			foreach ( array(PATH_APP, PATH_SYSTEM) as $basePath )
				if ( file_exists("$basePath/modules/$Module") )
					return "$basePath/modules/$Module";

			return null;
		}
	}

?>