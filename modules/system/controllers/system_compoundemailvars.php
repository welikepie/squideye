<?

	class System_CompoundEmailVars extends Backend_SettingsController
	{
		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'System_CompoundEmailVar';
		public $list_record_url = null;

		public $form_model_class = 'System_CompoundEmailVar';
		public $form_not_found_message = 'Variable not found';
		public $form_create_context_name = 'create';
		public $form_redirect = null;
		public $form_create_title = 'New Variable';
		public $form_edit_title = 'Edit Variable';

		public $list_search_enabled = true;
		public $list_search_fields = array('@code', '@description');
		public $list_search_prompt = 'find variables by code or description';

		public $form_edit_save_flash = 'Email variable has been successfully saved';
		public $form_create_save_flash = 'Email variable has been successfully added';
		public $form_edit_delete_flash = 'Email variable has been successfully deleted';
		
		protected $access_for_groups = array(Users_Groups::admin);

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';

			$this->list_record_url = url('/system/compoundemailvars/edit/');
			$this->form_redirect = url('/system/compoundemailvars/');
		}
		
		public function index()
		{
			try
			{
				$this->app_page_title = 'Compound Email Variables';
			}
			catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
	}

?>