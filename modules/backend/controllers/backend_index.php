<?

	class Backend_Index extends Backend_DashboardController
	{
		public function __construct()
		{
			parent::__construct();
			$this->app_tab = 'dashboard';
		}

		public function index()
		{
			$this->checkPermissions();

			$this->app_page_title = 'Dashboard';

			try
			{
				if (Cms_Analytics::isGoogleAnalyticsEnabled())
					$this->evalStatistics();

				$this->viewData['analytics_error'] = Cms_Analytics::isGoogleAnalyticsEnabled() ? Db_ModuleParameters::get('cms', 'analytics_error') : null;
				
				$this->viewData['report_start_date'] = $this->get_active_interval_start();
				$this->viewData['report_end_date'] = $this->get_interval_end(true);
				
			} catch (Exception $ex)
			{
				$this->handlePageError($ex);
			}
		}
		
		protected function checkPermissions()
		{
			if ($this->currentUser->get_permission('backend', 'access_dashboard'))
				return;

			$tabs = Core_ModuleManager::listTabs();
			if (!count($tabs))
				Phpr::$security->kickOut();

			Phpr::$response->redirect(url($tabs[0]->moduleId.'/'.$tabs[0]->url));
		}
		
		protected function evalStatistics()
		{
			$start = $this->get_active_interval_start();
			$end = $this->get_interval_end(true);

			return Cms_Analytics::evalVisiorsStatistics($start, $end);
		}
		
		protected function evalOrdersStatistics()
		{
			$start = $this->get_active_interval_start();
			$end = $this->get_interval_end(true);

			return Shop_Orders_Report::evalTotalsStatistics($start, $end);
		}
		
		protected function evalTopPages()
		{
			$start = $this->get_active_interval_start();
			$end = $this->get_interval_end(true);
			
			return Cms_Analytics::evalTopPages($start, $end);
		}
		
		/*
		 * Indicators
		 */
		
		protected function getIndicators()
		{
			return Core_ModuleManager::listDashboardIndicators();
		}

		protected function getVisibleIndicators()
		{
			$dafault_visible = array(
				'cms_visits',
				'shop_ordertotals',
				'cms_pageviews',
				'cms_pagespervisit',
			);

			return Db_UserParameters::get('dashboard_visible_indicators', null, $dafault_visible);
		}
		
		protected function getVisibleIndicatorsInfo()
		{
			$all_indicators = $this->getIndicators();
			$visible_indicators = $this->getVisibleIndicators();

			$result = array();
			foreach ($visible_indicators as $id)
			{
				if (!array_key_exists($id, $all_indicators))
					continue;

				$result[$id] = $all_indicators[$id];
			}
			
			return $result;
		}
		
		protected function index_onSetIndicatorsOrder()
		{
			Db_UserParameters::set('dashboard_visible_indicators', post('visible_indicators', array()));
		}
		
		/*
		 * Reports
		 */
		
		protected function getReports()
		{
			return Core_ModuleManager::listDashboardReports();
		}
		
		protected function getVisibleReports()
		{
			$dafault_visible = array(
				'cms_top_pages',
				'shop_recent_orders'
			);

			return Db_UserParameters::get('dashboard_visible_reports', null, $dafault_visible);
		}
		
		protected function getVisibleReportsInfo()
		{
			$all_reports = $this->getReports();
			$visible_reports = $this->getVisibleReports();

			$result = array();
			foreach ($visible_reports as $id)
			{
				if (!array_key_exists($id, $all_reports))
					continue;

				$result[$id] = $all_reports[$id];
			}
			
			return $result;
		}
		
		protected function index_onSetReportsOrder()
		{
			Db_UserParameters::set('dashboard_visible_reports', post('visible_reports', array()));
		}
		
		/*
		 * Settings form
		 */
		
		protected function index_onDashboardSetup()
		{
			$this->renderPartial('dashboard_setup_form', array(
				'indicators'=>$this->getIndicators(),
				'reports'=>$this->getReports(),
				'visible_indicators'=>$this->getVisibleIndicators(),
				'visible_reports'=>$this->getVisibleReports()
			));
		}
		
		protected function index_onApplyDashboardSettings()
		{
			/*
			 * Eval indicators order
			 */

			$newVisibleIndicators = post('visible_indicators', array());
			$visible_indicators = $this->getVisibleIndicators();

			$ordered_indicators = array();

			foreach ($visible_indicators as $indicator)
			{
				if (in_array($indicator, $newVisibleIndicators))
					$ordered_indicators[] = $indicator;
			}
			
			foreach ($newVisibleIndicators as $indicator)
			{
				if (!in_array($indicator, $ordered_indicators))
					$ordered_indicators[] = $indicator;
			}

			/*
			 * Eval reports order
			 */

			$newVisibleReports = post('visible_reports', array());
			$visible_reports = $this->getVisibleReports();

			$ordered_reports = array();

			foreach ($visible_reports as $report)
			{
				if (in_array($report, $newVisibleReports))
					$ordered_reports[] = $report;
			}
			
			foreach ($newVisibleReports as $report)
			{
				if (!in_array($report, $ordered_reports))
					$ordered_reports[] = $report;
			}

			Db_UserParameters::set('dashboard_visible_indicators', $ordered_indicators);
			Db_UserParameters::set('dashboard_visible_reports', $ordered_reports);
			
			$this->viewData['report_start_date'] = $this->get_active_interval_start();
			$this->viewData['report_end_date'] = $this->get_interval_end(true);

			$this->renderPartial('dashboard_content');
		}
	}

?>