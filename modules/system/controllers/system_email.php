<?

	class System_Email extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Email Settings';
		public $form_model_class = 'System_EmailParams';
		
		public $form_redirect = null;
		public $form_edit_save_flash = 'Email configuration has been saved.';

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			
			$this->form_redirect = url('system/settings/');
		}

		public function index()
		{
			try
			{
				$record = System_EmailParams::get();
				if (!$record)
					throw new Phpr_ApplicationException('Email configuration is not found.');
				
				$this->edit($record->id);
				$this->app_page_title = $this->form_edit_title;
			}
			catch (exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function index_onSave()
		{
			$record = System_EmailParams::get();
			$this->edit_onSave($record->id);
		}

		protected function index_onTest()
		{
			try
			{
				$obj = System_EmailParams::get();
				$form_data = post($this->form_model_class, array());
				
				if (array_key_exists('smtp_password', $form_data) && strlen($form_data['smtp_password']))
					$form_data['smtp_password'] = base64_encode($form_data['smtp_password']);
				else
					$form_data['smtp_password'] = $obj->smtp_password;
				
				$obj->validate_data($form_data, $this->formGetEditSessionKey());
				$viewData = array();
				Core_Email::send('system', 'test_message', $viewData, 'LemonStand test notification', $this->currentUser->short_name, $this->currentUser->email, array(), $obj);
				
				echo Backend_Html::flash_message('The test message has been successfully sent.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>