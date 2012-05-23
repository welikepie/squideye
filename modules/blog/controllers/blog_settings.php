<?

	class Blog_Settings extends Backend_Controller
	{
		public $implement = 'Db_FormBehavior';

		public $form_edit_title = 'Blog Settings';
		public $form_model_class = 'Blog_Configuration';
		public $form_redirect = null;

		protected $required_permissions = array('blog:manage_settings');

		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'blog';
			$this->app_module_name = 'Blog';

			$this->app_page = 'settings';
		}
		
		public function index()
		{
			try
			{
				$this->app_page_title = 'Settings';
			
				$obj = new Blog_Configuration();
				$this->viewData['form_model'] = $obj->load();
			}
			catch (exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}
		}
		
		protected function index_onSave()
		{
			try
			{
				$obj = new Blog_Configuration();
				$obj = $obj->load();

				$obj->save(post($this->form_model_class, array()), $this->formGetEditSessionKey());
				
				echo Backend_Html::flash_message('Blog configuration have been successfully saved.');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
	}

?>