<?

	class Backend_AppearanceSettings extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';
		public $form_model_class = 'Backend_AppearanceConfiguration';

		public function index()
		{
			$this->app_page_title = 'Appearance Settings';
			$this->app_module_name = 'My Settings';
			$this->override_module_name = 'Appearance Settings';
			
			$this->viewData['form_model'] = Backend_AppearanceConfiguration::create();
		}
		
		protected function index_onSave()
		{
			try
			{
				$obj = Backend_AppearanceConfiguration::create();
				$obj->save(post($this->form_model_class, array()), $this->formGetEditSessionKey());
				
				Phpr::$session->flash['success'] = 'Appearance settings have been saved.';
				Phpr::$response->redirect(url('system/mysettings'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>