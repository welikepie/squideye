<?php

	/**
	 * Back-end controller generic class
	 */
	class Backend_Controller extends Phpr_Controller
	{
		public $app_tab;
		public $app_page;
		public $app_module = null;
		public $app_page_title;
		public $app_module_name = null;
		public $app_page_subheader = null;
		public $override_module_name = null;
		public $hide_layout_tabs = false;
		public $hide_settings_links = false;
		
		protected $moduleId = null;
		protected $public_actions = array();
		
		protected $currentUser = null;
		protected $access_for_groups = null;
		protected $access_exceptions = null;

		public $list_control_panel;
		public $list_cell_individual_partial = array();

		/**
		 * Contains a list of user permissions required for accessing the controller
		 * Example: array('shop:access_reports', 'shop:manage_products')
		 * @var array
		 */
		protected $required_permissions = array();
		
		/*
		 * Creates a new BackEnd_Controller instance
		 */
		public function __construct()
		{
			Core_ModuleManager::listModules();

			parent::__construct();
			
			$this->addCss('/phproad/thirdpart/chosen/chosen.css');
			$this->addJavaScript('/phproad/thirdpart/chosen/chosen.jquery.min.js');
			$this->addJavaScript('/modules/backend/resources/javascript/jquery.tipsy.js');
			
			$this->globalHandlers[] = 'onHideHint';
			$this->globalHandlers[] = 'onPingLock';
			$this->globalHandlers[] = 'onStealLock';
			$this->globalHandlers[] = 'onFullscreen';
			$this->globalHandlers[] = 'onCustomEvent';

			$this->layoutsPath = PATH_APP.'/modules/backend/layouts';
			$this->layout = 'backend';
			$this->viewPath = PATH_APP.'/modules/'.$this->getModuleId().'/controllers/'.strtolower(get_class($this));

			$isPublicAction = in_array(Phpr::$router->action, $this->public_actions);

			if (!$isPublicAction && !Phpr_Controller::$no_permissions_check)
			{
				if ( !$isPublicAction && !Phpr::$security->cookiesUpdated )
					Phpr::$security->baseAuthorization();
			
				$this->currentUser = Phpr::$security->getUser();
				
				if (is_array($this->access_for_groups))
				{
					if (!(is_array($this->access_exceptions) && in_array(Phpr::$router->action, $this->access_exceptions)))
					{
						if (!$this->currentUser || !$this->currentUser->belongsToGroups($this->access_for_groups))
							Phpr::$response->redirect(url('/'));
					}
				}
				
				if ($this->required_permissions)
				{
					$permission_found = false;
					foreach ($this->required_permissions as $permission)
					{
						$permission_info = explode(':', $permission);
						$cnt = count($permission_info);
						if ($cnt != 2)
							throw new Phpr_SystemException('Invalid permission qualifier: '. $permission);
						
						if ($this->currentUser->get_permission($permission_info[0], $permission_info[1]))
						{
							$permission_found = true;
							break;
						}
					}
					
					if (!$permission_found)
						Phpr::$response->redirect(url('/'));
				}
			}
			
			if (!Phpr::$request->isRemoteEvent())
				Core_Metrics::update_metrics();
			else
			{
				$event_name = isset($_SERVER['HTTP_PHPR_EVENT_HANDLER']) ? $_SERVER['HTTP_PHPR_EVENT_HANDLER'] : null;
				$event_name = substr($event_name, 3, -1);
				Backend::$events->fireEvent('backend:onBeforeRemoteEvent', $this, $event_name);
			}
			
			Backend::$events->fireEvent('backend:onControllerReady', $this);
		}
		
		protected function onHideHint()
		{
			$hidden_hints = Db_UserParameters::get('hidden_hints', null, array());
			$hidden_hints[post('name')] = 1;
			Db_UserParameters::set('hidden_hints', $hidden_hints);
		}
		
		protected function onFullscreen()
		{
			Phpr::$session->set('backend_fullscreen_mode', post('fullscreen_mode'));
		}
		
		protected function onCustomEvent($id=null)
		{
			Backend::$events->fireEvent(post('custom_event_handler'), $this, $id);
		}
		
		protected function isHintVisible($name)
		{
			$hidden_hints = Db_UserParameters::get('hidden_hints', null, array());
			return !array_key_exists($name, $hidden_hints);
		}
		
		protected function onPingLock()
		{
			$lock_id = post('lock_id');
			if ($lock_id)
				Db_RecordLock::ping(post('lock_id'));
		}
		
		protected function onStealLock()
		{
			$hash = post('hash');
			if ($hash)
				Db_RecordLock::lock($hash);
				
			Phpr::$response->redirect(Phpr::$request->getReferer(post('url')));
		}
		
		// protected function onReleaseRecordLock()
		// {
		// 	Db_RecordLock::unlock(post('lock_id'));
		// }

		protected function getModuleId()
		{
			if ($this->moduleId !== null)
				return $this->moduleId;
				
			$refObj = new ReflectionObject($this);
			return $this->moduleId = basename(dirname(dirname($refObj->getFileName())));
		}
		
		public function handlePageError($exceptionObj)
		{
			Phpr::$session->flash['error'] = $exceptionObj->getMessage();
			$this->viewData['fatalError'] = true;
		}
		
		public function addPublicAction($action)
		{
			$this->public_actions[] = $action;
		}
		
		public function xmlData()
		{
			$this->layout = null;
			header("Content-type: text/xml; charset=utf-8"); 
		}
		
		public function loadView( $View, $SuppressLayout = false, $SuppressDefault = false )
		{
			Backend::$events->fireEvent('backend:onBeforeRenderPage', $this, $View);
			parent::loadView( $View, $SuppressLayout, $SuppressDefault);
		}

		public function renderPartial( $View, $Params = null, $PartialMode = true, $ForcePath = false )
		{
			Backend::$events->fireEvent('backend:onBeforeRenderPartial', $this, $View, $Params);
			parent::renderPartial( $View, $Params, $PartialMode, $ForcePath );
		}
		
		protected function renderLayout($Name = null)
		{
			Backend::$events->fireEvent('backend:onBeforeRenderLayout', $this, $Name);
			parent::renderLayout($Name);
		}
		
	}

?>