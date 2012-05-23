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
	 * PHP Road Validation Class
	 *
	 * Phpr_Validation class assists in validating form data.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Validation
	{
		private $_owner;

		/**
		 * @ignore
		 * Contains a list of fields validation rules
		 * @var array
		 */
		public $_fields;

		/**
		 * Indicates whether all validation rules are valid.
		 * A value of this field is set by the Validate method.
		 * @var boolean
		 */
		public $valid;

		/**
		 * Contains a list of invalid field names.
		 * @var array
		 */
		public $errorFields;

		/**
		 * Contains a list of fields error messages.
		 */
		public $fieldErrors;

		/**
		 * Keeps a common error message.
		 * @var string
		 */
		public $errorMessage;

		/**
		 * @ignore
		 * Contains an evaluated field values.
		 * @var array
		 */
		public $fieldValues;
		
		/**
		 * Specifies a prefix to add to field identifiers in focusField method call
		 */
		public $focusPrefix = null;
		
		private $_formId;

		/**
		 * Creates a new validation class
		 * @param object $Owner Specifies an optional owner object
		 * @param string $FormId Specifies an optional HTML form identifier
		 * The owner object class must be inherited from the Phpr_Validatable class
		 * if you are going to use the Method rule.
		 */
		public function __construct( $Owner = null, $FormId = 'FormElement' )
		{
			$this->_owner = $Owner;
			$this->_formId = $FormId;
			$this->_fields = array();
			$this->errorFields = array();
			$this->valid = false;
			$this->errorMessage = null;
			$this->fieldErrors = array();
			$this->fieldValues = array();
		}
		
		/**
		 * Sets a form element identifier
		 */
		public function setFormId($FormId)
		{
			$this->_formId = $FormId;
		}

		/**
		 * Attaches a validation rule set to a form field.
		 * @param string $Field Specifies a form field name (firstname)
		 * @param string $FieldName Specifies a field name (First Name). 
		 * You may omit this field if the field name matches the form field.
		 * @param bool $Focusable Specifies whether the field is focusable.
		 * @return Phpr_ValidationRules
		 */
		public function add( $Field, $FieldName = null, $Focusable = true )
		{
			if ( $FieldName === null )
				$FieldName = $Field;

			return $this->_fields[$Field] = new Phpr_ValidationRules( $this, $FieldName, $Focusable );
		}
		
		/**
		 * Sets a common or field error message.
		 * @param string $Message Specifies the error message
		 * @param string $Field Specifies the field name. If this parameter is omitted, the common message will be set.
		 * @param bool Throw exception
		 * @return Phpr_Validation
		 */
		public function setError( $Message, $Field = null, $Throw = false )
		{
			$this->valid = false;

			if ( $Field !== null )
			{
				$this->fieldErrors[$Field] = $Message;
				$this->errorFields[] = $Field;
			}
			else
				$this->errorMessage = $Message;

			if ( $Throw )
				$this->throwException();

			return $this;
		}

		/**
		 * Determines whether a field with the name specified is invalid.
		 * @param string $Field Specifies the field name.
		 * @return boolean
		 */
		public function isError( $Field )
		{
			return in_array($Field, $this->errorFields);
		}

		/**
		 * Returns an error message for the specified field.
		 * @param string $Field Specifies the field name.
		 * @param boolean $Html Indicates whether the message must be prepared to the HTML output.
		 * @return string
		 */
		public function getError( $Field, $Html = true )
		{
			if ( !isset($this->fieldErrors[$Field]) )
				return null;

			$Message = $this->fieldErrors[$Field];
			return $Html ? Phpr_Html::encode($Message) : $Message;
		}

		/**
		 * Runs the validation rules and determines whether the data is valid.
		 * @param mixed $Data Specifies a data object. It may be array or object. 
		 * If this parameter is omitted, the POST variables will be used.
		 * @param string $deferred_session_key An edit session key for deferred bindings. 
		 * Use it for validating Active Record objects with deferred bindings.
		 * @return boolean Indicates whether all validation rules are passed.
		 */
		public function validate( $Data = null, $deferred_session_key = null )
		{
			$ErrorFound = false;
			
			if ( $Data === null )
				$SrcArr = $_POST;
			elseif ( is_object($Data) )
				$SrcArr = (array)$Data;
			elseif ( is_array($Data) )
				$SrcArr = $Data;
			else
				throw Phpr_SystemException( "Invalid validation data object" );

			foreach ( $this->_fields as $ParamName=>$RuleSet )
			{
				if (!is_object($Data))
					$FieldValue = isset($SrcArr[$ParamName]) ? $SrcArr[$ParamName] : null;
				else
				{
					if (!($Data instanceof Db_ActiveRecord))
						$FieldValue = $Data->$ParamName;
					else
						$FieldValue = $Data->getDeferredValue($ParamName, $deferred_session_key);
				}

				foreach ( $RuleSet->rules as $Rule )
				{
					$RuleObj = $Rule[Phpr_ValidationRules::objName];

					switch ( $Rule[Phpr_ValidationRules::ruleType] )
					{
						case Phpr_ValidationRules::typeInternal : 
								$RuleResult = $RuleSet->evalInternal($RuleObj, $ParamName, $FieldValue, $Rule[Phpr_ValidationRules::params], $Rule[Phpr_ValidationRules::message], $Data, $deferred_session_key );
								break;

						case Phpr_ValidationRules::typeFunction :
								if ( !function_exists($RuleObj) )
									throw new Phpr_SystemException( "Unknown validation function: $RuleObj" );

								$RuleResult = $RuleObj($FieldValue);
								break;

						case Phpr_ValidationRules::typeMethod :
								if ( $this->_owner === null )
									throw new Phpr_SystemException( "Can not execute the method-type rule $RuleObj without an owner object" );
								
								if(is_string($RuleObj))
									$RuleResult = $this->_owner->_execValidation($RuleObj, $ParamName, $FieldValue);
								elseif(is_callable($RuleObj))
									$RuleResult = call_user_func($RuleObj, $ParamName, $FieldValue, $this, $this->_owner);
								break;
					}

					if ( $RuleResult === false )
					{
						$this->errorFields[] = $ParamName;
						$ErrorFound = true;
						continue 2;
					}

					if ( $RuleResult === true )
						continue;

					$FieldValue = $RuleResult;
				}

				$this->fieldValues[$ParamName] = $FieldValue;
			}

			$this->valid = !$ErrorFound;

			if ( $this->valid )
			{
				foreach ( $this->fieldValues as $fieldName=>$fieldValue )
				{
					if ( $Data === null )
						$_POST[$fieldName] = $fieldValue;
					elseif ( is_object($Data) )
					{
						if (!($Data instanceof Db_ActiveRecord))
							$Data->$fieldName = $fieldValue;
						else
							$Data->setDeferredValue($fieldName, $fieldValue, $deferred_session_key);
					}
				}
			}

			return $this->valid;
		}

		/**
		 * Sets focus to a first error field.
		 * If there are no error fields, sets focus to a first form field.
		 * You may also specify explicitly with the optional parameter.
		 * @param string $FieldId Optional identifier of a field to focus. 
		 * @param boolean $Force Optional. Determines whether the field specified 
		 * in the first parameter must be focused even in case of errors.
		 */
		public function focus( $FieldId = null, $Force = false )
		{
			$hasErrors = count($this->errorFields);

			$FormId = $this->_formId === null ? 'document.forms[0]' : $this->_formId;

			if ( $FieldId !== null && (!$hasErrors || ($hasErrors && $Force)) )
				return "$('{$FormId}').focusField('$FieldId');";

			if ( $hasErrors )
			{
				$Field = $this->errorFields[0];
				if ( isset($this->_fields[$Field]) && !$this->_fields[$Field]->focusable )
					return null;

				return "$('{$FormId}').focusField('{$this->errorFields[0]}');";
			}

			return "$('{$FormId}').focusFirst();";
		}

		/**
		 * Generates a Java Script code for focusing an error field
		 * @param boolean $AddScriptNode Indicates whether the script node must be generated
		 * @return string
		 */
		public function getFocusErrorScript( $AddScriptNode = true )
		{
			if ( !count($this->errorFields) )
				return null;

			$Field = $this->errorFields[0];
			if ( isset($this->_fields[$Field]) && !$this->_fields[$Field]->focusable )
				return null;

			$result = null;
			if ( $AddScriptNode )
				$result .= "<script type='text/javascript'>";

			$FormId = $this->_formId === null ? 'document.forms[0]' : $this->_formId;
			$FocusId = strlen($this->_fields[$Field]->focusId) ? $this->_fields[$Field]->focusId : $Field;

			if ($this->focusPrefix)
				$FocusId = $this->focusPrefix.$FocusId;

			$result .= "$(document.body).focusField('{$FocusId}');";
			$result .= "window.phprErrorField = '$FocusId';";

			if ( $AddScriptNode )
				$result .= "</script>";

			return $result;
		}

		/**
		 * Throws the Validation Exception in case if data is not valid.
		 */
		public function throwException()
		{
			throw new Phpr_ValidationException($this);
		}
		
		public function hasRuleFor($field)
		{
			return array_key_exists($field, $this->_fields);
		}
		
		public function getRule($field)
		{
			if ($this->hasRuleFor($field))
				return $this->_fields[$field];
				
			return null;
		}
	}

	/**
	 * Validation exception class.
	 *
	 * Phpr_ValidationException represens a data validation error.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_ValidationException extends Phpr_ApplicationException
	{
		public $Validation;

		/**
		 * Creates a new Phpr_ValidationException instance
		 * @param Phpr_Validation $Validation Validation object that caused the exception.
		 */
		public function __construct( Phpr_Validation $Validation )
		{
			parent::__construct();
			$this->message = null;

			$this->validation = $Validation;

			if ( $Validation->errorMessage !== null )
				$this->message = $Validation->errorMessage;

			if ( count($Validation->fieldErrors) )
			{
				$keys = array_keys($Validation->fieldErrors);

				if ( strlen($this->message) ) $this->message .= '\n';
				$this->message .= $Validation->fieldErrors[$keys[0]];
			}
		}
	}

	/**
	 * Validation rules class.
	 *
	 * Phpr_ValidationRules represens a set of validation rules applied to a POST element.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_ValidationRules
	{
		const ruleType = 'type';
		const objName = 'name';
		const typeFunction = 'function';
		const typeMethod = 'method';
		const typeInternal = 'internal';
		const params = 'params';
		const message = 'message';

		/**
		 * @ignore
		 * Contains a list of validation rules.
		 * @var array
		 */
		public $rules;

		/**
		 * @ignore
		 * Contains a field name.
		 * @var string
		 */
		public $fieldName;

		/**
		 * @ignore
		 * Determines whether the field is focusable.
		 * @var string
		 */
		public $focusable;
		
		/**
		 * @ignore
		 * An element that should be focused in case of error
		 * @var string
		 */
		public $focusId;
		
		public $required;

		protected $validation;

		/**
		 * Creates a new Phpr_ValidationRules instance. Do not instantiate this class directly - 
		 * the controller Validation property: $this->validation->addRule("FirstName").
		 * @param Phpr_Validation $Validation Specifies the validation class instance.
		 * @param bool $Focusable Specifies whether the field is focusable.
		 * @param string $FieldName Specifies a field name.
		 */
		public function __construct( $Validation, $FieldName, $Focusable )
		{
			$this->rules = array();
			$this->validation = $Validation;
			$this->fieldName = $FieldName;
			$this->focusable = $Focusable;
		}

		/**
		 * Adds a rule that validates a value using a PHP function.
		 * The function must accept one parameter - the value 
		 * and return a string or boolean value.
		 * @param string $Name Specifies a PHP function name.
		 * @return Phpr_ValidationRules
		 */
		public function fn( $Name )
		{
			$this->rules[] = array( self::ruleType=>self::typeFunction, self::objName=>$Name );
			return $this;
		}
		
		/**
		 * Sets an identifier of a element that should be focused in case of error
		 * @param string $Id Specifies an element identifier
		 * @return Phpr_ValidationRules
		 */
		public function focusId( $Id )
		{
			$this->focusId = $Id;
			return $this;
		}

		/**
		 * Adds a rule that validates a value using a owner class method.
		 * The owner class must be inherited from the Phpr_Validatable class.
		 * The method must accept two parameters - the field name and value
		 * and return a string or boolean value.
		 * @param string $Name Specifies a controller method name.
		 * @return Phpr_ValidationRules
		 */
		public function method( $Name )
		{
			$this->rules[] = array( self::ruleType=>self::typeMethod, self::objName=>$Name );
			return $this;
		}

		/**
		 * @ignore
		 * Evaluates the internal validation rule.
		 * This method is used by the Phpr_Validation class internally.
		 * @param string $Rule Specifies the rule name
		 * @param string $Name Specifies a field name
		 * @param string $Value Specifies a value to validate
		 * @param array &$Params A list of the rule parameters.
		 * @return mixed
		 */
		public function evalInternal( $Rule, $Name, $Value, &$Params, $CustomMessage, &$DataSrc, $deferred_session_key )
		{
			$MethodName = "eval".$Rule;
			if ( !method_exists($this, $MethodName) )
				throw new Phpr_SystemException( "Unknown validation rule: $Rule" );
				
			$Params['deferred_session_key'] = $deferred_session_key;

			return $this->$MethodName( $Name, $Value, $Params, $CustomMessage, $DataSrc );
		}

		/**
		 * Registers an internal validation rule.
		 * @param string $Method Specifies the rule method name.
		 * @param array $Params A list of the rule parameters.
		 * @param string $CustomMessage Custom error message
		 */
		protected function registerInternal( $Method, $Params = array(), $CustomMessage = null )
		{
			if ( ($pos = strpos($Method, '::')) !== false )
				$Method = substr($Method, $pos+2);

			$this->rules[] = array( self::ruleType=>self::typeInternal, self::objName=>$Method, self::params=>$Params, self::message=>$CustomMessage );
		}

		/*
		 * ====================== Numeric rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is numeric.
		 * @return Phpr_ValidationRules
		 */
		public function numeric($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is numeric.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalNumeric( $Name, $Value, &$Params, $CustomMessage )
		{
			if ( !strlen($Value) )
				return true;

			$result = preg_match("/^\-?[0-9]+$/", $Value) ? true : false;
			
			$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'numeric', 'validation'), $this->fieldName);
			if ( !$result )
				$this->validation->setError( $Message, $Name );

			return $result;
		}

		/*
		 * ====================== Float rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is a valid float number.
		 * This function uses a number format specified in user language settings.
		 * @see Phpr_Language
		 * @return Phpr_ValidationRules
		 */
		public function float($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is a valid float number.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalFloat( $Name, $Value, &$Params, $CustomMessage )
		{
			if (!strlen($Value))
				return true;
			
			// $result = Phpr::$lang->strToNum($Value);

			if (!preg_match('/^(\-?[0-9]*\.[0-9]+|\-?[0-9]+)$/', $Value))
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'float', 'validation'), $this->fieldName);
				
				$this->validation->setError( $Message, $Name );
				return false;
			}
			
			$Value = trim($Value);
			if (strlen($Value))
			{
				$first_char = substr($Value, 0, 1);
				if ($first_char == '.')
					$Value = (float)('0'.$Value);
				elseif ($first_char == '-')
				{
					if (substr($Value, 1, 1) == '.')
						$Value = (float)('-0'.substr($Value, 1));
				}
			}

			return $Value;
		}

		/*
		 * ====================== Min length rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is not shorter than a specified length.
		 * @param int $Length Specifies a minimum field length
		 * @return Phpr_ValidationRules
		 */
		public function minLength( $Length, $CustomMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Length), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is not shorter than a specified length.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the MinLength method.
		 * @return boolean.
		 */
		protected function evalMinLength( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = strlen($Value) >= $Params[0] ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'minlen', 'validation'), $this->fieldName, $Params[0]);

				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== Max length rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is not longer than a specified length.
		 * @param int $Length Specifies a maximum field length
		 * @return Phpr_ValidationRules
		 */
		public function maxLength( $Length, $CustomMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Length), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is not longer than a specified length.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the MaxLength method.
		 * @return boolean.
		 */
		protected function evalMaxLength( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = strlen($Value) <= $Params[0] ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'maxlen', 'validation'), $this->fieldName, $Params[0]);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== Length rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value length matches a specified value.
		 * @param int $Length Specifies a field length
		 * @return Phpr_ValidationRules
		 */
		public function length( $Length, $CustomMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Length), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value length matches a specified value.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Length method.
		 * @return boolean.
		 */
		protected function evalLength( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = strlen($Value) == $Params[0] ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'length', 'validation'), $this->fieldName, $Params[0]);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}
		
		/*
		 * ====================== Unique rule ======================
		 */
		
		/**
		 * Adds a rule that determines whether a value is unique in the database table. 
		 * @return Phpr_ValidationRules
		 */
		public function unique( $CustomMessage = null, $CheckerFilterCallback = null )
		{
			$this->registerInternal(__METHOD__, array('filter_callback'=>$CheckerFilterCallback), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value length matches a specified value.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Length method.
		 * @return boolean.
		 */
		protected function evalUnique( $Name, $Value, &$Params, $CustomMessage, &$obj )
		{
			if (!($obj instanceof Db_ActiveRecord) || !strlen($Value))
				return true;

			$modelClassName = get_class($obj);

			$checker = new $modelClassName();
			$checker->where("$Name = ?", $Value);
			if (!$obj->is_new_record())
				$checker->where("{$obj->primary_key} <> ?", $obj->get_primary_key_value());
				
			if ($Params['filter_callback'])
				call_user_func($Params['filter_callback'], $checker, $obj, $Params['deferred_session_key']);
				
			if ($checker->find())
			{
				$Message = strlen($CustomMessage) ? sprintf($CustomMessage, $Value) : sprintf(Phpr::$lang->mod( 'phpr', 'unique', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
				return false;
			}

			return true;
		}

		/*
		 * ====================== Required rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is not empty.
		 * @return Phpr_ValidationRules
		 */
		public function required($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			$this->required = true;
			return $this;
		}

		/**
		 * Determines whether a value is not empty.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalRequired( $Name, $Value, &$Params, $CustomMessage )
		{
			if (!is_array($Value) && !($Value instanceof Db_DataCollection))
				$result = trim($Value) != '' ? true : false;
			elseif($Value instanceof Db_DataCollection)
				$result = $Value->count() ? true : false;
			else
				$result = count($Value) ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'required', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}
		
		/*
		 * ====================== Optional rule ======================
		 */

		/**
		 * Makes a field optional.
		 */
		public function optional()
		{
			$this->required = false;
			
			$required_index = null;
			foreach ($this->rules as $index=>$rule)
			{
				if ($rule['name'] == 'required')
				{
					$required_index = $index;
					break;
				}
			}
			
			if ($required_index !== null)
				unset($this->rules[$required_index]);

			return $this;
		}
		
		/*
		 * ====================== Alpha rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value contains only alphabetical characters.
		 * @return Phpr_ValidationRules
		 */
		public function alpha($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value contains only alphabetical characters.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalAlpha( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = preg_match("/^([-a-z])+$/i", $Value) ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'alpha', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== Alphanumeric rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value contains only alpha-numeric characters.
		 * @return Phpr_ValidationRules
		 */
		public function alphanum($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value contains only alpha-numeric characters.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalAlphanum( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = preg_match("/^([-a-z0-9])+$/i", $Value) ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'alphanum', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== Email rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is a valid email address.
		 * @param bool @AllowEmpty Determines whether empty string is allowed
		 * @return Phpr_ValidationRules
		 */
		public function email( $AllowEmpty = false, $CustomMessage = null )
		{
			$this->registerInternal(__METHOD__, array($AllowEmpty), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is a valid email address.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Regexp method.
		 * @return boolean.
		 */
		protected function evalEmail( $Name, $Value, &$Params, $CustomMessage )
		{
			if ( !strlen($Value) && $Params[0] )
				return true;

//			$result = preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", mb_strtolower($Value)) ? true : false;
			$result = preg_match("/^[_a-z0-9-\.\=\+]+@[_a-z0-9-\.\=\+]+$/", mb_strtolower($Value)) ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'email', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== IP rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value is a valid IP address.
		 * @return Phpr_ValidationRules
		 */
		public function ip($CustomMessage = null)
		{
			$this->registerInternal(__METHOD__, array(), $CustomMessage);
			return $this;
		}

		/**
		 * Determines whether a value is a valid IP address.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @return boolean.
		 */
		protected function evalIp( $Name, $Value, &$Params, $CustomMessage )
		{
			$result = preg_match( "/^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$/", $Value) ? true : false;

			if ( !$result )
			{
				$Message = strlen($CustomMessage) ? $CustomMessage : sprintf(Phpr::$lang->mod( 'phpr', 'ip', 'validation'), $this->fieldName);
				$this->validation->setError( $Message, $Name );
			}

			return $result;
		}

		/*
		 * ====================== Matches rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value matches another field value.
		 * @param string $Field Specifies a name of field this field value must match
		 * @return Phpr_ValidationRules
		 */
		public function matches( $Field, $errorMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Field, $errorMessage));
			return $this;
		}

		/**
		 * Determines whether a value matches another field value.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Matches method.
		 * @return boolean.
		 */
		protected function evalMatches( $Name, $Value, &$Params )
		{
			$fieldToMatch = $Params[0];
			$errorMessage = $Params[1];
			if ( !isset($this->validation->_fields[$fieldToMatch]) )
				throw new Phpr_SystemException("Unknown validation field: $fieldToMatch");

			$valueToMatch = isset($this->validation->fieldValues[$fieldToMatch]) ? $this->validation->fieldValues[$fieldToMatch] : Phpr::$request->post($fieldToMatch);

			$result = $Value == $valueToMatch ? true : false;

			if ( !$result )
			{
				if ( !strlen($errorMessage) )
				{
					$fieldToMatchName = $this->validation->_fields[$fieldToMatch]->fieldName;
					$this->validation->setError( sprintf(Phpr::$lang->mod( 'phpr', 'matches', 'validation'), $this->fieldName, $fieldToMatchName), $Name );
				} else
				{
					$this->validation->setError( $errorMessage, $Name );
				}
			}

			return $result;
		}

		/*
		 * ====================== Regexp rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value matches a specified regular expression pattern.
		 * @param string $Pattern Specifies a Perl-compatible regular expression pattern.
		 * @param string $errorMessage Optional error message.
		 * @param bool @AllowEmpty Determines whether empty string is allowed
		 * @return Phpr_ValidationRules
		 */
		public function regexp( $Pattern, $errorMessage = null, $AllowEmpty = false )
		{
			$this->registerInternal(__METHOD__, array($Pattern, $errorMessage, $AllowEmpty));
			return $this;
		}

		/**
		 * Determines whether a value matches a specified regular expression pattern.
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Regexp method.
		 * @return boolean.
		 */
		protected function evalRegexp( $Name, $Value, &$Params )
		{
			if ( !strlen($Value) && $Params[2] )
				return true;

			$result = preg_match( $Params[0], $Value) ? true : false;

			if ( !$result ) 
			{
				$errorMessage = $Params[1] !== null ? $Params[1] : sprintf(Phpr::$lang->mod( 'phpr', 'regexp', 'validation'), $this->fieldName);
				$this->validation->setError( $errorMessage, $Name );
			}

			return $result;
		}

		/*
		 * ====================== DateTime rule ======================
		 */

		/**
		 * Adds a rule that determines whether a value represents a date/time value, according the specified format.
		 * Some formats (like %x and %X) depends on the current user language date format. 
		 * This rule sets the field value to a valid SQL date format converted to GMT.
		 * @param string $Format Specifies an expected format. 
		 * By default the short date format (%x) used (11/6/2006 - for en_US).
		 * @param string $errorMessage Optional error message.
		 * @return Phpr_ValidationRules
		 */
		public function dateTime( $Format = "%x", $errorMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Format, $errorMessage));
			return $this;
		}

		/**
		 * Determines whether a value is a valid data and time string
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Regexp method.
		 * @return boolean.
		 */
		protected function evalDateTime( $Name, $Value, &$Params )
		{
			if (is_object($Value))
				return true;
			
			$timeZone = Phpr::$config->get('TIMEZONE');
			try
			{
				$timeZoneObj = new DateTimeZone( $timeZone );
			}
			catch (Exception $Ex)
			{
				throw new Phpr_SystemException('Invalid time zone specified in config.php: '.$timeZone.'. Please refer this document for the list of correct time zones: http://docs.php.net/timezones.');
			}
			
			$result = Phpr_DateTime::parse($Value, $Params[0], $timeZoneObj);

			if ( !$result ) 
			{
				$errorMessage = $Params[1] !== null ? $Params[1] : 
					sprintf(Phpr::$lang->mod( 'phpr', 'datetime', 'validation'), $this->fieldName, Phpr_DateTime::now()->format($Params[0]));
					
				$this->validation->setError( $errorMessage, $Name );
			} else
			{
				$timeZoneObj = new DateTimeZone( 'GMT' );
				$result->setTimeZone($timeZoneObj);
				unset($timeZoneObj);
				
				$result = $result->toSqlDateTime();
			}

			return $result;
		}

		/**
		 * Adds a rule that determines whether a value represents a date/time value, according the specified format.
		 * Some formats (like %x and %X) depends on the current user language date format. 
		 * This rule sets the field value to a valid SQL date format.
		 * @param string $Format Specifies an expected format. 
		 * By default the short date format (%x) used (11/6/2006 - for en_US).
		 * @param string $errorMessage Optional error message.
		 * @return Phpr_ValidationRules
		 */
		public function date( $Format = "%x", $errorMessage = null )
		{
			$this->registerInternal(__METHOD__, array($Format, $errorMessage));
			return $this;
		}

		/**
		 * Determines whether a value is a valid data and time string
		 * @param string $Name Specifies a field name
		 * @param $Value Specifies a value to validate.
		 * @param array &$Params A list of parameters passed to the Regexp method.
		 * @return string.
		 */
		protected function evalDate( $Name, $Value, &$Params )
		{
			if (is_object($Value))
				return true;

			if (!strlen($Value))
				return null;
			
			$result = Phpr_DateTime::parse($Value, $Params[0]);

			if ( !$result ) 
			{
				$errorMessage = $Params[1] !== null ? $Params[1] : 
					sprintf(Phpr::$lang->mod( 'phpr', 'datetime', 'validation'), $this->fieldName, Phpr_DateTime::now()->format($Params[0]));
					
				$this->validation->setError( $errorMessage, $Name );
			} else
				$result = $result->toSqlDate();

			return $result;
		}
	}

?>