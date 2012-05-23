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
	 * PHP Road Controller Base Class
	 *
	 * Phpr_Controller is a base class for the application controllers.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 */
	class Phpr_Controller extends Phpr_ControllerBase
	{
		/**
		 * Contains a reference to a currently executing controller.
		 */ 
		public static $current = null;

		/**
		 * Specifies a name of the layout.
		 * @var string
		 */
		public $layout = null;

		/**
		 * Specifies a path to the controller views directory.
		 * If this field is empty (null) the default path is used.
		 * @var string
		 */
		protected $viewPath = null;
		
		/**
		 * Specifies a path to the controller layouts directory.
		 * If this field is empty (null) the default path is used.
		 * @var string
		 */
		protected $layoutsPath = null;
		
		protected $globalHandlers = array();
		
		public static $no_permissions_check = false;

		/**
		 * Returns a path to the controller views directory. No trailing slashes.
		 * @return string
		 */
		public function getViewsDirPath()
		{
			return $this->viewPath === null ? PATH_APP."/views/".strtolower(get_class($this)) : $this->viewPath;
		}
		
		/**
		 * Allows to set the controller views directory. Use application root paths.
		 */
		public function setViewsDirPath($path)
		{
			$this->viewPath = PATH_APP.'/'.$path;
		}

		/** 
		 * This method is used by the PHP Road internally.
		 * Dispatches events, invokes the controller action or event handler and loads a corresponding view.
		 * @param string $ActionName Specifies the action name.
		 * @param array $Parameters A list of the action parameters.
		 */
		public function _run( $ActionName, $Parameters )
		{
			if ( Phpr::$request->isRemoteEvent() )
				$this->suppressView();

			// If no event was handled, execute the action requested in URI
			//
			if ( !$this->dispatchEvents( $ActionName, $Parameters ) )
				$this->executeAction( $ActionName, $Parameters );
		}

		/**
		 * Loads a view with the name specified. Applies layout if its name is provided by the controller.
		 * The view file must be situated in the views directory, and has the extension "htm".
		 * @param string $View Specifies the view name, without extension: "archive". 
		 * @param boolean $SuppressLayout Determines whether the view must be loaded without layout.
		 * @param boolean $SuppressDefault Indicates whether the default action view must be suppressed.
		 */
		public function loadView( $View, $SuppressLayout = false, $SuppressDefault = false )
		{
			// If there is no layout provided, just render the view
			//
			if ( $this->layout == '' || $SuppressLayout )
			{
				Phpr_ControllerBase::loadView($View);
				return;
			}

			// Catch the layout blocks
			//
			Phpr_View::beginBlock( "OutsideBlock" );
			parent::loadView($View);
			Phpr_View::endBlock();

			Phpr_View::appendBlock( 'view', Phpr_View::getBlock('OutsideBlock') );

			// Render the layout
			//
			$this->renderLayout();

			if ( $SuppressDefault )
				$this->suppressView();
		}

		/**
		 * Finds and executed a handler for an event triggered by client.
		 * @param string $ActionName Specifies the action name.
		 * @param array $Parameters A list of the action parameters.
		 * @return boolean Determines whether the event was handled.
		 */
		protected function dispatchEvents( $ActionName, $Parameters )
		{
			$HandlerName = isset($_SERVER['HTTP_PHPR_EVENT_HANDLER']) ? $_SERVER['HTTP_PHPR_EVENT_HANDLER'] : null;

			if (!$HandlerName)
				return false;
			
			$matches = null;

			foreach ($this->globalHandlers as $globalHandler)
			{
				if ( $this->_eventPostPrefix."{".$globalHandler."}" == $HandlerName )
				{
					$this->_execEventHandler($globalHandler, $Parameters, $ActionName);
					return true;
				}
			}

			if ( preg_match("/^".$this->_eventPostPrefix."\{(?P<handler>".$ActionName."_on[a-zA-Z_]*)\}$/i", $HandlerName, $matches) )
			{
				$this->_execEventHandler($matches["handler"], $Parameters, $ActionName);
				return true;
			}
		}

		/**
		 * This method is used by the PHP Road internally.
		 * Invokes the controller action or event handler and loads corresponding view.
		 * @param string $MethodName Specifies a method name to execute.
		 * @param array $Parameters A list of the action parameters.
		 */
		public function executeAction( $MethodName, $Parameters )
		{
			// Execute the action
			//
			call_user_func_array(array(&$this, $MethodName), $Parameters);

			// Load the view
			//
			if ( !$this->_suppressView )
				$this->loadView($MethodName);
		}

		/**
		 * This method is used by the PHP Road internally by the RequestAction method.
		 * Invokes the controller action or event handler and loads corresponding view.
		 * @param string $MethodName Specifies a method name to execute.
		 * @param array $Parameters A list of the action parameters.
		 * @return mixed
		 */
		public function executeEnternalAction( $MethodName, $Parameters )
		{
			$Result = $this->$MethodName( $Parameters );

			if ( !$this->_suppressView )
				$this->loadView($MethodName, true, true);

			return $Result;
		}

		/**
		 * @ignore
		 * Executes an event handler.
		 * This method is used by the PHP Road internally.
		 * @param string $MethodName Specifies a method name to execute.
		 * @param string $ActionName Specifies the action name.
		 * @param array $Parameters A list of the action parameters.
		 */
		public function _execEventHandler( $MethodName, $Parameters = array(), $ActionName = null )
		{
			parent::_execEventHandler($MethodName, $Parameters);

			// Load the view
			//
			if ( !$this->_suppressView )
				$this->loadView($ActionName);
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Determines whether an action with the specified name exists.
		 * Action must be a class public method. Action name can not be prefixed with the underscore character.
		 * @param string $ActionName Specifies the action name.
		 * @param bool $InternalCall Allow protected actions
		 * @return boolean
		 */
		public function _actionExists( $ActionName, $InternalCall = false )
		{
			if ( !strlen($ActionName) || substr($ActionName, 0, 1) == '_' || !$this->methodExists($ActionName) )
				return false;

			foreach ($this->_internalMethods as $method)
			{
				if ($ActionName == strtolower($method))
					return false;
			}

			$ownMethod = method_exists($this, $ActionName);

			if ($ownMethod)
			{
				$MethodInfo = new ReflectionMethod($this, $ActionName);
				$Public = $MethodInfo->isPublic();
				if ( $Public )
					return true;
			}
			
			if ( $InternalCall && (($ownMethod && $MethodInfo->isProtected()) || !$ownMethod))
				return true;
			
			if (!$ownMethod)
				return true;

			return false;
		}

		/**
		 * Renders multiple view files. This method works in conjunction 
		 * with the Ajax multiupdate feature
		 * @param array $Parts Specifies a list of views or strings to render and element identifiers to update.
		 * Example: $this->RenderMultiple( array('Photos'=>'@photos', 'Sidebar'=>'@sidebar', 'Message'=>'File not found') );
		 */
		public function renderMultiple( $Parts )
		{
			foreach ( $Parts as $Element=>$Part )
			{
				echo ">>$Element<<";
				
				if ( strpos($Part, '@@') === 0 )
					$this->renderLayout( substr($Part, 2) );
				elseif ( strlen($Part) && $Part{0} == '@' )
					$this->renderPartial( substr($Part, 1), null, false );
				else
					echo $Part;
			}
		}
		
		/**
		 * Renders a specific partial in a specific page element
		 * @param string $ElementId Specifies a page element identifier
		 * @param string $Partial Specifies a partial name to render
		 */
		public function renderMultiPartal( $ElementId, $Partial )
		{
			echo ">>$ElementId<<";
			$this->renderPartial( $Partial, null, false );
		}

		/**
		 * Prepares the view engine to rendering a partial in a specific element
		 * @param string $ElementId Specifies a page element identifier
		 */
		public function preparePartialRender( $ElementId )
		{
			echo ">>$ElementId<<";
		}

		/**
		 * Renders the layout.
		 * @param string $Name Specifies the layout name.
		 * If this parameter is omitted, the $Layout property will be used.
		 */
		protected function renderLayout( $Name = null )
		{
			extract($this->viewData);
			$Layout = $Name === null ? $this->layout : $Name;

			if ( $Layout == '' )
				return;
				
			$DirPath = $this->layoutsPath != null ? $this->layoutsPath : PATH_APP."/layouts";

			if ( strpos($Layout, '/') === false )
				$LayoutPath = $DirPath.'/'.$Layout.".htm";
			else
				$LayoutPath = $Layout;

			if ( !file_exists($LayoutPath) )
				throw new Phpr_SystemException( "The layout file \"$LayoutPath\" does not exist" );

			include $LayoutPath;
		}
		
		/**
		 * Returns a name of event handler with added action name. 
		 * Use it when you have multiple event handers on different pages (actions).
		 * You may use this method in Java Script "sendPhpr" method call as a handler name.
		 * @param string $eventName Specifies an event name
		 */
		public function getEventHandler($eventName)
		{
			return Phpr::$router->action.'_'.$eventName;
		}
	}

?>