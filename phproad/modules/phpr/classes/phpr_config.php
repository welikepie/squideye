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
	 * PHP Road configuration base class
	 *
	 * Loads the configuration from the application configuration files.
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$Config.
	 *
	 * @see Phpr
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Config implements ArrayAccess
	{
		protected $_configuration = array();

		/**
		 * Creates a new config instance and load the configuration files.
		 */
		public function __construct()
		{
			$this->loadConfiguration();
		}

		/**
		 * Loads the configuration and populates the internal array.
		 * Override this method in the inherited configuration classes.
		 */
		protected function loadConfiguration()
		{
			$configFound = false;
			global $APP_CONF;

			// Define the configuration array
			//
			$CONFIG = array();
			
			if (isset($APP_CONF) && is_array($APP_CONF))
				$CONFIG = $APP_CONF;

			// Look in the application config directory
			//
			$path = PATH_APP."/config";
			if ( file_exists($path) && is_dir($path) )
			{
				if ( $dh = opendir($path) )
				{
					while ( ($file = readdir($dh)) !== false )
						if ( $file != '.' && $file != '..' && (pathinfo($file, PATHINFO_EXTENSION) == PHPR_EXT) )
						{
							$filePath = $path."/".$file;
							if ( !is_dir($filePath) )
							{
								include ($filePath);
								$configFound = true;
							}
						}

					closedir($dh);
				}
			}

			if ( $configFound ) 
			{
				$this->_configuration = $CONFIG;
				return;
			}

			// Look in the application parent directory
			//
			$path = realpath(PATH_APP."/../config.php");
			if ( $path && file_exists($path) )
				include($path);

			$this->_configuration = $CONFIG;
		}

		/**
		 * Returns a value of the configuration option with the specified name. Allows to specify the default option value.
		 * @param string $OptionName Specifies the name of option to return.
		 * @param mixed $Default Optional. Specifies default option value.
		 * @return mixed Returns the option value or default value. If option does not exist returns null.
		 */
		public function get( $OptionName, $Default = null )
		{
			if ( isset($this->_configuration[$OptionName]) )
				return $this->_configuration[$OptionName];

			return $Default;
		}

		/*
		 * ArrayAccess implementation
		 */

		public function offsetExists($offset)
		{
			return isset($this->_configuration[$offset]);
		}

		public function offsetGet($offset)
		{
			if ( isset($this->_configuration[$offset]) )
				return $this->_configuration[$offset];
			else
				return null;
		}

		public function offsetSet($offset, $value) {}

		public function offsetUnset($offset) {}
	}

?>