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
	 * PHP Road Request Class
	 *
	 * This class prepares the input data and provides information about the web request.
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$request.
	 *
	 * @see Phpr
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Request
	{
		private $_ip = null;
		private $_language = null;
		private $_cachedEvendParams = null;
		private $_cachedUri = null;
		private $_subdirectory = null;

		protected $_remoteEventIndicator = 'HTTP_PHPR_REMOTE_EVENT';
		protected $_postbackIndicator = 'HTTP_PHPR_POSTBACK';

		public $get_fields = null;

		/**
		 * Creates a new Phpr_Request instance.
		 * Do not create the Request objects directly. Use the Phpr::$request object instead.
		 * @see Phpr
		 */
		public function __construct()
		{
			$this->preprocessGlobals();
		}

		/**
		 * Returns a value of the POST variable. 
		 * If the variable with specified name does not exist, returns null or a value specifies in the $Default parameter.
		 * @param string $Name Specifies a variable name.
		 * @param mixed $Default Specifies a default value.
		 * @return mixed
		 */
		public function post( $Name, $Default = null )
		{
			if (array_key_exists($Name.'_x', $_POST) && array_key_exists($Name.'_y', $_POST))
				return true;

			if ( !array_key_exists($Name, $_POST) )
				return $Default;

			return $_POST[$Name];
		}

		/**
		 * Finds an array in the POST variable by its name and then finds and returns the array element by its key.
		 * If the array or the element key do not exist, returns null or a value specifies in the $Default parameter.
		 * @param string $ArrayName Specifies the array name.
		 * @param string $Name Specifies the array element key name.
		 * @param mixed $Default Specifies a default value.
		 * @return mixed
		 */
		public function post_array_item( $ArrayName, $Name, $Default = null )
		{
			if ( !array_key_exists($ArrayName, $_POST) )
				return $Default;

			if ( !array_key_exists($Name, $_POST[$ArrayName]) )
				return $Default;

			return $_POST[$ArrayName][$Name];
		}
		
		/**
		 * Returns a value of the COOKIE variable. 
		 * If the variable with specified name does not exist, returns null;
		 * @param string $Name Specifies a variable name.
		 * @return mixed
		 */
		public function cookie( $Name )
		{
			if ( !isset($_COOKIE[$Name]) )
				return null;

			return $_COOKIE[$Name];
		}

		/**
		 * Returns a name of the User Agent.
		 * If user agent data is not availale returns null;
		 * @return mixed.
		 */
		public function getUserAgent()
		{
			return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
		}

		/**
		 * Determines whether the remote event handling requested.
		 * @return boolean.
		 */
		public function isRemoteEvent()
		{
			return isset($_SERVER[$this->_remoteEventIndicator]);
		}
		
		/**
		 * Returns SSL Session Id value.
		 * @return string.
		 */
		public function getSslSessionId()
		{
			if (isset($_SERVER["SSL_SESSION_ID"]))
				return $_SERVER["SSL_SESSION_ID"];
				
			return null;
		}

		/**
		 * Determines whether the page is loaded in response to a client postback.
		 * @return boolean.
		 */
		public function isPostBack()
		{
			return isset($_SERVER[$this->_postbackIndicator]);
		}

		/**
		 * Returns the visitor IP address.
		 * @return string
		 */
		public function getUserIp()
		{
			if ( $this->_ip !== null )
				return $this->_ip;

			$ipKeys = Phpr::$config->get('REMOTE_IP_HEADERS', array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'));
			foreach ( $ipKeys as $ipKey )
			{
				if ( isset($_SERVER[$ipKey]) && strlen($_SERVER[$ipKey]) )
				{
					$this->_ip = $_SERVER[$ipKey];
					break;
				}
			}

			if ( strlen( strstr($this->_ip, ',') ) )
			{
				$ips = explode(',', $this->_ip);
				$this->_ip = trim(reset($ips));
			}
				
			if ($this->_ip == '::1')
				$this->_ip = '127.0.0.1';

			return $this->_ip;
		}

		/**
		 * Returns the visitor language preferences.
		 * @return string
		 */
		public function gerUserLanguage()
		{
			if ( $this->_language !== null )
				return $this->_language;

			if ( !array_key_exists('HTTP_ACCEPT_language', $_SERVER) )
				return null;

			$languages = explode( ",", $_SERVER['HTTP_ACCEPT_language'] );
			$language = $languages[0];

			if ( ($pos = strpos($language, ";")) !== false )
				$language = substr( $language, 0, $pos );

			return $this->_language = str_replace( "-", "_", $language );
		}
		
		/**
		 * Returns a subdirectory path, starting from the server 
		 * root directory to LemonStand directory root.
		 * Example. LemonStand installed to the subdirectory /lemonstand of a domain
		 * Then the method will return the '/subdirectory/' string
		 */
		public function getSubdirectory()
		{
			if ($this->_subdirectory !== null)
				return $this->_subdirectory;
				
			$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
			
			$uri = $this->getRequestUri();
			$path = $this->getField($request_param_name);

			$uri = urldecode($uri);
			$uri = preg_replace('|/\?(.*)$|', '/', $uri);

			$pos = strpos($uri, '?');
			if ($pos !== false)
				$uri = substr($uri, 0, $pos);

			$pos = strpos($uri, '/&');
			if ($pos !== false)
				$uri = substr($uri, 0, $pos+1);
			
			$path = mb_strtolower($path);
			$uri = mb_strtolower($uri);

			$pos = mb_strrpos($uri, $path);
			$subdir = '/';
			if ($pos !== false && $pos == mb_strlen($uri)-mb_strlen($path))
				$subdir = mb_substr($uri, 0, $pos).'/';
				
			if (!strlen($subdir))
				$subdir = '/';
				
			return $this->_subdirectory = $subdir;
		}

		/**
		 * Returns the URL of the current request
		 */
		public function getRequestUri()
		{
			$provider = Phpr::$config->get( "URI_PROVIDER", null );

			if ( $provider !== null )
				return getenv( $provider );
			else
			{
				// Pick the provider from the server variables
				//
				$providers = array( 'REQUEST_URI', 'PATH_INFO', 'ORIG_PATH_INFO' );
				foreach ( $providers as $provider )
				{
					$val = getenv( $provider );
					if ( $val != '' )
						return $val;
				}
			}
			
			return null;
		}

		/**
		 * Returns the URI of the current request relative to the LemonStand root directory.
		 * @param bool $Routing Determines whether the Uri is requested for the routing process
		 * @return string
		 */
		public function getCurrentUri( $Routing = false )
		{
			global $bootstrapPath;

			if ( !$Routing && $this->_cachedUri !== null )
				return $this->_cachedUri;

			$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
			$bootstrapPathBase = pathinfo($bootstrapPath, PATHINFO_BASENAME);
			$URI = $this->getField($request_param_name);

			// Postprocess the URI
			//
			if ( strlen($URI) )
			{
				if ( ( $pos = strpos($URI, '?') ) !== false )
					$URI = substr( $URI, 0, $pos );

				if ( $URI{0} == '/' ) $URI = substr( $URI, 1 );

				$len = strlen($bootstrapPathBase);
				if ( substr($URI, 0, $len) == $bootstrapPathBase )
				{
					$URI = substr($URI, $len);
					if ( $URI{0} == '/' ) $URI = substr( $URI, 1 );
				}

				$len = strlen($URI);
				if ($len > 0 && $URI{$len-1} == '/' ) $URI = substr( $URI, 0, $len-1 );
			}

			$URI = "/".$URI;

			if ( $Routing )
			{
				// $DocRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : null;
				// if ( strlen($DocRoot) )
				// {
				// 	if ( strpos(PATH_APP, $DocRoot) == 0 && strcmp(PATH_APP, $DocRoot) != 0 )
				// 	{
				// 		$dirName = substr( PATH_APP, strlen($DocRoot) );
				// 		if ( strlen($dirName) )
				// 		{
				// 			$URI = str_replace($dirName.'/', '', $URI);
				// 		}
				// 	}
				// }
				// 
				// $URI = str_replace('test/', '', $URI);
			} else
				$this->_cachedUri = $URI;

			return $URI;
		}

		/**
		 * Cleans the _POST and _COOKIE data and unsets the _GET data.
		 * Replaces the new line charaters with \n.
		 */
		private function preprocessGlobals()
		{
			// Unset the global variables
			//
			$this->get_fields = $_GET;
			
			$this->unsetGlobals( $_GET );
			$this->unsetGlobals( $_POST );
			$this->unsetGlobals( $_COOKIE );
			
			// Remove magic quotes
			//
			if (ini_get('magic_quotes_gpc') || Phpr::$config->get('REMOVE_GPC_SLASHES'))
			{
				array_walk_recursive($_GET, array('Phpr_Request', 'array_strip_slashes')); 
			    array_walk_recursive($_POST, array('Phpr_Request', 'array_strip_slashes')); 
			    array_walk_recursive($_COOKIE, array('Phpr_Request', 'array_strip_slashes'));
			}

			// Clear the _GET array
			//
			$_GET = array();

			// Clean the POST and COOKIE data
			//
			$this->cleanupArray( $_POST );
			$this->cleanupArray( $_COOKIE );
		}
		
		public function get_value_array($name, $default = array())
		{
			if (array_key_exists($name, $this->get_fields))
				return $this->get_fields[$name];

			if (!isset($_SERVER['QUERY_STRING']))
				return $default;

			$vars = explode('&', $_SERVER['QUERY_STRING']);

			$result = array();
			foreach ($vars as $var_data)
			{
				$var_data = urldecode($var_data);

				$var_parts = explode('=', $var_data);
				if (count($var_parts) == 2)
				{
					if ($var_parts[0] == $name.'[]' || $var_parts[0] == $name.'%5B%5D')
						$result[] = $var_parts[1];
				}
			}
			
			if (!count($result))
				return $default;
				
			return $result;
		}
		
		public static function array_strip_slashes(&$value)
		{
			$value = stripslashes($value); 
		}
		
		/**
		 * Returns a GET parameter value
		 */
		public function getField($name, $default = false)
		{
			return array_key_exists($name, $this->get_fields) ? $this->get_fields[$name] : $default;
		}

		/**
		 * Unsets the global variables created with from the POST, GET or COOKIE data.
		 * @param array &$Array The array containing a list of variables to unset.
		 */
		private function unsetGlobals( &$Array )
		{
			if ( !is_array($Array) )
				unset( $$Array );
			else
				foreach ( $Array as $VarName => $VarValue )
					unset($$VarName);
		}

		/**
		 * Check the input array key for invalid characters and adds slashes.
		 * @param string $Key Specifies the key to process.
		 * @return string
		 */
		private function cleanupArrayKey( $Key )
		{
			if ( !preg_match("/^[0-9a-z:_\/-\{\}|]+$/i", $Key) )
			{
				return null;
//				throw new Phpr_SystemException( "Invalid characters in the input data key: $Key" );
			}

			return get_magic_quotes_gpc() ? $Key : addslashes($Key);
		}

		/**
		 * Fixes the new line characters in the specified value.
		 * @param mixed $Value Specifies a value to process.
		 * return mixed
		 */
		private function cleanupArrayValue( $Value )
		{
			if ( !is_array($Value) )
				return preg_replace("/\015\012|\015|\012/", "\n", $Value);

			$Result = array();
			foreach ( $Value as $VarName => $VarValue )
				$Result[$VarName] = $this->cleanupArrayValue($VarValue);

			return $Result;
		}

		/**
		 * Cleans the unput array keys and values.
		 * @param array &$Array Specifies an array to clean.
		 */
		private function cleanupArray( &$Array )
		{
			if ( !is_array($Array) )
				return;

			foreach( $Array as $VarName => &$VarValue)
			{
				if (is_array($VarValue))
					$this->cleanupArray( $VarValue );
				else
					$Array[$this->cleanupArrayKey($VarName)] = $this->cleanupArrayValue($VarValue);
			}
		}

		/**
		 * @ignore
		 * Returns a list of the event parameters, or a specified parameter value.
		 * This method is used by the PHP Road internally.
		 *
		 * @param string $Name Optional name of parameter to return.
		 * @return mixed
		 */
		public function getEventParams( $Name = null )
		{
			if ( $this->_cachedEvendParams == null )
			{
				$this->_cachedEvendParams = array();

				if ( isset($_POST['phpr_handler_params']) )
				{
					$pairs = explode( '&', $_POST['phpr_handler_params'] );
					foreach ($pairs as $pair)
					{
						$parts = explode( "=", urldecode($pair) );
						$this->_cachedEvendParams[$parts[0]] = $parts[1];
					}
				}
			}

			if ( $Name === null )
				return $this->_cachedEvendParams;

			if ( isset($this->_cachedEvendParams[$Name]) )
				return $this->_cachedEvendParams[$Name];

			return null;
		}

		public function getReferer($Detault = null)
		{
			if ( isset($_SERVER['HTTP_REFERER']) )
				return $_SERVER['HTTP_REFERER'];

			return $Detault;
		}

		public function getRequestMethod()
		{
			if (isset($_SERVER['REQUEST_METHOD']))
				return strtoupper($_SERVER['REQUEST_METHOD']);
				
			return null;
		}

		public function getCurrentUrl()
		{
			$protocol = $this->protocol();
			$port = ($_SERVER["SERVER_PORT"] == "80") ? ""
				: (":".$_SERVER["SERVER_PORT"]);
				
			return $protocol."://".$_SERVER['SERVER_NAME'].$port.$_SERVER['REQUEST_URI'];
		}
		
		public function protocol()
		{
			if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https')
				$s = 's';
			else
				$s = (empty($_SERVER["HTTPS"]) || ($_SERVER["HTTPS"] === 'off')) ? '' : 's';

			return $this->strleft(strtolower($_SERVER["SERVER_PROTOCOL"]), "/").$s;
		}
		
		public function port()
		{
		    if (Phpr::$config->get('STANDARD_HTTP_PORTS'))
		        return null;
		    
			if (array_key_exists('HTTP_HOST', $_SERVER))
			{
				$matches = array();
				if (preg_match('/:([0-9]+)/', $_SERVER['HTTP_HOST'], $matches))
					return $matches[1];
			}

			return isset($_SERVER["SERVER_PORT"]) ? $_SERVER["SERVER_PORT"] : null;
		}

		public function getRootUrl($protocol = null)
		{
			if (!isset($_SERVER['SERVER_NAME']))
				return null;
			
			$protocol_specified = strlen($protocol);

			if ($protocol === null)
				$protocol = $this->protocol();

			$port = $this->port();

			$current_protocol = $this->protocol();
			if ($protocol_specified && strtolower($protocol) != $current_protocol)
				$port = '';

			$https = strtolower($protocol) == 'https';

			if (!$https && $port == 80)
				$port = '';

			if ($https && $port == 443)
				$port = '';

			$port = !strlen($port) ? "" : ":".$port;

			return $protocol."://".$_SERVER['SERVER_NAME'].$port;
		}
		
		private function strleft($s1, $s2) 
		{
			return substr($s1, 0, strpos($s1, $s2));
		}
	}

?>