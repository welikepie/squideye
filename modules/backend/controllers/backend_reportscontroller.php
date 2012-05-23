<?

	class Backend_ReportsController extends Backend_Controller
	{
		public function index()
		{
			$reports = Core_ModuleManager::listReports();
			if (!count($reports))
				Phpr::$response->redirect(url());

			foreach ($reports as $module_id=>$reports)
			{
				foreach ($reports['reports'] as $id=>$report)
				{
					Phpr::$response->redirect(url($module_id.'/'.$id.'_report'));
					break 2;
				}
			}
			
			Phpr::$response->redirect(url());
		}
	}

?>