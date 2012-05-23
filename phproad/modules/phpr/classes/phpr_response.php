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
	 * PHP Road Response Class
	 *
	 * This class incapsulates the server respons to a web request.
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$response.
	 *
	 * @see Phpr
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Response
	{
		const actionOn404Action = 'On404';
		const actionOnException = 'OnException';
		const controllerApplication = 'Application';

		public static $defaultJsScripts = array( 'mootools.js', 'popups.js', 'phproad.js' );

		/**
		 * Opens a local URL (like "blog/edit/1")
		 * @param string $URI Specifies the URI to open.
		 */
		public function open( $URI )
		{
			$Controller = null;
			$Action = null;
			$Parameters = null;
			$Folder = null;

			Phpr::$router->route( $URI, $Controller, $Action, $Parameters, $Folder );

			if ( $Action == self::actionOn404Action || $Action == self::actionOnException )
				$this->open404();

			if ( !strlen($Controller) )
				$this->open404();

			$Obj = Phpr::$classLoader->loadController($Controller, $Folder);
			if ( !$Obj )
				$this->open404();

			if ( $Action == $Controller )
				$Action = 'Index';

			if ( !$Obj->_actionExists($Action) )
				$this->open404();

			$Obj->_run($Action, $Parameters);
		}

		/**
		 * Opens the "Page not found" page.
		 * By default this method opens a page provided by the PHP Road.
		 * You may supply the application 404 page by creating the On404() action in the Application Controller.
		 */
		public function open404()
		{
			// Try to execute the application controller On404 action.
			//
			$Controller = Phpr::$classLoader->loadController(self::controllerApplication);
			if ( $Controller != null && $Controller->_actionExists(self::actionOn404Action) )
			{
				$Controller->_run(self::actionOn404Action, array());
				exit;
			}

			// Output the default 404 message.
			//
			include PATH_SYSTEM."/errorpages/404.htm";
			exit;
		}

		/**
		 * Opens the Error Page.
		 * By default this method opens a page provided by the PHP Road.
		 * You may supply the application error page by creating the OnException($Exception) action in the Application Controller.
		 */
		public function openErrorPage($exception)
		{
			if(ob_get_length())
				ob_clean();
				
			// try to execute the application controller On404 action.
			$application = Phpr::$classLoader->loadController(self::controllerApplication);
			
			if($application != null && $application->_actionExists(self::actionOnException)) {
				$application->_run(self::actionOnException, array($exception));
				
				exit;
			}

			$error = Phpr_ErrorLog::get_exception_details($exception);
			
			// Output the default exception message.
			include PATH_SYSTEM . "/errorpages/exception.htm";
			exit;
		}

		/**
		 * Redirects the client browser and terminates the script.
		 * This function may send the 'refresh' or 'location' header, depending on the configuration settings.
		 * The 'location' header may work incorrectly on Windows servers, but it is faster.
		 * To specify the redirect method, set the configuration parameter: 
		 * $CONFIG['REDIRECT'] = 'refresh'; or $CONFIG['REDIRECT'] = 'location';
		 * By default the location method is used.
		 * @param string $Uri Specifies the target URI.
		 * @param bool $Send301Header Send 301 Moved Permanently HTTP header
		 */
		public function redirect( $Uri, $Send301Header = false )
		{
			if ( !Phpr::$request->isRemoteEvent() )
			{
				if ($Send301Header)
					header ('HTTP/1.1 301 Moved Permanently');
				
				switch (Phpr::$config->get( "REDIRECT", 'location' ))
				{
					case 'refresh' : header("Refresh:0;url=".$Uri); break;
					default : header("location:".$Uri); break;
				}
			}
			else
			{
				$output = "<script type='text/javascript'>";
				$output .= "(function(){window.location='".$Uri."';}).delay(100)";
				$output .= "</script>";
				echo $output;
			}

			die;
		}

		/**
		 * Sends a cookie.
		 * @param string $Name The name of the cookie. 
		 * @param string $Value The value of the cookie.
		 * @param string $Expire The time the cookie expires.
		 * @param string $Path The path on the server in which the cookie will be available on. 
		 * @param string $Domain The domain that the cookie is available. 
		 * @param string $Secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.
		 */
		public function setCookie( $Name, $Value, $Expire = 0, $Path = '/', $Domain = '', $Secure = false )
		{
			$_COOKIE[$Name] = $Value;

			if ( Phpr::$request->isRemoteEvent() )
			{
				if (post('no_cookies'))
					return;
				
				$output = "<script type='text/javascript'>";
				$duration = $Expire;
				$Secure = $Secure ? 'true' : 'false';
				
				$output .= "Cookie.write('$Name', '$Value', {duration: $duration, path: '$Path', domain: '$Domain', secure: $Secure});";
				$output .= "</script>";
				echo $output;
			} else
			{
				if ($Expire > 0)
					$Expire = time() + $Expire*24*3600;
					
				setcookie( $Name, $Value, $Expire, $Path, $Domain, $Secure );
			}
		}
		
		/**
		 * Deletes a cookie.
		 * @param string $Name The name of the cookie. 
		 * @param string $Path The path on the server in which the cookie will be available on. 
		 * @param string $Domain The domain that the cookie is available. 
		 * @param string $Secure Indicates that the cookie should only be transmitted over a secure HTTPS connection.
		 */
		public function deleteCookie( $Name, $Path = '/', $Domain = '', $Secure = false )
		{
			if ( Phpr::$request->isRemoteEvent() )
			{
				if (post('no_cookies'))
					return;
				
				$output = "<script type='text/javascript'>";
				$output .= "Cookie.dispose('$Name', {duration: 0, path: '$Path', domain: '$Domain'});";
				$output .= "</script>";
				echo $output;
			} else
			{
				setcookie( $Name, '', time()-360000, $Path, $Domain, $Secure );
			}
		}

		/**
		 * Sends AJAX response with information about exception.
		 * @parma mixed $Exception Specifies the exception object or message.
		 * @param boolean $Html Determines whether the response should be in HTML format.
		 * @param boolean $Focus Determines whether the focusing Java Script code must be added to a response.
		 * This parameter will work only if Exception is a Phpr_ValidationException class.
		 */
		public function ajaxReportException( $Exception, $Html = false, $Focus = false )
		{
			/*
			 * Prepare the message
			 */
			$Message = is_object($Exception) ? $Exception->getMessage() : $Exception;
			if ( $Html )
				$Message = nl2br($Message);

			/*
			 * Add focusing Java Script code
			 */
			if ( $Focus && $Exception instanceof Phpr_ValidationException )
				$Message .= $Exception->validation->getFocusErrorScript();

			/*
			 * Output headers and result
			 */
			echo "@AJAX-ERROR@";
			echo $Message;

			/*
			 * Stop the script execution
			 */
			die();
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Outputs the requested Java Script resource.
		 */
		public static function processJavaScriptRequest()
		{
			if ( !isset($_GET['phpr_js']) )
				return;

			// Sanitize the requested resource name
			//
			if ( !preg_match("/^([a-zA-Z0-9_\.]*\.js|defaults|\|)*/", $_GET['phpr_js']) )
				die;

			// Prepare the result string
			//
			$result = null;

			foreach ( explode( "|", $_GET['phpr_js'] ) as $file )
			{
				$files = ($file == "defaults") ? self::$defaultJsScripts : array($file);

				foreach ( $files as $file )
				{
					$filePath = PATH_SYSTEM."/javascript/".$file;
					if ( file_exists($filePath) )
						$result .= file_get_contents($filePath);
				}
			}

			// Prepare the output buffering with compressing
			//
			if ( function_exists('ob_gzhandler') && !!ini_get('zlib.output_compression') )
				ob_start("ob_gzhandler");

			// Output the headers
			//
			header("Content-type: text/javascript; charset: UTF-8");
			header("Vary: Accept-Encoding");

			// Output the result string
			//
			echo $result;

			die;
		}
	}

?>