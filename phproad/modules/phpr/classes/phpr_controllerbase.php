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
	 * Phpr_ControllerBase is a base class for the application and component controllers.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	abstract class Phpr_ControllerBase extends Phpr_Validatable
	{
		protected $_suppressView = false;
		protected $_eventPostPrefix = 'ev';
		protected $_resources = array('javascript'=>array(), 'css'=>array(), 'rss'=>array());

		public $_internalMethods = array('remoteEventHandler', 'loadView', 'renderPartial', 'renderMultiple', 'eventHandler', 'remoteEventHandler', 'getViewsDirPath', 'executeAction', 'executeEnternalAction');

		/**
		 * Use ViewData to pass data to the template.
		 * @var array ViewData as a data bridge between the controller and a view.
		 */
		public $viewData = array();

		/**
		 * The Validation object. Use it to validate a form data.
		 * @var Phpr_Validation
		 */
		public $validation;

		/**
		 * Creates a new controller instance
		 */
		public function __construct()
		{
			$this->validation = new Phpr_Validation($this);
			
			parent::__construct();
		}

		/**
		 * Returns a value from the View Data array. 
		 * If the index specified does not exist, returns null.
		 * @param string $Index Specifies the View Data index.
		 * @param mixed $Default Specifies a default value
		 * @param boolean $InspectPost Indicates whether the function must look in the POST array as well
		 * in case if the value is not found in the View Data.
		 * @return mixed.
		 */
		protected function viewDataElement( $Index, $Default = null, $InspectPost = false )
		{
			if ( isset($this->viewData[$Index]) )
				return $this->viewData[$Index];
			else
				if ( !$InspectPost )
					return $Default;

			return Phpr::$request->post($Index, $Default);
		}

		/**
		 * Loads a view with the name specified.
		 * The view file must be situated in the views directory, and has the extension "htm".
		 * @param string $View Specifies the view name, without extension: "archive". 
		 */
		public function loadView( $View )
		{
			$this->renderPartial( $View, null, false );
		}

		/**
		 * Renders the specified view without applying a layout. 
		 * The view file must be situated in the views directory, and has the extension "htm".
		 * @param string $View Specifies the view name, without extension: "archive". 
		 * @param array $Params An optional list of parameters to pass to the view.
		 * @param bool $PartialMode Determines whether this method is used directly or from another method (loadView etc.)
		 * @param bool $ForcePath Use the path passed in the $View parameter instead of using the own views path directory
		 */
		public function renderPartial( $View, $Params = null, $PartialMode = true, $ForcePath = false )
		{
			extract($this->viewData);

			if ( is_array($Params) )
				extract($Params);

			if (strpos($View, '/') !== false)
				$ForcePath = true;

			if (!$ForcePath)
			{
				if ($PartialMode)
					$View = '_'.$View;

				$ViewFile = $this->getViewsDirPath()."/".strtolower($View).".htm";
			} else
				$ViewFile = $View;

			if ( file_exists($ViewFile) )
				include $ViewFile;
			elseif ($PartialMode)
				throw new Phpr_SystemException('Partial file not found: '.$ViewFile);
		}

		/**
		 * Renders multiple view files. This method works in conjunction 
		 * with the Ajax multiupdate feature
		 * @param array $Parts Specifies a list of views or strings to render and element identifiers to update.
		 * Example: $this->renderMultiple( array('Photos'=>'@photos', 'Sidebar'=>'@sidebar', 'Message'=>'File not found') );
		 */
		public function renderMultiple( $Parts )
		{
			foreach ( $Parts as $Element=>$Part )
			{
				echo ">>$Element<<";
				if ( strlen($Part) && $Part{0} == '@' )
					$this->renderPartial( substr($Part, 1), null, false );
				else
					echo $Part;
			}
		}

		/**
		 * Returns the event handler information for using with the 
		 * control helpers like Phpr_Form::Button or Phpr_Form::Anchor.
		 * @param string $EventName Specifies the event name. 
		 * The event name must be a name of the controller method.
		 * @return array Event handler information.
		 */
		public function eventHandler( $EventName )
		{
			return array( 'handler'=>$this->getEventPostName($EventName), 'remote'=>false );
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
		public function remoteEventHandler( $EventName, $Options = null )
		{
			$result = array( 'handler'=>$this->getEventPostName($EventName), 'remote'=>true );

			if ( $Options !== null )
				$result = array_merge( $result, $Options );

			return $result;
		}

		/**
		 * Executes a controller action, renders its view and returns the action resutl
		 * @param string $URI Specifies an action URI
		 * @param mixed $Params Optional. Any parameters to pass to the action
		 * @return mixed
		 */
		protected function requestAction( $URI, $Params = null )
		{
			$Controller = null;
			$Action = null;
			$Parameters = null;
			$Folder = null;

			Phpr::$Router->route( $URI, $Controller, $Action, $Parameters, $Folder );
			$Obj = Phpr::$ClassLoader->loadController($Controller, $Folder);
			if ( !$Obj )
				throw new Phpr_SystemException( "Controller $Controller is not found" );

			if ( !$Obj->_actionExists($Action, true) )
				throw new Phpr_SystemException( "Action $Action is not found in the controller $Controller" );

			if ( $Params !== null )
				$Parameters = $Params;

			return $Obj->executeEnternalAction( $Action, $Parameters );
		}

		/**
		 * Prevents the automatic view display.
		 * Call this method in the controller action or event handler 
		 * if you do not want the view to be displayed.
		 */
		public function suppressView()
		{
			$this->_suppressView = true;
		}

		/**
		 * Returns a name of the POST variable assigned with the controller event.
		 * @param string $EventName Specifies the event name.
		 * @return string
		 */
		protected function getEventPostName( $EventName )
		{
			return $this->_eventPostPrefix."{".$EventName."}";
		}

		/**
		 * @ignore
		 * Executes an event handler.
		 * This method is used by the PHP Road internally.
		 * @param string $MethodName Specifies a method name to execute.
		 * @param array $Parameters A list of the action parameters.
		 */
		public function _execEventHandler( $MethodName, $Parameters = array(), $Action = null )
		{
			if ( !$this->methodExists($MethodName) )
				throw new Phpr_SystemException( "The event handler $MethodName does not exist in the controller." );

			foreach ( $Parameters as &$Param )
				$Param = str_replace("\"", "\\\"", $Param);

			$Parameters = count($Parameters) ? "\"".implode("\",\"", $Parameters)."\"" : null;
			eval( "\$this->$MethodName($Parameters);" );
		}
		
		/**
		 * Adds JavaScript resource to the resource list. Call $this->loadResources in a view to output corresponding markup.
		 * @param string $scriptPath Specifies a path (URL) to the script
		 */
		public function addJavaScript( $scriptPath )
		{
			if (substr($scriptPath, 0, 1) == '/')
				$scriptPath = Phpr::$request->getSubdirectory().substr($scriptPath, 1);
			
			if (!in_array($scriptPath, $this->_resources['javascript']))
				$this->_resources['javascript'][] = $scriptPath;
		}
		
		/**
		 * Adds CSS resource to the resource list. Call $this->loadResources in a view to output corresponding markup.
		 * @param string $cssPath Specifies a path (URL) to the script
		 */
		public function addCss( $cssPath )
		{
			if (substr($cssPath, 0, 1) == '/')
				$cssPath = substr($cssPath, 1);

			if (!in_array($cssPath, $this->_resources['css']))
				$this->_resources['css'][] = $cssPath;
		}

		/**
		 * Adds RSS link to the resource list. Call $this->loadResources in a view to output corresponding markup.
		 * @param string $rssPath Specifies a path (URL) to the RSS channel
		 */
		public function addRss( $rssPath )
		{
			if (!in_array($rssPath, $this->_resources['rss']))
				$this->_resources['rss'][] = $rssPath;
		}
		
		/**
		 * Outputs <link> and <script> tags to load resources previously added with addJavaScript and addCss method calls
		 * @return string
		 */
		public function loadResources()
		{
			$result = null;
			
			foreach ($this->_resources['css'] as $file)
				$result .= '<link rel="stylesheet" href="'.$file.'" type="text/css"/>'."\n";
			
			foreach ($this->_resources['rss'] as $file)
				$result .= '<link title="RSS" rel="alternate" href="'.$file.'" type="application/rss+xml"/>'."\n";
				
			foreach ($this->_resources['javascript'] as $file)
				$result .= '<script type="text/javascript" src="'.$file.'"></script>'."\n";

			return $result;
		}

		/**
		 * Returns a path to the controller views directory. No trailing slashes.
		 * @return string
		 */
		abstract public function getViewsDirPath();
	}

?>