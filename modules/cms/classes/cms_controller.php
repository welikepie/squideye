<?php

	$_cms_current_page_object = null;

	class Cms_Controller
	{
		const cms_type_page = 'CMS page';
		const cms_type_template = 'CMS template';
		const cms_type_partial = 'CMS partial';
		const cms_type_block = 'CMS page block';
		const cms_type_head = 'CMS page head';
		
		public $data = array();
		public $page;
		public $tracking_code = array();
		public $ajax_mode = false;
		
		protected static $_instance = null;

		/**
		 * Keeps the request parameters
		 * @var array
		 */
		public $request_params;
		
		public $customer = null;
		
		protected $_page_content;
		protected $_page_block_content = array();
		protected $_is_admin_authorized;
		protected $_twig_parser;
		public static $_ajax_handlers_loaded = false;

		protected $_special_query_flags = array();
		
		protected $_cms_call_stack = array();

		public static function create()
		{
			return self::$_instance = new self();
		}
		
		public static function get_instance()
		{
			return self::$_instance;
		}
		
		public function __construct($authorize = true)
		{
			if (Phpr::$config->get('OPTIMIZE_FRONTEND_QUERIES'))
				Db_ActiveRecord::$execution_context = 'front-end';

			if ($authorize)
				$this->customer = Phpr::$frontend_security->authorize_user();
		}
		
		public static function get_customer_group_id()
		{
			$controller = Cms_Controller::get_instance();

			$group_ids = Backend::$events->fireEvent('cms:onGetCustomerGroupId', $controller);
			foreach ($group_ids as $group_id) 
			{
				if (strlen($group_id))
				{
					return $group_id;
				}
			}

			if ($controller && $controller->customer)
				return $controller->customer->customer_group_id;
			else
				return Shop_CustomerGroup::get_guest_group()->id;
		}
		
		public static function get_customer_group()
		{
			$controller = Cms_Controller::get_instance();

			if ($controller && $controller->customer)
				return $controller->customer->group;
			else
				return Shop_CustomerGroup::get_guest_group();
		}

		public static function get_customer()
		{
			$controller = Cms_Controller::get_instance();

			if ($controller && $controller->customer)
				return $controller->customer;
				
			return null;
		}

		/**
		 * Outputs a specified page
		 */
		public function open($page, &$params)
		{
			global $_cms_current_page_object;
			global $activerecord_no_columns_info;
			$this->process_special_requests();
			$_cms_current_page_object = $page;

			try
			{
				/*
				 * Apply security mode
				 */
				
				$this->apply_security($page, $params);
				
				/*
				 * Add Google Analytics tracker code
				 */
				
				$gaSettings = Cms_Stats_Settings::getLazy();
				if ($gaSettings->ga_enabled && !$page->disable_ga)
					$this->add_tracking_code($gaSettings->get_ga_tracking_code());
					
				Backend::$events->fireEvent('cms:onBeforeDisplay', $page);

				/*
				 * Output the page
				 */

				$activerecord_no_columns_info = true;
				$template = $page->template;
				$activerecord_no_columns_info = false;

				$this->page = $page;
				$this->request_params = $params;
				$this->logVisit($page);

				$this->data['cms_fatal_error_message'] = null;
				$this->data['cms_error_message'] = null;
				$this->eval_page_content();
				
				$template_content = $template ? $template->get_content() : $this->_page_content;
				
				if (in_array('show_page_structure', $this->_special_query_flags))
				{
					$bootstrap = '<link rel="stylesheet" type="text/css" href="'.root_url('/modules/cms/resources/css/frontend.css').'" />';
					$template_content = preg_replace(',\</head\>,i', $bootstrap.'</head>', $template_content, 1);
				}

				ob_start();

				if ($template)
					$this->evalWithException('?>'.$template_content, Cms_Controller::cms_type_template, $template->name);
				else
					echo $template_content;
					
				$page_content = ob_get_clean();

				/*
				 * Integrate Google Analytics tracker code
				 */
				
				if ($this->tracking_code)
				{
					$this->add_tracking_code($gaSettings->get_ga_tracker_close_declaration());
					$ga_code = implode("\n", $this->tracking_code);
					$page_content = preg_replace(',\</head\>,i', $ga_code."</head>", $page_content, 1);
				}
				echo $page_content;
				
				Backend::$events->fireEvent('cms:onAfterDisplay', $page);
			} 
			catch (Exception $ex)
			{
				$this->clean_buffer();
				
				throw $ex;
			}
		}

		/**
		 * Executes a handler for a specific page
		 */
		public function handle_ajax_request($page, $handlerName, $updateElements, &$params)
		{
			$this->apply_security($page, $params);

			try
			{
				$this->page = $page;
				$this->request_params = $params;

				$this->data['cms_fatal_error_message'] = null;
				$this->data['cms_error_message'] = null;

				/*
				 * Determine whether the hanlder is a local function 
				 * or a module-provided method and run the handler
				 */

				$this->ajax_mode = true;
				
				Backend::$events->fireEvent('cms:onBeforeHandleAjax', $page);

				$handlerNameParts = explode(':', $handlerName);
				if (count($handlerNameParts) == 1)
				{
					/*
					 * Run on_action handler
					 */

					if ($handlerName == 'on_action')
					{
						$this->action();
					}
					else
					{
						/*
						 * Run the local function
						 */

						$php_is_allowed = self::is_php_allowed();

						if ($php_is_allowed) // Ignore custom AJAX handlers field if PHP is not allowed
						{
							try
							{
								if (!self::$_ajax_handlers_loaded)
								{
									self::$_ajax_handlers_loaded = true;
									$this->evalWithException($this->page->get_ajax_handlers_code(), 'Page AJAX handlers', $this->page->label ? $this->page->label : $this->page->title, array(), true);
								}
							}
							catch (Exception $ex)
							{
								$this->handleEvalException('Error executing page AJAX handlers code: ', $ex);
							}
						}

						if (!function_exists($handlerName))
							throw new Phpr_ApplicationException('AJAX handler not found: '.$handlerName);

						call_user_func($handlerName, $this, $this->page, $this->request_params);
					}
				} 
				else
				{
					Cms_ActionManager::execAjaxHandler($handlerName, $this);
				}

				/*
				 * Update page elements
				 */
				ob_start();
				foreach ($updateElements as $element=>$partial)
				{
					if(!$element)
						continue;
				
					echo ">>$element<<";
					$this->render_partial($partial);
				}
				ob_end_flush();
				
				Backend::$events->fireEvent('cms:onAfterHandleAjax', $page);
			}
			catch (Exception $ex)
			{
				$this->clean_buffer();
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/**
		 * Copies execution context from another controller instance 
		 */
		public function copy_context_from($controller)
		{
			$this->page = $controller->page;
			$this->request_params = $controller->request_params;
			$this->data = $controller->data;
			$this->customer = $controller->customer;
			$this->tracking_code = $controller->tracking_code;
			$this->ajax_mode = $controller->ajax_mode;
		}

		protected function evalWithException($code, $call_stack_object_type, $call_stack_object_name, $params = array(), $eval_as_handler = false)
		{
			$eval_result = null;
			
			try
			{
				$call_stack_obj = new Cms_CallStackItem($call_stack_object_name, $call_stack_object_type, $code);
				array_push($this->_cms_call_stack, $call_stack_obj);

				extract($this->data);
				extract($params);

				ob_start();
				$display_errors = ini_set('display_errors', 1);

				$eval_code_result = Backend::$events->fire_event(array('name' => 'cms:onEvaluateCode', 'type' => 'filter'), array(
					'page' => $this->page,
					'controller' => $this,
					'params' => array_merge($this->data, $params),
					'code' => substr($code, 2), // remove the php tag prepended to beginning of code
					'object_type' => $call_stack_object_type,
					'object_name' => $call_stack_object_name,
					'content' => null
				));

				if($eval_code_result['content'] !== null)
					$eval_result = $eval_code_result['content'];
				else
				{
					if ($eval_as_handler)
						$engine_code = 'php'; // Always eval handlers in PHP unless PHP features is disabled.
					else
						$engine_code = self::get_templating_engine_code($call_stack_object_type);

					if ($engine_code == 'php')
					{
						if (self::is_php_allowed())
							$eval_result = eval($code);
						else
							throw new Phpr_ApplicationException('PHP is not allowed in CMS templates.');
					} elseif ($engine_code == 'twig')
					{
						$object_name = $call_stack_object_type.' - '.$call_stack_object_name;
						echo $this->get_twig_parser()->parse(substr($code, 2), array_merge($this->data, $params, array('this'=>$this)), $object_name);
					}
					else
						throw new Phpr_ApplicationException('Unknown templating engine: '.$engine_code);
				}

				ini_set('display_errors', $display_errors);

				$result = ob_get_clean();
				$matches = array();

				$error_types = array('Warning', 'Parse error', 'Fatal error');
				$error = false;

				foreach ($error_types as $type)
				{
					if ($error = preg_match(',^\<br\s*/\>\s*\<b\>'.$type.'\</b\>:(.*),m', $result, $matches))
						break;
				}

				if ($error)
				{
					$errorMessage = $matches[1];
					$errorMessageText = null;
					$errorLine = null;
					$pos = strpos($errorMessage, 'in <b>');

					if ($pos !== false)
					{
						$lineFound = preg_match(',on\s*line\s*\<b\>([0-9]*)\</b\>,', $errorMessage, $matches);
						$errorMessageText = substr($errorMessage, 0, $pos);
						if ($lineFound)
							$errorLine = $matches[1];

						throw new Cms_ExecutionException($errorMessageText, $this->_cms_call_stack, $errorLine);
					} else
						throw new Cms_ExecutionException($errorMessage, $this->_cms_call_stack, null);
				}

				array_pop($this->_cms_call_stack);
				
				echo $result;
			}
			catch (Exception $ex)
			{
				$forward_exception_classes = array(
					'Cms_ExecutionException',
					'Phpr_ValidationException',
					'Phpr_ApplicationException',
					'Cms_Exception'
				);

				if (in_array(get_class($ex), $forward_exception_classes))
					throw $ex;
					
				if ($ex instanceof Twig_Error)
				{
					if (!$ex->getPrevious())
						throw new Cms_ExecutionException($ex->getMessage(), $this->_cms_call_stack, $ex->getTemplateLine());
					else 
						throw $ex->getPrevious();
				}

				if ($this->_cms_call_stack && strpos($ex->getFile(), "eval()") !== false)
					throw new Cms_ExecutionException($ex->getMessage(), $this->_cms_call_stack, $ex->getLine());

				throw $ex;
			}

			return $eval_result;
		}
		
		protected function evalHandler($code, $call_stack_object_type, $call_stack_object_name, $params = array())
		{
			try
			{
				return $this->evalWithException($code, $call_stack_object_type, $call_stack_object_name, $params, true);
			}
			catch (Phpr_ValidationException $ex)
			{
				Phpr::$session->flash['error'] = $ex->getMessage();
				return -1;
			}
			catch (Phpr_ApplicationException $ex)
			{
				// if ($ex instanceof Cms_FatalException)
				// 	throw $ex;
			
				Phpr::$session->flash['error'] = $ex->getMessage();
				return -1;
			}
			catch (Cms_Exception $ex)
			{
				Phpr::$session->flash['error'] = $ex->getMessage();
				return -1;
			}
		}
		
		protected function handleEvalException($message, $ex)
		{
			$exception_text = $message.Core_String::finalize($ex->getMessage());

			if ($ex instanceof Phpr_PhpException)
				$exception_text .= ' Line '.$ex->getLine().'.';

			throw new Exception($exception_text);
		}

		protected function logVisit($page)
		{
			Cms_Analytics::logVisit($page, Phpr::$request->getCurrentUri());
			Core_Metrics::log_pageview();
		}
		
		protected function reset_cache_request()
		{
			$caching_params = Phpr::$config->get('CACHING', array());
			$reset_cache_key = array_key_exists('RESET_PAGE_CACHE_KEY', $caching_params) ? $caching_params['RESET_PAGE_CACHE_KEY'] : null;
			if (!$reset_cache_key)
				return false;

			return Phpr::$request->getField($reset_cache_key);
		}
		
		protected function eval_page_content()
		{
			$php_is_allowed = self::is_php_allowed();

			ob_start();
			
			if ($php_is_allowed)
				$this->evalHandler($this->page->get_pre_action_code(!$php_is_allowed), 'CMS page PRE action code', $this->page->label ? $this->page->label : $this->page->title);
				
			/*
			 * We always execute the page action code even if the PRE Action code returned -1 (CMS, Validation or Application exception).
			 * In case of a fatal exception the execution stop.
			 */

			$loaded_from_cache = false;
			$cache_result = false;
	
			if (function_exists('get_page_caching_params'))
			{
				$vary_by = array();
				$versions = array();
				$ttl = null;
				$cache = get_page_caching_params($vary_by, $versions, $ttl);
				$vary_by[] = 'url';

				$key_prefix= 'page_'.str_replace('/', '', $this->page->url);
				if (Cms_Theme::is_theming_enabled())
				{
					$theme = Cms_Theme::get_active_theme();
					if ($theme)
						$key_prefix = $theme->code.'-'.$key_prefix;
				}
				
				$recache = false;
				$cache_key = Core_CacheBase::create_key($key_prefix, $recache, $vary_by, $versions);
				if ($this->reset_cache_request())
					$recache = true;
				$cache = Core_CacheBase::create();
				$page_contents = !$recache ? $cache->get($cache_key) : false;

				if ($page_contents !== false)
				{
					$loaded_from_cache = true;
					echo $page_contents;
				} else
					$cache_result = true;
			}

			if (!$loaded_from_cache)
			{
				$action_result = true;
				if (strlen($this->page->action_reference) && $this->page->action_reference != Cms_Page::action_custom)
				{
					try
					{
						Cms_ActionManager::execAction($this->page->action_reference, $this);
					}
					catch (Phpr_ValidationException $ex)
					{
						$action_result = false;
						Phpr::$session->flash['error'] = $ex->getMessage();
					}
					catch (Phpr_ApplicationException $ex)
					{
						$action_result = false;

						// if ($ex instanceof Cms_FatalException)
						// 	throw $ex;

						Phpr::$session->flash['error'] = $ex->getMessage();
					}
					catch (Cms_Exception $ex)
					{
						$action_result = false;
						Phpr::$session->flash['error'] = $ex->getMessage();
					}
				}
				
				if ($cache_result)
					ob_start();

				if ($action_result && $php_is_allowed)
					$this->evalHandler($this->page->get_post_action_code(!$php_is_allowed), 'CMS page POST action code', $this->page->label ? $this->page->label : $this->page->title);

				$this->evalWithException('?>'.$this->page->get_content_code(), Cms_Controller::cms_type_page, $this->page->label ? $this->page->label : $this->page->title);
				
				if ($cache_result)
				{
					$page_contents = ob_get_contents();
					ob_end_clean();
					$cache->set($cache_key, $page_contents, $ttl);
					echo $page_contents;
				}
			}
			
			$this->_page_content = ob_get_clean();
		}
		
		public function render_head()
		{
			ob_start();

			$this->evalWithException('?>'.$this->page->get_head_code(), Cms_Controller::cms_type_head, $this->page->label ? $this->page->label : $this->page->title);

			echo ob_get_clean();
		}
		
		public function render_block($block_code)
		{
			$block_code = mb_strtolower(trim($block_code));
			
			if (!array_key_exists($block_code, $this->_page_block_content))
			{
				$blocks = $this->page->list_blocks();
				if (array_key_exists($block_code, $blocks))
				{
					ob_start();
					$this->evalWithException('?>'.$blocks[$block_code], Cms_Controller::cms_type_block, $block_code);
					$this->_page_block_content[$block_code] = ob_get_clean();
				}
			}

			if (array_key_exists($block_code, $this->_page_block_content))
				echo $this->_page_block_content[$block_code];
		}

		public function redirect_url($default, $index = 0)
		{
			$url = $this->request_param($index);
			if (!$url)
				return $default;
				
			return root_url(str_replace("|", "/", urldecode($url)));
		}

		protected function process_special_requests()
		{
			$special_queries = array(
				'show_page_structure'
			);

			$special_query_found = false;
			foreach ($_REQUEST as $key=>$value)
			{
				if (in_array($key, $special_queries))
				{
					$this->_special_query_flags[] = $key;
					$special_query_found = true;
				}
			}

			if ($special_query_found)
				$this->http_admin_authorize();
		}
		
		protected function http_admin_authorize()
		{
			if (!isset($_SERVER['PHP_AUTH_USER']))
				$this->send_http_auth_headers();

			$user = new Users_User();
			$user = $user->findUser($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
			
			if (!$user)
				$this->send_http_auth_headers();
				
			$this->_is_admin_authorized = true;
		}
		
		protected function send_http_auth_headers()
		{
			header('WWW-Authenticate: Basic realm="Website management"');
			header('HTTP/1.0 401 Unauthorized');

			die("You are not authorized to access this page.");
		}

		protected function clean_buffer()
		{
			$handlers = ob_list_handlers();
			foreach ($handlers as $handler)
			{
				try
				{
					if (strpos($handler, 'zlib') === false)
						@ob_end_clean();

				} catch (Exception $ex) {}
			}
		}

		protected function apply_security($page, $request_params)
		{
			if ($page->protocol != 'any' && $page->protocol != 'none')
			{
				$protocol = Phpr::$request->protocol();
				if ($page->protocol != $protocol)
				{
					$ticket_id = Phpr::$frontend_security->storeTicket();
					$session_id = session_id();
					
					Phpr::$session->store();

					session_write_close();
					
					$param_str = null;
					$url_params_str = null;

					if ($request_params)
						$param_str = '/'.implode('/', $request_params).'/';
					elseif ($page->url != '/') 
						$param_str = '/';

					$url_params = Phpr::$request->get_fields;
					if (!$url_params)
						$url_params = array();

					$request_param_name = Phpr::$config->get('REQUEST_PARAM_NAME', 'q');
					if (isset($url_params[$request_param_name]))
						unset($url_params[$request_param_name]);
						
					$session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'ls_session_id');
					$frontend_ticket_param = Phpr::$config->get('TICKET_PARAM_NAME', 'ls_frontend_ticket');

					$url_params[$frontend_ticket_param] = $ticket_id;
					$url_params[$session_id_param] = $session_id;

					$url_params_encoded = array();
					foreach ($url_params as $param_name=>$param_value)
						$url_params_encoded[] = $param_name.'='.urlencode($param_value);

					$url_params_str = '?'.implode('&', $url_params_encoded);

					Phpr::$response->redirect(Phpr::$request->getRootUrl($page->protocol).
						root_url($page->url).
						$param_str.
						$url_params_str,
						true);
				}
			}
			
			if (($page->security_mode_id == Cms_SecurityMode::customers && $this->customer == null) || 
				($page->security_mode_id == Cms_SecurityMode::guests && $this->customer != null) ||
				$page->protocol == 'none')
			{
				$redir_page = $page->security_redirect;
				if ($redir_page)
				{
					$currentUri = Phpr::$request->getCurrentUri();
					$url = urlencode(str_replace('/', '|', strtolower($currentUri)) );
					
					if ($redir_page->url != '/')
						Phpr::$response->redirect(root_url($redir_page->url, true).'/'.$url);
					else
						Phpr::$response->redirect(root_url($redir_page->url));
				} else
				{
					echo "Sorry, specified page is not found."; 
					die();
				}
			}
			
			Backend::$events->fireEvent('cms:onApplyPageSecurity', $page, $request_params);
			
			if ($this->customer)
				Shop_CheckoutData::load_from_customer($this->customer);
		}
		
		/**
		 * Adds JavaScript string to output before the closing BODY tag
		 */
		protected function add_tracking_code($code)
		{
			$stop_tracking_code = false;
			$return = Backend::$events->fireEvent('cms:onBeforeTrackingCodeInclude', $code);
			foreach ($return as $value)
			{
				if ($value === false)
					$stop_tracking_code = true;
			}
			if(!$stop_tracking_code)
				$this->tracking_code[] = $code;
		}
		
		/*
		 * Service functions - use it in pages or layouts
		 */
		
		/**
		 * Returns a page parameter by its index
		 */
		public function request_param($index, $default = null)
		{
			if ($index < 0)
			{
				$length = count($this->request_params);
				$index = $length+$index;
			}

			if (array_key_exists($index, $this->request_params))
				return $this->request_params[$index];
				
			return $default;
		}

		/**
		 * Outputs a page
		 */
		public function render_page()
		{
			echo $this->_page_content;
		}

		/**
		 * Outputs a partial
		 */
		public function render_partial($name, $params = array(), $options = array('return_output' => false))
		{
			$result = null;
			
			$return_output = array_key_exists('return_output', $options) && $options['return_output'];
			if ($return_output)
				ob_start();
			
			if (in_array('show_page_structure', $this->_special_query_flags))
			{
				echo '<div class="cms_partial_wrapper" title="'.h($name).'">';
				echo '<span title="'.h($name).'" class="cms_partial_name">'.h($name).'</span>';
			}
			
			Backend::$events->fireEvent('cms:onBeforeRenderPartial', $name, $params, $options);
			
			$loaded_from_cache = false;
			$cache_result = false;
			
			if (array_key_exists('cache', $options))
			{
				$key_prefix= 'partial_'.str_replace(':', '-', $name);
				
				if (Cms_Theme::is_theming_enabled())
				{
					$theme = Cms_Theme::get_active_theme();
					if ($theme)
						$key_prefix = $theme->code.'-'.$key_prefix;
				}

				$vary_by = array_key_exists('cache_vary_by', $options) ? $options['cache_vary_by'] : array();
				$cache_versions = array_key_exists('cache_versions', $options) ? $options['cache_versions'] : array();

				$cache_ttl = array_key_exists('cache_ttl', $options) ? $options['cache_ttl'] : null;
				$recache = false;
				$cache_key = Core_CacheBase::create_key($key_prefix, $recache, $vary_by, $cache_versions);
				if ($this->reset_cache_request())
					$recache = true;
				
			  	$cache = Core_CacheBase::create();
				$partial_contents = !$recache ? $cache->get($cache_key) : false;

				if ($partial_contents !== false)
				{
					$loaded_from_cache = true;
					echo $partial_contents;
				} else
					$cache_result = true;
			}
			
			if (!$loaded_from_cache)
			{
				if ($cache_result)
					ob_start();

				$partial = Cms_Partial::find_by_name($name);
				if ($partial)
					$result = $this->evalWithException('?>'.Cms_Partial::get_content($name, $partial->html_code, $partial->file_name), Cms_Controller::cms_type_partial, $partial->name, $params); 
				else if ($this->_cms_call_stack)
					throw new Cms_ExecutionException("Partial \"$name\" not found", $this->_cms_call_stack, null, true);
				else
					throw new Phpr_ApplicationException("Partial " . $name . " not found");
					
				if ($cache_result)
				{
					$partial_contents = ob_get_contents();
					ob_end_clean();
					
					$cache->set($cache_key, $partial_contents, $cache_ttl);
					echo $partial_contents;
				}
			}
				
			if (in_array('show_page_structure', $this->_special_query_flags))
				echo "</div>";
				
			Backend::$events->fireEvent('cms:onAfterRenderPartial',  $name, $params, $options);
				
			if ($return_output)
			{
				$result = ob_get_contents();
				ob_end_clean();
			}
			
			return $result;
		}
		
		/**
		 * Executes the page action
		 */
		public function action()
		{
			if (strlen($this->page->pre_action) && self::is_php_allowed())
				eval($this->page->pre_action);
			
			if ($this->page->action_reference != Cms_Page::action_custom)
				Cms_ActionManager::execAction($this->page->action_reference, $this);

			if (strlen($this->page->action_code) && self::is_php_allowed())
				eval($this->page->action_code);
		}
		
		/**
		 * Executes a specified action handler
		 * @param string $handler Specifies a handler name, for example shop:on_addToCart
		 */
		public function exec_action_handler($handler)
		{
			return Cms_ActionManager::execAjaxHandler($handler, $this);
		}
		
		public static function is_php_allowed()
		{
			return Core_Configuration::is_php_allowed();
		}
		
		protected static function get_templating_engine_code($object_type)
		{
			$cms_object_types = array(
				Cms_Controller::cms_type_page,
				Cms_Controller::cms_type_template,
				Cms_Controller::cms_type_partial,
				Cms_Controller::cms_type_block,
				Cms_Controller::cms_type_head,
			);
			
			if (!in_array($object_type, $cms_object_types))
				return 'php';
			
			$engine = null;
			
			if (Cms_Theme::is_theming_enabled())
			{
				$theme = Cms_Theme::get_active_theme();
				if (!$theme)
					return 'php';
					
				$engine = $theme->templating_engine;
				if (!$engine)
					return 'php';
			} else
				$engine = Cms_SettingsManager::get()->default_templating_engine;
				
			if (!$engine)
				$engine = 'php';
				
			return $engine;
		}
		
		protected function get_twig_parser()
		{
			if ($this->_twig_parser)
				return $this->_twig_parser;
				
			return $this->_twig_parser = new Cms_Twig($this);
		}
		
		protected function resource_combine($type, $files, $options, $show_tag = true)
		{
			$results = Backend::$events->fire_event('cms:onBeforeResourceCombine', array(
				'type' => $type,
				'files' => $files,
				'options' => $options, 
				'show_tag' => $show_tag
			));
			
			foreach($results as $result)
				if($result)
					return $result;
		
			$files = Phpr_Util::splat($files);
			
			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
				$current_theme = $theme;
			
			$files_array = array();
			foreach ($files as $file)
			{
				$file = trim($file);
				
				if (substr($file, 0, 1) == '@')
				{
					$file = substr($file, 1);
					if (strpos($file, '/') !== 0)
						$file = '/'.$file;

					if ($current_theme)
						$file = $theme->get_resources_path().$file;
					else 
						$file = '/'.Cms_SettingsManager::get()->resources_dir_path.$file;
				}
					
				$files_array[] = 'file%5B%5D='.urlencode(trim($file));
			}
				
			$options_str = array();
			foreach ($options as $option=>$value)
			{
				if ($value)
					$options_str[] = $option.'=1';
			}
			
			$options_str = implode('&amp;', $options_str);
			if ($options_str)
				$options_str = '&amp;'.$options_str;
			
			if ($type == 'javascript') {
				$url = root_url('ls_javascript_combine/?'.implode('&amp;', $files_array).$options_str);
				
				return $show_tag ? '<script type="text/javascript" src="'.$url.'"></script>'."\n" : $url;
			}
			else {
				$url = root_url('ls_css_combine/?'.implode('&amp;', $files_array).$options_str);
				
				return $show_tag ? '<link rel="stylesheet" type="text/css" href="'.$url.'" />' : $url;
			}
		}

		public function js_combine($files, $options = array(), $show_tag = true)
		{
			return $this->resource_combine('javascript', $files, $options, $show_tag);
		}

		public function css_combine($files, $options = array(), $show_tag = true)
		{
			return $this->resource_combine('css', $files, $options, $show_tag);
		}
	}

?>