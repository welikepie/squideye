<?

	class Session_Handle extends Backend_Controller
	{
		public $no_agreement_redirect = true;
		protected $public_actions = array('index', 'create', 'redirect', 'destroy', 'restore', 'restore_finish');
		
		public function __construct()
		{
			parent::__construct(true);
			$this->layout = 'login';
		}
		
		public function index()
		{
			Phpr::$response->redirect(url('/session/handle/create'));
		}
		
		public function create($path)
		{
			$this->app_page_title = 'Login';
			$this->viewData['return_path'] = $path;
			
			$agent = Phpr::$request->getUserAgent();
			$is_ie = !preg_match('/opera|webtv/i', $agent) && preg_match('/msie\s(\d)/i', $agent);
			$this->viewData['is_ie'] = $is_ie;
			
			if (post('postback'))
			{
				try
				{
					$this->do_login($path);
				} catch (Exception $ex)
				{
					Phpr::$session->flash['error'] = $ex->getMessage();
				}
			}
		}
		
		private function do_login()
		{
			Db_UpdateManager::update();
			Phpr::$session->reset();

			Phpr::$security->login( $this->validation, url('/session/handle/redirect/'.post('return_path')) );
		}
		
		protected function create_onSubmit($path)
		{
			try
			{
				$this->do_login($path);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function redirect($path)
		{
			if (strlen($path))
				Phpr::$response->redirect( root_url(str_replace("|", "/", urldecode($path))) );

			Phpr::$response->redirect( url('/') );
		}
		
		public function destroy()
		{
			Backend::$events->fireEvent('core:onLogout');
			Phpr::$security->logout();
			Phpr::$response->redirect(url('/session'));
		}

		public function restore()
		{
			$this->app_page_title = 'Password restore';
		}

		protected function restore_onSend()
		{
			try
			{
				$Validation = new Phpr_Validation();
				$Validation->add('login');
				$login = trim(post('login'));
				
				if (!strlen($login))
					$Validation->setError( 'Please specify your user name.', 'login', true );
				
				$obj = Users_User::create()->findUserByLogin($login);
				if (!$obj)
					$Validation->setError( 'User with specified name is not found.', 'login', true );

				$hash = $obj->createPasswordRestoreHash();
				$viewData = array(
					'user'=>$obj,
					'link'=>Phpr::$request->getRootUrl().url('/session/handle/restore_finish/'.$hash)
				);

				if (!Core_Email::sendOne('session', 'password_reset', $viewData, 'Password restore', $obj))
					throw new Phpr_ApplicationException('Cannot send email message. Please see the error log for  details.');
					
				$this->renderPartial('restore_success');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function restore_finish($hash)
		{
			$this->app_page_title = 'Password restore';
			
			try
			{
				$user = Users_User::create()->find_by_password_restore_hash($hash);
				if (!$user)
					throw new Phpr_ApplicationException('We are sorry, the Password Restore link you provided is not valid.');
			}
			catch (Exception $ex)
			{
				$this->viewData['error'] = $ex->getMessage();
			}
		}
		
		protected function restore_finish_onRestore($hash)
		{
			try
			{
				$user = Users_User::create()->find_by_password_restore_hash($hash);
				if (!$user)
					throw new Phpr_ApplicationException('We are sorry, the Password Restore link you provided is not valid.');
				$user->password_restore_mode = true;
				$user->save($_POST);
				$this->renderPartial('restore_finish_success');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>