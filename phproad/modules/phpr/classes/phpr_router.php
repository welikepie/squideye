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
	 * PHP Road Router Class
	 *
	 * Router maps an URI string to the PHP Road controllers and actions.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Router
	{
		private $_rules = array();

		/**
		 * @var array
		 * A list of URI parameters names a and values. 
		 * The URI "archive/:year/:month/:day" will produce 3 parameters: year, month and day.
		 */
		public $parameters = array();

		/**
		 * @var string
		 * Contains a current Controller name. This variable is set during the Rout method call.
		 */
		public $controller = null;

		/**
		 * @var string
		 * Contains a current Action name. This variable is set during the Rout method call.
		 */
		public $action = null;

		const _urlController = 'controller';
		const _urlAction = 'action';
		const _urlModule = 'module';

		/**
		 * Parses an URI and finds the controller class name, action and parameters.
		 * @param string $URI Specifies the URI to parse.
		 * @param string &$Controller The controller name
		 * @param string &$Action The controller action name
		 * @param array &$Parameters A list of the action parameters
		 * @param string &$Folder A path to the controller folder
		 */
		public function route( $URI, &$Controller, &$Action, &$Parameters, &$Folder )
		{
			$Controller = null;
			$Action = null;
			$Parameters = array();

			if ( $URI{0} == '/' ) 
				$URI = substr( $URI, 1 );

			$segments = $this->segmentURI($URI);
			$segmentCount = count($segments);

			foreach ( $this->_rules as $Rule )
			{
				if ( strlen($Rule->URI) )
					$ruleSegments = explode( "/", $Rule->URI );
				else
					$ruleSegments = array();

				try
				{
					$ruleSegmentCount = count($ruleSegments);
					$ruleParams = $this->getURIParams( $ruleSegments );

					// Check whether the number of URI segments matches
					//
					$minSegmentNum = $ruleSegmentCount - count($Rule->defaults);

					if ( !($segmentCount >= $minSegmentNum && $segmentCount <= $ruleSegmentCount) )
						continue;

					// Check whether the static segments matches
					//
					foreach ( $ruleSegments as $index=>$ruleSegment )
					{
						if ( !$this->valueIsParam($ruleSegment) )
							if ( !isset($segments[$index]) || $segments[$index] != $ruleSegment )
								continue 2;
					}

					// Validate checks
					//
					foreach ( $Rule->checks as $param=>$pattern )
					{
						$paramIndex = $ruleParams[$param];

						// Do not check default parameter values
						//
						if ( !isset($segments[$paramIndex]) )
							continue;

						// Match the parameter value
						//
						if ( !preg_match($pattern, $segments[$paramIndex]) )
							continue 2;
					}

					// Evaluate the controller parameters
					//
					foreach ( $ruleParams as $paramName=>$paramIndex )
					{
						if ( $paramName == self::_urlController || $paramName == self::_urlAction )
							continue;

						$value = $this->evaluateParameterValue( $paramName, $paramIndex, $segments, $Rule->defaults );

						if ( $paramName != self::_urlModule )
							$Parameters[] = $value;

						$this->parameters[$paramName] = $value;
					}

					// Evaluate the controller and action values
					//
					$Controller = $this->evaluateTargetValue( self::_urlController, $ruleParams, $Rule, $segments );
					$Action = $this->evaluateTargetValue( self::_urlAction, $ruleParams, $Rule, $segments );
					if ( !strlen($Action) )
						$Action = 'index';

					$this->controller = $Controller;
					$this->action = $Action;

					// Evaluate the controller path
					//
					$Folder = $Rule->folder;

					if ( $Rule->folder !== null )
					{
						$FolderParams = Phpr_Router::getURIParams( explode("/", $Rule->folder) );
						foreach ( $FolderParams as $paramName=>$paramIndex )
						{
							if ( $paramName == self::_urlController )
								$paramValue = $Controller;
							elseif ( $paramName == self::_urlAction )
								$paramValue = $Action;
							else
								$paramValue = $this->parameters[$paramName];

							$Folder = strtolower( str_replace( ':'.$paramName, $paramValue, $Folder ) );
						}
					}

					break;
				}
				catch ( Exception $ex )
				{
					throw new Phpr_SystemException( "Error routing rule [{$Rule->URI}]: ".$ex->getMessage() );
				}
			}
		}
		
		public function rout( $URI, &$Controller, &$Action, &$Parameters, &$Folder ) { // deprecated
			return $this->route( $URI, $Controller, $Action, $Parameters, $Folder );
		}

		/**
		 * This function takes an URI and returns its segments as array.
		 * @param URI Specifies the URI to process.
		 * @return array
		 */
		protected function segmentURI( $URI )
		{
			$result = array();

			foreach ( explode( "/", preg_replace("|/*(.+?)/*$|", "\\1", $URI) ) as $segment )
			{
				$segment = trim($segment);
				if ( $segment != '' )
					$result[] = $segment;
			}

			return $result;
		}

		/**
		 * @ignore
		 * Returns a list of parameters in the URI. Parameters are prefixed with the colon character.
		 * @param array $Segments A list of URI segments
		 * @return array
		 */
		public static function getURIParams( $Segments )
		{
			$result = array();

			foreach ( $Segments as $index=>$val )
				if ( self::valueIsParam($val) )
					$result[substr($val, 1)] = $index;

			return $result;
		}

		/**
		 * @ignore
		 * Determines whether value is parameter.
		 * @param string $Segment Specifies the segment name to check.
		 * @return boolean
		 */
		public static function valueIsParam( $Segment )
		{
			return strlen($Segment) && substr($Segment, 0, 1) == ':';
		}

		/**
		 * Returns a name of the controller or action.
		 * @param string $TargetType Specifies a type of the target - controller or action.
		 * @param array &$RuleParams List of the rule parameters.
		 * @param Phpr_RouterRule &$Rule Specifies the rule.
		 * @param array &$Segments A list of the URI segments.
		 * @return string
		 */
		protected function evaluateTargetValue( $TargetName, &$RuleParams, &$Rule, &$Segments )
		{
			//$fieldName = ucfirst($TargetName);
			$fieldName = strtolower($TargetName);

			// Check whether the target value is specified explicitly in the rule target settings.
			//
			if ( !isset($RuleParams[$TargetName]) )
			{
				if ( strlen($Rule->$fieldName) )
				{
					$targetValue = $Rule->$fieldName;

					if ( $this->valueIsParam($targetValue) )
					{
						$targetValue = substr($targetValue, 1);
						return strtolower( $this->evaluateParameterValue( $targetValue, $RuleParams[$targetValue], $Segments, $Rule->defaults ) );
					} else
						return strtolower( $targetValue );
				}
			} else {
				// Extract the target value from the URI or try to find a default value
				//
				if ( isset($Segments[$RuleParams[$TargetName]]) )
				{
					return strtolower( $this->evaluateConvertedValue( $TargetName, strtolower( $Segments[$RuleParams[$TargetName]] ), $Segments, $RuleParams, $Rule->defaults, $Rule->converts ));
					// return ucfirst( strtolower( $Segments[$RuleParams[$TargetName]] ) );
				}
				else
				{
					$Value = $this->evaluateParameterValue( $TargetName, $TargetName, $Segments, $Rule->defaults );
					return strtolower( $this->evaluateConvertedValue( $TargetName, strtolower($Value), $Segments, $RuleParams, $Rule->defaults, $Rule->converts ));
				}
			}
		}

		/**
		 * Returns a specified or default value of the parameter.
		 * @param string $ParamName Specifies a name of the parameter.
		 * @index int $Index Specifies the index of the parameter.
		 * @param array &$Segments A list of the URI segments.
		 * @param array &$Defaults Specifies the rule parameters defaults.
		 * @return string
		 */
		protected function evaluateParameterValue( $ParamName, $Index, &$Segments, &$Defaults )
		{
			if ( isset($Segments[$Index]) )
				return $Segments[$Index];

			if ( isset($Defaults[$ParamName]) )
				return $Defaults[$ParamName];

			return null;
		}
		
		protected function evaluateConvertedValue( $ParamName, $ParamValue, &$Segments, &$RuleParams, &$Defaults, &$Converts )
		{
			if ( isset($Converts[$ParamName]) )
			{
				$ConvertRule = $Converts[$ParamName];

				foreach ($RuleParams as $Name=>$Index)
				{
					if (isset($Segments[$Index]))
						$Value = $Segments[$Index];
					else
						$Value = $this->evaluateParameterValue( $Name, null, $Segments, $Defaults );

					$ConvertRule[1] = str_replace(":".$Name, $Value, $ConvertRule[1]);
				}
				return preg_replace($ConvertRule[0], $ConvertRule[1], $ParamValue);
			}
			
			return $ParamValue;
		}

		/**
		 * Adds a routing rule.
		 * Use this method to define custom URI mappings to your application controllers.
		 * After adding a rule use the Phpr_RouterRule class methods to configure the rule. For example: AddRule("archive/:year")->controller("blog")->action("Archive")->def("year", 2006).
		 * @return Phpr_RouterRule
		 */
		public function addRule( $URI )
		{
			return $this->_rules[] = new Phpr_RouterRule( $URI );
		}

		/**
		 * Returns a URI parameter by its name.
		 * @param string $Name Specifies the parameter name
		 * @param string $Default Default parameter value
		 * @return string
		 */
		public function param( $Name, $Default = null )
		{
			return isset($this->parameters[$Name]) ? $this->parameters[$Name] : $Default;
		}

		/*
		 * Returns a requested URI
		 */
		public function getURI()
		{
			$Result = $this->controller.'/'.$this->action;

			foreach ( $this->parameters as $ParamValue )
			{
				if ( strlen($ParamValue) )
					$Result .= '/'.$ParamValue;
				else
					break;
			}

			return $Result;
		}
	}

	/**
	 * PHP Road Router Rule Class
	 *
	 * Represents a rule for mapping an URI string to the PHP Road controller and action.
	 * Do not use this class directly. Use the Phpr::$router->addRule method instead.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_RouterRule
	{
		public $URI = null;
		public $controller = null;
		public $action = null;
		public $defaults = array();
		public $checks = array();
		public $folder = null;
		public $converts = array();

		private $_params = array();

		/**
		 * Creates a new rule.
		 * Do not create rules directly. Use the Phpr::$router->addRule method instead.
		 * @param string $URI Specifies the URI to be matched. No leading and trailing slashes. The :controller and :action names may be used. Example: :controller/:action/:id
		 * @return Phpr_RouterRule
		 */
		public function __construct( $URI )
		{
			$this->URI = $URI;
			$this->_params = Phpr_Router::getURIParams( explode("/", $this->URI) );
		}

		/**
		 * Sets a name of the controller to be used if the requested URI matches this rule URI.
		 * @param string $Controller Specifies a controller name.
		 * @return Phpr_RouterRule
		 */
		public function controller( $Controller )
		{
			if ( $this->controller !== null )
				throw new Phpr_SystemException( "Invalid router rule configuration. The controller is already specified: [{$this->URI}]" );

			if ( Phpr_Router::valueIsParam($Controller) )
			{
				if ( !isset($this->_params[$Controller]) )
					throw new Phpr_SystemException( "Invalid router rule configuration. The parameter \"$Controller\" specified in the Controller instruction is not found in the rule URI: [{$this->URI}]" );
			}

			$this->controller = $Controller;

			return $this;
		}

		/**
		 * Sets a name of the controller action be executed if the requested URI matches this rule URI.
		 * @param string $Action Specifies an action name.
		 * @return Phpr_RouterRule
		 */
		public function action( $Action )
		{
			if ( $this->action !== null )
				throw new Phpr_SystemException( "Invalid router rule configuration. The action is already specified: [{$this->URI}]" );

			if ( Phpr_Router::valueIsParam($Action) )
			{
				if ( !isset($this->_params[$Action]) )
					throw new Phpr_SystemException( "Invalid router rule configuration. The parameter \"$Action\" specified in the Action instruction is not found in the rule URI: [{$this->URI}]" );
			}

			$this->action = $Action;
			return $this;
		}

		/**
		 * Sets a default URI parameter value. This value will be used if the URI component is ommited.
		 * @param string $Param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
		 * @param mixed $Value Specifies a parameter value.
		 * @return Phpr_RouterRule
		 */
		public function def( $Param, $Value )
		{
			if ( !isset($this->_params[$Param]) )
				throw new Phpr_SystemException( "Invalid router rule configuration. The default parameter \"$Param\" is not found in the rule URI: [{$this->URI}]" );

			$this->defaults[$Param] = $Value;
			return $this;
		}
		
		/**
		 * Converts a parameter value according a specified regular expression match and replacement strings
		 * @param string $Param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
		 * @param mixed $Match Specifies a regular expression match value
		 * @param mixed $Replace Specifies a regular expression replace value
		 * @return Phpr_RouterRule
		 */
		public function convert( $Param, $Match, $Replace )
		{
			if ( !isset($this->_params[$Param]) )
				throw new Phpr_SystemException( "Invalid router rule configuration. The convert parameter \"$Param\" is not found in the rule URI: [{$this->URI}]" );

			$this->converts[$Param] = array($Match, $Replace);
			return $this;
		}

		/**
		 * Sets the URI parameter value check.
		 * @param string $Param Specifies a parameter name. The parameter must be present in the rule URI and prefixed with the colon character. For example "/date/:year".
		 * @param string $Check Specifies a checking value as a Perl-Compatible Regular Expression pattern, for example "/^\d{1,2}$/"
		 * @return Phpr_RouterRule
		 */
		public function check( $Param, $Check )
		{
			if ( !isset($this->_params[$Param]) )
				throw new Phpr_SystemException( "Invalid router rule configuration. The parameter \"$Param\" specified in the Check instruction is not found in the rule URI: [{$this->URI}]" );

			$this->checks[$Param] = $Check;
			return $this;
		}

		/**
		 * Defines a path to the controller class file.
		 * @param string $Folder Specifies a path to the file.
		 * You may use parameters from URI and default parameters here.
		 * Example: Phpr::$router->addRule("catalog/:product")->def('product', 'books')->folder('controllers/:product');
		 * @return Phpr_RouterRule
		 */
		public function folder( $Folder )
		{
			$Folder = str_replace( "\\", "/", $Folder );

			// Validate the folder path
			//
			$PathParams = Phpr_Router::getURIParams( explode("/", $Folder) );
			foreach ( $PathParams as $Param=>$Index )
			{
				if ( $Param != Phpr_Router::_urlController && $Param != Phpr_Router::_urlAction && !isset($this->_params[$Param]) )
					throw new Phpr_SystemException( "Invalid router rule configuration. The parameter \"$Param\" specified in the Folder instruction is not found in the rule URI: [{$this->URI}]" );
			}

			$this->folder = $Folder;
			return $this;
		}
	}

?>