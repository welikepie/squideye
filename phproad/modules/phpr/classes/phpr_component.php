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
	 * PHP Road Component Class
	 * Phpr_Component is a base class for the component controllers.
	 */
	class Phpr_Component extends Phpr_ControllerBase
	{
		/**
		 * Contains a reference to a currently executing component.
		 */
		public static $Current = null;

		/**
		 * @var string
		 * Component identificator.
		 */
		public $Id = null;

		protected $Options;
		protected $Settings;

		protected $_StoredVars = array();
		protected $_PersistingOptions = array();

		protected $_ViewPath = null;
		protected $_ViewName = null;

		/**
		 * Creates a new component controller instance
		 * @param string $Id Specifies the component identifier.
		 * Each component must has the unique identifier.
		 * @param array $Options Specifies the component options.
		 * @param array $Settings Specifies the component settings.
		 */
		public function __construct( $Id, $Options = array(), $Settings = array() )
		{
			$this->Id = $Id;
			$this->_EventPostPrefix = 'cmev'.$Id;
			$this->Options = $Options;
			$this->Settings = $Settings;

			parent::__construct();
		}

		/**
		 * The default component action. This method is called by the PHP Road
		 * always then the component is displayed in the parent view, 
		 * excepting the cases then the component handles an event 
		 * triggered by client.
		 */
		public function Index()
		{
			//
			// Do nothing
			//
		}

		/**
		 * Renders the component.
		 * Override this method if you need to perform the view data before rendering.
		 * Do not forget to call the parent class Render() method.
		 */
		public function Render()
		{
			// Store the options
			//
			$this->StoreOptions();

			// Create the form hidden fields
			//
			$this->RenderOptions();

			// Display the view
			//
			$ViewName = $this->_ViewName === null ? get_class($this) : $this->_ViewName;

			$this->LoadView($ViewName);
		}

		/**
		 * Renders multiple view files. This method works in conjunction 
		 * with the Ajax multiupdate feature
		 * @param array $Parts Specifies a list of views to render and element identifiers to update.
		 * Example: $this->RenderMultiple( array('Photos'=>'photos.htm', 'Sidebar'=>'sidebar') );
		 */
		public function RenderMultiple( $Parts, $OutputOptions = true )
		{
			// Store the options
			//
			if ( $OutputOptions )
				$this->StoreOptions();

			parent::RenderMultiple( $Parts );

			// Create the form hidden fields
			//
			if ( $OutputOptions )
				$this->RenderOptions();
		}

		/** 
		 * This method is used by the PHP Road internally.
		 * Dispatches events, invokes the controller index action or event handler and loads the component view.
		 */
		public function _Run()
		{
			if ( Phpr::$Request->IsRemoteEvent() )
				$this->SuppressView();

			// If no event was handled, execute the action requested in URI
			//
			if ( !$this->DispatchEvents() )
				$this->Index();

			// Load the view
			//
			//if ( !$this->_SuppressView )
				$this->Render();
		}

		/**
		 * @ignore
		 * Finds and executed a handler for an event triggered by client.
		 * @return boolean Determines whether the event was handled.
		 */
		public function DispatchEvents()
		{
			foreach ( $_POST as $postKey=>$postValue )
			{
				$matches = null;

				$keyParts = explode("|", $postKey);

				foreach($keyParts as $keyPart)
					if ( preg_match("/^".$this->_EventPostPrefix."\{(?P<handler>On[a-zA-Z_]*)\}$/i", $keyPart, $matches) )
					{
						$this->_ExecEventHandler($matches["handler"]);
						return true;
					}
			}

			return false;
		}

		/**
		 * Returns a path to the controller views directory. No trailing slashes.
		 * @return string
		 */
		public function GetViewsDirPath()
		{
			if ( $this->_ViewPath !== null )
				return $this->_ViewPath;

			// Component views must be placed to the views directory, that is in the same level with the controllers directory:
			// components
			//   controllers
			//      mycomponent.php
			//   views
			//      mycomponent.htm
			//
			$ClassInfo = new ReflectionClass( get_class($this) );
			$this->_ViewPath = dirname($ClassInfo->getFileName())."/../views";

			return $this->_ViewPath;
		}

		/**
		 * Returns a path to the component view file.
		 * @return string
		 */
		public function GetViewPath()
		{
			return $this->GetViewsDirPath()."/".strtolower(get_class($this)).".htm";
		}

		/**
		 * Returns the remote event handler information for using with the 
		 * control helpers like Phpr_Form::Button or Phpr_Form::Anchor.
		 * Remote events are called using the AJAX.
		 * @param string $EventName Specifies the event name. 
		 * The event name must be a name of the controller method.
		 * @param array $Options Specifies a list of Mootools AJAX request options: onComplete, evalScripts
		 * @return array Event handler information.
		 */
		public function RemoteEventHandler( $EventName, $Options = null )
		{
			$result = parent::RemoteEventHandler( $EventName, $Options );
			$result['componentId'] = $this->Id;
			$result['componentClass'] = get_class($this);

			return $result;
		}

		/**
		 * Creates a hidden form field to store the specified value.
		 * This method adds the component identifier to the variable name, 
		 * making the variable name name unique.
		 * @param string $Name Specifies the variable name.
		 * @param string $Value Specifies the variable value.
		 */
		protected function StoreVar( $Name, $Value )
		{
			$this->_StoredVars[$this->Id.$Name] = $Value;
		}

		/**
		 * Returns a value of a variable stored with the StoreVar function.
		 * If the variable requested is not found, returns null.
		 * @param string $Name Specifies the variable name.
		 * @return mixed
		 */
		protected function RestoreVar( $Name )
		{
			$VarName = $this->Id.$Name;

			if ( isset($this->_StoredVars[$VarName]) )
				return $this->_StoredVars[$VarName];

			return Phpr::$request->post($VarName);
		}

		/**
		 * Returns an option value.
		 * @param string $Name Specifies the option name.
		 * @param mixed $Default Specifies the default option value.
		 * @return mixed
		 */
		protected function GetOption( $Name, $Default = null )
		{
			if ( in_array($Name, $this->_PersistingOptions) )
			{
				if ( isset( $this->Options[$Name] ) )
					return $this->Options[$Name];

				return Phpr::$request->post($this->Id.$Name, $Default);
			} else
				if ( isset($this->Options[$Name]) )
					return $this->Options[$Name];

			return $Default;
		}

		/**
		 * Returns a component option value.
		 * @param string $Id Component identifier.
		 * @param string $Name Specifies the option name.
		 * @param mixed $Default Specifies the default option value.
		 * @return mixed
		 */
		public static function GetComponentOption( $Id, $Name, $Default )
		{
			return Phpr::$request->post($Id.$Name, $Default);
		}

		/**
		 * Returns a setting value.
		 * @param string $Name Specifies the setting name.
		 * @param mixed $Default Specifies the default setting value.
		 * @return mixed
		 */
		protected function GetSetting( $Name, $Default = null )
		{
			if ( isset( $this->Settings[$Name] ) )
				return $this->Settings[$Name];
		}

		/**
		 * Returns the composite event handler information for using with the 
		 * control helpers like Phpr_Form::Button or Phpr_Form::Anchor.
		 * Use this method if you want to handle the component event with a page 
		 * controller event handler before passing it to the component event handler.
		 * @param mixed $PrimaryHandler Specifies the primary event handler information.
		 * @param string $SecondaryHandler Specifies the name of the secondary event handler.
		 * @return array Event handler information.
		 */
		protected function CompositeEventHandler( $PrimaryHandler, $SecondaryHandler )
		{
			if ( !is_array($PrimaryHandler) && $PrimaryHandler !== null )
				return $PrimaryHandler;

			if ( $PrimaryHandler == null )
				return $SecondaryHandler;
			else
			{
				$PrimaryHandler['componentHandler'] = $SecondaryHandler['handler'];
				$PrimaryHandler['componentId'] = $this->Id;
				$PrimaryHandler['componentClass'] = get_class($this);
			}

			return $PrimaryHandler;
		}

		/**
		 * Prepares the component options to be stored in a page form.
		 */
		protected function StoreOptions()
		{
			foreach ( $this->_PersistingOptions as $OptionName )
			{
				$Value = $this->GetOption($OptionName);
				if ( $Value !== null )
					$this->StoreVar( $OptionName, $Value );
			}
		}

		/**
		 * Renders the component options hidden fields.
		 */
		protected function RenderOptions()
		{
			foreach ( $this->_StoredVars as $Name=>$Value )
				echo Phpr_Form::HiddenField( $Name, $Value );
		}
	}

?>