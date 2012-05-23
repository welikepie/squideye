<?php

	/**
	 * Controller behaviors base class
	 */
	class Phpr_ControllerBehavior extends Phpr_Extension
	{
		protected $_controller;
		protected $_viewPath;
		protected $_controllerCache = array();

		protected $viewData = array();
		
		protected $_alternativeViewPaths = array();
		
		public function __construct($controller)
		{
			$this->_controller = $controller;
			
			$refObj = new ReflectionObject($this);
			$this->_viewPath = dirname($refObj->getFileName()).'/'.strtolower(get_class($this)).'/partials';
			$this->extHideMethod('getBehaviorEventHandler');
		}
		
		/**
		 * Registers a controller protected methods. 
		 * These methods could be defined in a controller to override a behavior default action.
		 * Such methods should be defined as public, to allow the behavior object to access it.
		 * By default public methods of a controller are considered as actions.
		 * To prevent it such methods should be registered with this method.
		 * @param mixed $methodName Specifies a method name. Could be a string or array.
		 */
		protected function hideAction($methodName)
		{
			$methods = Phpr_Util::splat($methodName, ',');
			foreach ($methods as $method)
				$this->_controller->_internalMethods[] = trim($method);
		}
		
		/**
		 * Allows to register alternative view paths. Please use application root relative path.
		 */
		protected function registerViewPath($path)
		{
			$this->_alternativeViewPaths[] = $path;
		}

		/**
		 * This method allows to add event handlers to the behavior.
		 * @param string $eventName Specifies an event name. The behavior class must contain a method with the same name.
		 */
		protected function addEventHandler($eventName)
		{
			$this->_controller->addDynamicMethod($this, $this->_controller->getEventHandler($eventName), $eventName);
		}

		/**
		 * Returns true in case if a partial with a specified name exists in the controller.
		 * @param string $viewName Specifies a view name
		 * @return bool
		 */
		protected function controllerPartialExists($viewName)
		{
			$controllerViewPath = $this->_controller->getViewsDirPath().'/_'.$viewName.'.htm';
			return file_exists($controllerViewPath);
		}
		
		/**
		 * Returns true in case if a specified method exists in the extended controller.
		 * @param string $methodName Specifies the method name
		 * @return bool
		 */
		protected function controllerMethodExists($methodName)
		{
			return method_exists($this->_controller, $methodName);
		}

		/**
		 * Tries to render a controller partial, and if it does not exist, renders the behavior partial with the same name.
		 * @param string $viewName Specifies a view name
		 * @param array $params A list of parameters to pass to the partial file
		 * @param bool $overrideController Indicates that the controller partial should be overridden 
		 * by the behavior partial even if the controller partial does exist.
		 * @param bool $throwNotFound Indicates that an exception should be thrown in case if the partial does not exist
		 * @return bool
		 */
		protected function renderPartial($viewName, $params = array(), $overrideController = false, $throwNotFound = true)
		{
			$this->renderPartialFile($this->_controller->getViewsDirPath(), $viewName, $params, $overrideController, $throwNotFound);
		}

		/*
		 * Does the same things as renderPartial, but uses a specified controller class name for finding partials.
		 * @param string $controllerClass Specifies a controller class name. If it is null, fallbacks to renderPartial.
		 * @param string $viewName Specifies a view name
		 * @param array $params A list of parameters to pass to the partial file
		 * @param bool $overrideController Indicates that the controller partial should be overridden 
		 * by the behavior partial even if the controller partial does exist.
		 * @param bool $throwNotFound Indicates that an exception should be thrown in case if the partial does not exist
		 * @return bool
		 */
		protected function renderControllerPartial($controllerClass, $viewName, $params = array(), $overrideController = false, $throwNotFound = true)
		{
			if (!strlen($controllerClass))
				return $this->renderPartial($viewName, $params, $overrideController, $throwNotFound);

			if (array_key_exists($controllerClass, $this->_controllerCache))
				$controller = $this->_controllerCache[$controllerClass];
			else
			{
				Phpr_Controller::$no_permissions_check = true;
				$controller = $this->_controllerCache[$controllerClass] = new $controllerClass();
				Phpr_Controller::$no_permissions_check = false;
			}

			$this->renderPartialFile($controller->getViewsDirPath(), $viewName, $params, $overrideController, $throwNotFound);
		}
		
		private function renderPartialFile($controllerViewPath, $viewName, $params = array(), $overrideController = false, $throwNotFound = true)
		{
			$this->_controller->viewData = $this->viewData + $this->_controller->viewData;
			$controllerViewPath = $controllerViewPath.'/_'.$viewName.'.htm';

			if (!$overrideController && file_exists($controllerViewPath))
				$this->_controller->renderPartial($controllerViewPath, $params, true, true);
			else
			{
				$viewPath = null;
				foreach ($this->_alternativeViewPaths as $path)
				{
					if (file_exists($path.'/_'.$viewName.'.htm'))
					{
						$viewPath = $path.'/_'.$viewName.'.htm';
						break;
					}
				}
				
				if (!$viewPath)
				{
					$viewPath = $this->_viewPath.'/_'.$viewName.'.htm';
					if (!$throwNotFound && !file_exists($viewPath))
						return;
				}

				$this->_controller->renderPartial($viewPath, $params, true, true);
			}
		}
	}

?>