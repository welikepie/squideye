<?

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
	 * PHP Road Component helper
	 *
	 * This class contains functions for working with PHP Road views.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_View
	{
		private static $_blockstack = array();
		private static $_blocks = array();

		private static $_errorBlocks = array();

		/**
		 * Returns the JavaScript inclusion tag for the PHP Road script file.
		 * The PHP Road java script files are situated in the PHP Road javascript folder.
		 * By default this function creates a link to the application bootstrap file 
		 * that outputs the requested script. You may speed up the resource request
		 * by providing a direct URL to the PHP Road javascript folder in the 
		 * configuration file: 
		 * $CONFIG['JAVASCRIPT_URL'] = 'www.my_company_com/phproad/javascript';
		 * @param mixed $Name Specifies a name of the script file to include.
		 * Use the 'defaults' name to include the minimal required PHP Road script set.
		 * If this parameter is omitted the 'defaults' value is used.
		 * Also you may specify a list of script names as array.
		 * @return string
		 */
		public static function includeJavaScript( $Name = 'defaults', $version_mark = null )
		{
			if ( !is_array($Name) )
				$Name = array( $Name );

			$result = null;
			foreach ( $Name as $ScriptName )
			{
				$ScriptName = urlencode($ScriptName);

				if ( $ScriptName == 'defaults' )
				{
					foreach ( Phpr_Response::$defaultJsScripts as $DefaultScript )
						$result .= "<script type=\"text/javascript\" src=\"phproad/javascript/$DefaultScript?$version_mark\"></script>\n";
				} else
					$result .= "<script type=\"text/javascript\" src=\"phproad/javascript/".$ScriptName."\"></script>\n";
			}

			return $result;
		}

		/**
		 * Begins the layout block.
		 * @param string $Name Specifies the block name.
		 */
		public static function begin_block($name) {
			array_push(self::$_blockstack, $name);
			ob_start();
		}
		
		/**
		 * @deprecated
		 */
		public static function beginBlock($name) {
			return self::begin_block($name);
		}
		
		/**
		 * Closes the layout block.
		 * @param boolean $append Indicates that the new content should be appended to the existing block content.
		 */
		public static function end_block($append = false) {
			if ( !count(self::$_blockstack) )
				throw new Phpr_SystemException( "Invalid layout blocks nesting" );

			$Name = array_pop(self::$_blockstack);
			$Contents = ob_get_clean();

			if ( !isset(self::$_blocks[$Name]) )
				self::$_blocks[$Name] = $Contents;
			else 
				if ($append)
					self::$_blocks[$Name] .= $Contents;

			if ( !count(self::$_blockstack) && (ob_get_length() > 0) )
				ob_end_clean();
		}
		 
		/**
		 * @deprecated
		 */
		public static function endBlock($append = false) {
			return self::end_block($append);
		}

		/**
		 * Sets a content of the layout block.
		 * @param string $Name Specifies the block name.
		 * @param string $Content Specifies the block content.
		 * 
		 */
		public static function setBlock( $Name, $Content )
		{
			self::beginBlock($Name);
			echo $Content;
			self::endBlock();
		}

		/**
		 * Appends a content of the layout block.
		 * @param string $Name Specifies the block name.
		 * @param string $Content Specifies the block content.
		 * 
		 */
		public static function appendBlock( $Name, $Content )
		{
			if ( !isset(self::$_blocks[$Name]) )
				self::$_blocks[$Name] = null;

			self::$_blocks[$Name] .= $Content;
		}

		/**
		 * Returns the layout block contents and deletes the block from memory.
		 * @param string $Name Specifies the block name.
		 * @param string $Default Specifies a default block value to use if the block requested is not exists.
		 * @return string
		 */
		public static function block( $Name, $Default = null )
		{
			$Result = self::getBlock( $Name, $Default );

			unset( self::$_blocks[$Name] );

			return $Result;
		}

		/**
		 * Returns the layout block contents but not deletes the block from memory.
		 * @param string $Name Specifies the block name.
		 * @param string $Default Specifies a default block value to use if the block requested is not exists.
		 * @return string
		 */
		public static function getBlock( $Name, $Default = null )
		{
			if ( !isset(self::$_blocks[$Name]) )
				return  $Default;

			$Result = self::$_blocks[$Name];

			return $Result;
		}

		/**
		 * Returns an error message.
		 * @param string $Message Specifies the error message. If this parameter is omitted, the common 
		 * validation message will be returned.
		 * @return string
		 */
		public static function showError( $Message = null )
		{
			if ( $Message === null )
			{
				$Controller = self::getCurrentController();
				if ( is_null($Controller) )
					return null;

				$Message = Phpr_Html::encode($Controller->validation->errorMessage);
			}

			if ( strlen($Message) )
				return $Message;
		}

		/**
		 * Returns a current controller
		 * @return Phpr_ControllerBase
		 */
		private static function getCurrentController()
		{
			if ( Phpr_Component::$current !== null )
				return Phpr_Component::$current;

			if ( Phpr_Controller::$current !== null )
				return Phpr_Controller::$current;
		}
	}