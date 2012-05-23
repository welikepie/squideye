<?

	class Backend_ChartData extends Backend_DashboardController
	{
		public function unique_visits()
		{
			$this->xmlData();
			$this->layout = null;

			$start = $this->get_interval_start(true);
			$end = $this->get_interval_end(true);

			$this->viewData['sales_data'] = Shop_Orders_Report::get_totals_chart_data($start, $end);
			$this->viewData['chart_data'] = Cms_Analytics::getVisitorsChartData($start, $end);
		}
	}

?>