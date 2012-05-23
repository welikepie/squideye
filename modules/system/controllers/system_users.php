<?

	class System_Users extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'Users_User';
		public $list_record_url = null;

		public $form_preview_title = 'User';
		public $form_create_title = 'New User';
		public $form_edit_title = 'Edit User';
		public $form_model_class = 'Users_User';
		public $form_not_found_message = 'User not found';
		public $form_redirect = null;

		public $form_edit_save_flash = 'User has been successfully saved';
		public $form_create_save_flash = 'User has been successfully added';
		public $form_edit_delete_flash = 'User has been successfully deleted';

		public $list_search_enabled = true;
		public $list_search_fields = array('@firstName', '@lastName', '@email', '@login');
		public $list_search_prompt = 'find users by name, login or email';
		
		protected $access_for_groups = array(Users_Groups::admin);
		protected $access_exceptions = array('mysettings');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_page = 'users';
			$this->app_module_name = 'System';

			$this->list_record_url = url('/system/users/edit/');
			$this->form_redirect = url('/system/users/');
		}
		
		public function index()
		{
			$this->app_page_title = 'Users';
		}
		
		/*
		 * My settings
		 */
		
		public function mysettings()
		{
			$this->edit($this->currentUser->id, 'mysettings');
			$this->app_page_title = 'Account';
			$this->app_module_name = 'My Settings';
			$this->override_module_name = 'Account';
		}
		
		protected function mysettings_onSave()
		{
			$this->form_redirect = null;
			$this->form_edit_save_flash = null;
			
			$this->edit_onSave($this->currentUser->id);

			Phpr::$session->flash['success'] = 'The account settings have been saved.';
			Phpr::$response->redirect(url('system/mysettings'));
		}
	}

?>