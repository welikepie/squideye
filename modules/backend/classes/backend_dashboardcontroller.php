<?

	class Backend_DashboardController extends Backend_ReportingController
	{
		protected function get_interval_start($as_date = true)
		{
			return Backend_Dashboard::get_interval_start($as_date);
		}
		
		protected function get_interval_end($as_date = false)
		{
			return Backend_Dashboard::get_interval_end($as_date);
		}
		
		protected function get_active_interval_start()
		{
			return Backend_Dashboard::get_active_interval_start(true);
		}
	}

?>