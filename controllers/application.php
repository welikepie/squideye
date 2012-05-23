<?

	class Application extends Phpr_Controller
	{
		
		protected $globalHandlers = array('onHandleRequest');

		protected function resolve_page(&$params)
		{
			$maintenance_config = Cms_MaintenanceParams::create();
			if ($maintenance_config->enabled && !Phpr::$security->getUser())
			{
				$page = $maintenance_config->get_maintenance_page();
				if ($page)
				{
					header('HTTP/1.1 503 Service Unavailable');
					return $page;
				}
			}
			
			$action = strtolower(Phpr::$request->getCurrentUri());
			$params = null;

			$custom_pages = Backend::$events->fireEvent('cms:onBeforeRoute', $action);
			$page = null;
			foreach ($custom_pages as $custom_page_data)
			{
				if ($custom_page_data || !is_array($custom_page_data))
				{
					$page = $custom_page_data['page'];
					$params = $custom_page_data['params'];
					break;
				}
			}

			$page = $page ? $page : Cms_Page::findByUrl($action, $params);
			if (!$page || !$page->visible_for_customer_group(Cms_Controller::get_customer_group_id()) || !$page->is_published)
			{
				// look for a handler to handle 404 pages
				$results = Backend::$events->fireEvent('cms:onPageNotFound');
				
				foreach($results as $result) {
					if($result === true)
						exit;
				}
			
				$page = Cms_Page::findByUrl('/404', $params);
				if ($page)
					header("HTTP/1.0 404 Not Found");
			}
			if (!$page)
				$page = Cms_Page::findByUrl('/', $params);
			
			if (!$page)
			{
				echo "Sorry, specified page is not found."; 
				exit;
			}
			
			return $page;
		}

		public function On404()
		{
			// try to find an access point
			if($this->find_access_point())
				return;

			// open requested page
			$params = array();
			$Cms_Controller = Cms_Controller::create();
			$Cms_Controller->open($this->resolve_page($params), $params);
		}
		
		public function download_product_file($file_id, $order_hash, $mode = null)
		{
			$this->suppressView();
			
			try
			{
				$customer = Phpr::$frontend_security->authorize_user();
				if (!$customer)
					die("File not found");

				if (!strlen($file_id))
					die("File not found");

				if (!strlen($order_hash))
					die("File not found");

				$order = Shop_Order::create()->find_by_order_hash($order_hash);
				if (!$order || !$order->is_paid() || $order->customer_id != $customer->id)
					die("File not found");

				foreach ($order->items as $item)
				{
					foreach ($item->product->files as $file)
					{
						if ($file->id == $file_id)
						{
							if ($mode != 'inline' || $mode != 'attachment')
								$mode = 'inline';
							
							$file->output($mode);
							die();
						}
					}
				}
				
				die("File not found");
			}
			catch (Exception $ex)
			{
				echo $ex->getMessage();
			}
		}

		protected function onHandleRequest()
		{
			Cms_Controller::create()->handle_ajax_request(
				$this->resolve_page($params), 
				post('cms_handler_name'), 
				post('cms_update_elements', array()),
				$params);
		}

		private function find_access_point()
		{
			try
			{
				$action = substr(Phpr::$request->getCurrentUri(), 1);
				
				$url_parts = explode('/', $action);
				$meaning_parts = array();
				foreach ($url_parts as $part)
				{
					if (strlen($part))
						$meaning_parts[] = $part;
				}
				
				if (!$meaning_parts)
					return false;

				$action = mb_strtolower(array_shift($meaning_parts));

				/*
				 * Process payment methods access points
				 */
				if (substr($action, 0, 3) == 'ls_')
				{
					$payment_types = Core_ModuleManager::findById('shop')->listPaymentTypes();
					foreach ($payment_types as $type)
					{
						$obj = new $type();
						$points = $obj->register_access_points(); 
						if (is_array($points))
						{
							foreach ($points as $url=>$method)
							{
								if ($url == $action)
								{
									$obj->$method($meaning_parts);
									return true;
								}
							}
						}
					}
				}
				
				/*
				 * Process modules access points
				 */
				$modules = Core_ModuleManager::listModules();
				foreach ($modules as $module)
				{
					$points = $module->register_access_points(); 
					if (is_array($points))
					{
						foreach ($points as $url=>$method)
						{
							if ($url == $action)
							{
								$module->$method($meaning_parts);
								return true;
							}
						}
					}
				}
			} catch (Exception $ex) {
				Cms_Controller::create();
				$this->OnException($ex);
				return true;
			}

			return false;
		}
		
		public function backend_theme_styles_hidden_url()
		{
			$this->layout = null;
			$this->suppressView();
			
			header("Content-type: text/css; charset=utf-8"); 

			$theme = System_ColorThemeParams::get();
			if ($theme)
			{
				$path = PATH_APP."/modules/backend/themes/".$theme->theme_id."/css/theme.css";
				if (file_exists($path))
					Phpr_Files::readFile($path);
			}
		}

		public function OnException($exception)
		{
			$this->layout = null;
			
			if (Phpr::$config->get('HIDE_ERROR_DETAILS') && Cms_Controller::get_instance())
			{
				if (!Phpr::$request->isRemoteEvent())
				{
					$this->setViewsDirPath('controllers/application');
					if (Phpr::$config->get('DISPLAY_ERROR_LOG_ID') || Phpr::$config->get('DISPLAY_ERROR_LOG_STRING'))
					{
						$this->viewData['error'] = Phpr_ErrorLog::get_exception_details($exception);
						$this->loadView( PATH_SYSTEM."/errorpages/frontend_exception.htm", false, true );
					} else 
						$this->loadView( 'error_page', false, true );
				} else {
					try
					{
						$new_exception = new Phpr_ApplicationException('Some error occurred');
						Phpr::$response->ajaxReportException($new_exception, true);
					}
					catch (exception $ex) {
						die('Some error occurred.');
					}
				}
			}
			else
			{
				$handlers = ob_list_handlers();
				foreach ($handlers as $handler)
				{
					if (strpos($handler, 'zlib') === false)
						ob_end_clean();
				}

				if (!Phpr::$request->isRemoteEvent())
				{
					$this->viewData['error'] = Phpr_ErrorLog::get_exception_details($exception);
					$this->loadView( PATH_SYSTEM."/errorpages/exception.htm", false, true );
				} else {
					Phpr::$response->ajaxReportException($exception, true);
				}
			}
		}
		
		public function javascript_combine()
		{
			header("Content-type: text/javascript; charset: UTF-8");
			header("Cache-Control: must-revalidate");
			$expires = "Expires: " . gmdate("D, d M Y H:i:s", time() + 3600) . " GMT";
			header($expires);
			
			$files = Phpr::$request->getField('file');
			foreach ($files as $file)
			{
				$file = urldecode($file);
				if (strtolower(substr($file, -3)) == '.js')
					Phpr_Files::readFile(PATH_APP.$file);
			}
		}
	}

?>