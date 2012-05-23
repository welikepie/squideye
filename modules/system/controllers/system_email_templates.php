<?

	class System_Email_Templates extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'System_EmailTemplate';
		public $list_record_url = null;

		public $form_model_class = 'System_EmailTemplate';
		public $form_not_found_message = 'Template not found';
		public $form_create_context_name = 'create';
		public $form_redirect = null;
		public $form_create_title = 'New Email Template';
		public $form_edit_title = 'Edit Email Template';

		public $list_search_enabled = true;
		public $list_search_fields = array('@code', '@subject', '@description');
		public $list_search_prompt = 'find templates by code, subject or description';

		public $form_edit_save_flash = 'Email template has been successfully saved';
		public $form_create_save_flash = 'Email template has been successfully added';
		public $form_edit_delete_flash = 'Email template has been successfully deleted';
		
		protected $access_for_groups = array(Users_Groups::admin);

		public $globalHandlers = array('onTest');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';

			$this->list_record_url = url('/system/email_templates/edit/');
			$this->form_redirect = url('/system/email_templates/');
		}
		
		public function index()
		{
			try
			{
				$this->app_page_title = 'Email Templates';
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}

		protected function onTest($id)
		{
			try
			{
				$obj = strlen($id) ? $this->formFindModelObject($id) : $this->formCreateModelObject();
				$obj->validate_data(post($this->form_model_class, array()));
				$obj->send_test_message();
				
				echo Backend_Html::flash_message('The test message has been successfully sent.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function listGetRowClass($model)
		{
			$classes = $model->is_system ? null : 'important';
			return $classes;
		}
	}

?>