<?

	class System_Email_Layouts extends Backend_SettingsController
	{
		public $implement = 'Db_FormBehavior';

		public $form_model_class = 'System_EmailLayout';
		public $form_not_found_message = 'Layout not found';
		public $form_redirect = null;
		public $form_edit_title = 'Edit Layout';

		public $form_edit_save_flash = 'Email layout has been successfully saved';
		public $form_create_save_flash = 'Email layout has been successfully added';
		public $form_edit_delete_flash = 'Email template has been successfully deleted';
		
		protected $access_for_groups = array(Users_Groups::admin);

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
			$this->app_page_title = 'Layout';

			$this->list_record_url = url('/system/email_layout/edit/');
			$this->form_redirect = url('/system/email_templates/');
		}
		
		public function layout($code)
		{
			try
			{
				$layout = System_EmailLayout::find_by_code($code);
				Phpr::$response->redirect(url('/system/email_layouts/edit/'.$layout->id));
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
	}

?>