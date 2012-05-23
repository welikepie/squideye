<?

	class System_Security extends Backend_Controller
	{
		protected $access_for_groups = array(Users_Groups::admin);
		
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'system';
			$this->app_module_name = 'Security';
		}
		
		public function index()
		{
			$this->app_page_title = 'Security';
			$this->app_page = 'security';
		}
	}
	
?>