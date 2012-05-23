<?

	class System_Settings extends Backend_Controller
	{
		protected $access_for_groups = array(Users_Groups::admin);
		
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'System';
		}
		
		public function index()
		{
			$this->app_page_title = 'Settings';
			$this->app_page = 'settings';
			
			$this->viewData['items'] = Core_ModuleManager::listSettingsItems(true);
			$this->viewData['body_class'] = 'no_padding';
		}
	}
	
?>