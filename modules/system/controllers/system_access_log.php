<?

	class System_Access_Log extends Backend_SettingsController
	{
		protected $access_for_groups = array(Users_Groups::admin);

		public $implement = 'Db_ListBehavior, Db_FormBehavior';
		public $list_model_class = 'System_LoginLogRecord';
		public $list_record_url = null;

		public $list_search_enabled = true;
		public $list_search_fields = array('firstName', 'lastName', 'email', 'login');
		public $list_search_prompt = 'find users by name, login or email';
		
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
		}
		
		public function index()
		{
			#test
			$this->app_page_title = 'Access Log';
		}
	}
	
?>