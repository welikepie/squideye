<?

	abstract class Backend_ChartController extends Backend_ReportingController
	{
		const rt_stacked_column = 'stacked_column';
		const rt_column = 'column';
		const rt_line = 'line';
		const rt_pie = 'pie';
		
		protected $timeUnits = array(
			'day'=>'day',
			'week'=>'week',
			'month'=>'month',
			'year'=>'year'
		);
		
		protected $amountTypes = array(
			'revenue'=>'Revenue',
			'totals'=>'Totals',
			'tax'=>'Tax',
			'shipping'=>'Shipping',
		);
		
		protected $orderStatuses = array(
			'all'=>'All orders',
			'paid'=>'Paid only',
		);

		protected $chartColors = array(
			'#0D8ECF', '#FCD202', '#B0DE09', '#FF6600', '#2A0CD0', '#CD0D74', '#CC0000', '#00CC00', '#0000CC'
		);

		protected $timeline_charts = array();

		protected $chart_types = array();
		protected $maxChartValue = 0;
		
		protected static $colorStates = array();

		public function __construct()
		{
			parent::__construct();
			$this->layout = PATH_APP.'/modules/backend/layouts/chart_report.htm';
		}

		/*
		 * Time units
		 */

		protected function getTimeUnit()
		{
			$result = Db_UserParameters::get('report_time_unit_'.get_class($this), null, 'day');
			if (!strlen($result))
				return 'day';
				
			return $result;
		}
		
		protected function index_onSetTimeUnit()
		{
			Db_UserParameters::set('report_time_unit_'.get_class($this), post('time_unit'));
		}

		protected function index_onUpdateChart()
		{
			$this->renderPartial(PATH_APP.'/modules/backend/controllers/partials/_chart.htm', null, true, true);
		}
		
		/*
		 * Amount type selector
		 */
		
		protected function index_onSetAmountType()
		{
			Db_UserParameters::set('report_amount_type_'.get_class($this), post('type_id'));
		}
		
		protected function getAmountType()
		{
			return Db_UserParameters::get('report_amount_type_'.get_class($this), null, 'revenue');
		}
		
		protected function getOrderAmountField()
		{
			$amountType = $this->getAmountType();
			
			switch ($amountType)
			{
				case 'revenue' : return '(shop_orders.total - shop_orders.goods_tax - shop_orders.shipping_tax - shop_orders.shipping_quote - ifnull(shop_orders.total_cost, 0))';
				case 'totals' : return 'shop_orders.total';
				case 'tax' : return '(shop_orders.shipping_tax + shop_orders.goods_tax)';
				case 'shipping' : return 'shop_orders.shipping_quote';
			}
			return 'shop_orders.total';
		}
		
		/*
		 * Universal parameters
		 */
		
		protected function index_onSetReportParameter()
		{
			Db_UserParameters::set('report_'.post('param').'_'.get_class($this), post('value'));
		}
		
		protected function getReportParameter($name, $default = null)
		{
			return Db_UserParameters::get('report_'.$name.'_'.get_class($this), null, $default);
		}
		
		/*
		 * Order status selector (paid/all)
		 */

		protected function index_onSetOrderPaidStatus()
		{
			Db_UserParameters::set('report_order_paid_status_'.get_class($this), post('status_id'));
		}
		
		protected function getOrderPaidStatus()
		{
			return Db_UserParameters::get('report_order_paid_status_'.get_class($this), null, 'all');
		}
		
		protected function getOrderPaidStatusFilter()
		{
			$status = $this->getOrderPaidStatus();
			if ($status == 'all')
				return null;
				
			return "(exists (select shop_order_status_log_records.id from shop_order_status_log_records, shop_order_statuses where shop_order_status_log_records.order_id = shop_orders.id and shop_order_statuses.id=shop_order_status_log_records.status_id and shop_order_statuses.code='paid'))";
		}

		/*
		 * Timeline helpers
		 */
		
		protected function timeSeriesIdField()
		{
			return 'report_date';
		}
		
		protected function timeSeriesValueField()
		{
			return 'report_dates.report_date';
		}

		protected function timeSeriesDateFrameFields()
		{
			$timeUnit = $this->getTimeUnit();
			switch ($timeUnit)
			{
				case 'day': return '';
				case 'week': return ', week_start_formatted, week_end_formatted, week_number';
				case 'month': return ', month_start_formatted, month_end_formatted';
				case 'quarter': return ', quarter_start_formatted, quarter_end_formatted';
				case 'year': return ', year_start_formatted, year_end_formatted';
			}
		}
		
		protected function timelineFramedSerie($record, $isFirst, $isLast, &$valueAltered)
		{
			$timeUnit = $this->getTimeUnit();
			$valueAltered = false;
			
			if (!$isFirst && !$isLast || $timeUnit == 'day')
				return $record->series_value;

			$intStart = $this->get_interval_start();
			$intEnd = $this->get_interval_end();

			if ($isFirst && $isLast)
			{
				switch ($timeUnit)
				{
					case 'week': 
						if ($record->week_start_formatted == $intStart && $record->week_end_formatted == $intEnd)
							return $record->series_value;

						$valueAltered = true;
						return '<b>'.$intStart.' -<br>'.$intEnd.'</b>, #'.$record->week_number;
					case 'month': 
						if ($intStart == $record->month_start_formatted && $intEnd == $record->month_end_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'quarter':
						if ($intStart == $record->quarter_start_formatted && $intEnd == $record->quarter_end_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'year':

						if ($intStart == $record->year_start_formatted && $intEnd == $record->year_end_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
				}
			}
			
			if ($isFirst)
			{
				switch ($timeUnit)
				{
					case 'week': 
						if ($record->week_start_formatted == $intStart)
							return $record->series_value;
					
						$valueAltered = true;
						return '<b>'.$intStart.'</b> -<br>'.$record->week_end_formatted.', #'.$record->week_number;
						
					case 'month' :
						if ($intStart == $record->month_start_formatted)
							return $record->series_value;
							
						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'quarter':
						if ($intStart == $record->quarter_start_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'year':
						if ($intStart == $record->year_start_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
				}
			}
			
			if ($isLast)
			{
				switch ($timeUnit)
				{
					case 'week': 
						if ($record->week_end_formatted == $intEnd)
							return $record->series_value;
							
						$valueAltered = true;
						return $record->week_start_formatted.' -<br><b>'.$intEnd.'</b>, #'.$record->week_number;
					case 'month' :
						if ($intEnd == $record->month_end_formatted)
							return $record->series_value;
							
						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'quarter':
						if ($intEnd == $record->quarter_end_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
					case 'year':
						if ($intEnd == $record->year_end_formatted)
							return $record->series_value;

						$valueAltered = true;
						return $record->series_value.' <b>(!)</b>';
				}
			}

		}

		/*
		 * Chart types
		 */
		
		protected function getChartType()
		{
			if (!count($this->chart_types))
				throw new Phpr_SystemException('There is no chart type defined for this report.');
			
			$type = Db_UserParameters::get(get_class($this).'_report_type', null, $this->chart_types[0]);
			if (!in_array($type, $this->chart_types))
				return $this->chart_types[0];
				
			return $type;
		}
		
		protected function index_onSetChartType()
		{
			Db_UserParameters::set(get_class($this).'_report_type', post('chart_type'));
			$this->renderPartial(PATH_APP.'/modules/backend/controllers/partials/_chart.htm', null, true, true);
		}
		
		protected function getChartTypes()
		{
			return $this->chart_types;
		}

		/*
		 * Chart height
		 */
		
		protected function index_onSetChartHeight()
		{
			Db_UserParameters::set('report_chart_height', post('height'));
		}
		
		protected function getChartHeight()
		{
			return Db_UserParameters::get('report_chart_height', null, 300);
		}

		/*
		 * Data helpers
		 */
		
		protected function addToArray(&$arr, $key, &$value, $keyParams = array(), $array_key = null)
		{
			if (!array_key_exists($key, $arr))
				$arr[$key] = (object)array('values'=>array(), 'params'=>$keyParams);

			if (!strlen($array_key))
				$arr[$key]->values[] = $value;
			else
				$arr[$key]->values[$array_key] = $value;
		}
		
		protected function addMaxValue($value)
		{
			$this->maxChartValue = max($value, $this->maxChartValue); 
			return $value;
		}
		
		/*
		 * Records filter
		 */

		public function listPrepareData()
		{
			$obj = Shop_Order::create();
			$this->filterApplyToModel($obj);
			$this->applyIntervalToModel($obj);
			return $obj;
		}

		protected function applyIntervalToModel($model)
		{
			$start = Phpr_DateTime::parse($this->get_interval_start(), '%x')->toSqlDate();
			$end = Phpr_DateTime::parse($this->get_interval_end(), '%x')->toSqlDate();
			$paidFilter = $this->getOrderPaidStatusFilter();

			$model->where('date(shop_orders.order_datetime) >= ?', $start);
			$model->where('date(shop_orders.order_datetime) <= ?', $end);
			
			if ($paidFilter)
				$model->where($paidFilter);
		}

		/*
		 * Chart helpers
		 */
		
		protected function onBeforeChartRender()
		{
		}
		
		protected function getValuesAxisMargin()
		{
			return strlen(Phpr::$lang->num($this->maxChartValue, 2))*10;
		}
		
		protected function getLegendWidth()
		{
			return strlen(Phpr::$lang->num($this->maxChartValue, 2))*9;
		}
		
		protected function getDataPath()
		{
			$module = Phpr::$router->param('module');
			
			$reportName = strtolower(get_class($this));
			$reportName = preg_replace('/^'.$module.'_/', '', $reportName);

			return url('/'.$module.'/'.$reportName.'/chart_data');
		}

		protected function chartNoData(&$data)
		{
			if (!count($data))
				$this->renderPartial(PATH_APP.'/modules/backend/controllers/partials/_nodata.htm', null, true, true);
		}

		protected function chartColor($index)
		{
			return array_key_exists($index, $this->chartColors) ? 'color="'.$this->chartColors[$index].'"' : null;
		}

		abstract public function chart_data();
		
		abstract public function refererUrl();
		
		abstract public function refererName();
		
		/*
		 * Chart totals
		 */
		
		protected function index_onUpdateTotals()
		{
			$this->renderReportTotals();
		}
		
		protected function renderReportTotals()
		{
			$intervalLimit = $this->intervalQueryStrOrders();
			$filterStr = $this->filterAsString();
			
			$paidFilter = $this->getOrderPaidStatusFilter();
			if ($paidFilter)
				$paidFilter = 'and '.$paidFilter;
				
			$query_str = "from shop_orders, shop_order_statuses, shop_customers
			where shop_customers.id=customer_id and shop_order_statuses.id = shop_orders.status_id and $intervalLimit $filterStr $paidFilter";

			$query = "
				select (select count(*) $query_str) as order_num,
				(select sum(total) $query_str) as total,
				(select sum(total - goods_tax - shipping_tax - shipping_quote - ifnull(total_cost, 0)) $query_str) as revenue,
				(select sum(total_cost) $query_str) as cost,
				(select sum(goods_tax + shipping_tax) $query_str) as tax,
				(select sum(shipping_quote) $query_str) as shipping
			";
			
			$this->viewData['totals_data'] = Db_DbHelper::object($query);
			$this->renderPartial('chart_totals');
		}
	}
	

?>