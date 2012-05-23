<?

	class Session_Index extends Backend_Controller
	{
		protected $public_actions = array('index');
		
		public function __construct()
		{
			parent::__construct(true);
		}
		
		public function index()
		{
			Phpr::$response->redirect(url('/session/handle/create'));
		}
	}

?>