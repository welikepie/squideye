<?php

	/**
	 * Back-end reporting controller generic class
	 */
	class Backend_ReportingController extends Backend_Controller
	{
		public $implement = 'Db_ListBehavior, Db_FilterBehavior';
		
		public $list_model_class = 'Shop_Order';
		public $list_no_data_message = 'No orders found';
		public $list_items_per_page = 6;
		public $list_custom_prepare_func = null;
		
		public $filter_desc_max_len = 100;

		public $list_no_js_declarations = false;
		public $list_sorting_column = null;
		public $list_record_url = null;
		public $list_render_as_tree = null;
		public $list_search_enabled = null;
		public $list_search_prompt = null;
		public $list_name = null;
		public $list_no_setup_link = false;
		public $list_options = array();

		public $list_control_panel_partial = null;

		protected $settingsDomain = 'dashboard';
		
		protected $globalHandlers = array('onSetRange', 'onUpdateData');
		protected $maxChartValue = 0;
		
		public $filter_list_title = 'Report Filters';
		public $filter_prompt = 'Please choose records to include to the report.';
		public $filter_onApply = 'updateReportData();';
		public $filter_onRemove = 'updateReportData();';
		
		public function __construct()
		{
			$this->addJavaScript('/phproad/modules/db/behaviors/db_formbehavior/resources/javascript/datepicker.js');
			$this->addCss('/phproad/resources/css/datepicker.css');
			$this->addCss('/modules/backend/resources/css/reports.css?'.module_build('backend'));
			$this->addCss('/phproad/modules/db/behaviors/db_listbehavior/resources/css/list.css');
			parent::__construct();
			
			$this->app_tab = 'reports';
			$this->app_module = 'backend';
		}
		
		protected function get_interval_start()
		{
			$result = Db_UserParameters::get($this->settingsDomain.'_report_int_start');

			if (!strlen($result))
				return Phpr_Date::firstYearDate(Phpr_DateTime::now())->format('%x');

			return $result;
		}
		
		protected function get_interval_end()
		{
			return Db_UserParameters::get($this->settingsDomain.'_report_int_end', null, Phpr_DateTime::now()->format('%x'));
		}

		protected function get_interval_type()
		{
			return Db_UserParameters::get($this->settingsDomain.'_report_int_type', null, 'day');
		}
		
		protected function get_interval_ranges()
		{
			return Db_UserParameters::get($this->settingsDomain.'_report_int_ranges');
		}
		
		protected function set_interval_start($value)
		{
			Db_UserParameters::set($this->settingsDomain.'_report_int_start', $value);
		}
		
		protected function set_interval_end($value)
		{
			Db_UserParameters::set($this->settingsDomain.'_report_int_end', $value);
		}

		protected function set_interval_type($value)
		{
			Db_UserParameters::set($this->settingsDomain.'_report_int_type', $value);
		}

		protected function set_interval_ranges($value)
		{
			Db_UserParameters::set($this->settingsDomain.'_report_int_ranges', $value);
		}
		
		protected function onSetRange()
		{
			$this->set_interval_start(post('interval_start'));
			$this->set_interval_end(post('interval_end'));
			$this->set_interval_type(post('interval_type'));
			$this->set_interval_ranges(post('interval_ranges'));

			$this->listRenderTable();
		}
		
		protected function intervalQueryStr()
		{
			$start = Phpr_DateTime::parse($this->get_interval_start(), '%x')->toSqlDate();
			$end = Phpr_DateTime::parse($this->get_interval_end(), '%x')->toSqlDate();
			
			$result = " report_date >= '$start' and report_date <= '$end'";
			return $result;
		}
		
		protected function intervalQueryStrOrders()
		{
			$start = Phpr_DateTime::parse($this->get_interval_start(), '%x')->toSqlDate();
			$end = Phpr_DateTime::parse($this->get_interval_end(), '%x')->toSqlDate();
			
			$result = " date(order_datetime) >= '$start' and date(order_datetime) <= '$end'";
			return $result;
		}

		/*
		 * Data helpers
		 */
		
		protected function addToArray(&$arr, $key, &$value, $keyParams = array())
		{
			if (!array_key_exists($key, $arr))
				$arr[$key] = (object)array('values'=>array(), 'params'=>$keyParams);
				
			$arr[$key]->values[] = $value;
		}
		
		protected function addMaxValue($value)
		{
			$this->maxChartValue = max($value, $this->maxChartValue); 
			return $value;
		}
	}
	
?>