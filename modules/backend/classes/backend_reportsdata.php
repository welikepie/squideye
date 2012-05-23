<?

	class Backend_ReportsData
	{
		protected static $startReportingDate = false;
		
		public static function listReportYears()
		{
			$date = Phpr_Date::userDate(Phpr_DateTime::now())->getDate();
			$startDate = self::getStartReportingDate();
			if (!$startDate)
				$startDate = $date;
			
			$records =  Db_DbHelper::objectArray('
				select 
					distinct year as name, 
					year_start as start, 
					year_end as end
				from 
					report_dates
				where 
					report_dates.report_date >= :start_date
					and report_dates.report_date <= :now_date
				order by report_dates.report_date
			', array('now_date'=>$date, 'start_date'=>$startDate));

			foreach ($records as $record)
			{
				$record->start = Phpr_Datetime::parse($record->start, Phpr_Datetime::universalDateFormat)->format('%x');
				$record->end = Phpr_Datetime::parse($record->end, Phpr_Datetime::universalDateFormat)->format('%x');
			}

			return $records;
		}
		
		public static function listReportMonths()
		{
			$date = Phpr_Date::userDate(Phpr_DateTime::now())->getDate();
			$startDate = self::getStartReportingDate();
			if (!$startDate)
				$startDate = $date;

			$records = Db_DbHelper::objectArray('
				select 
					distinct month_start as name, 
					month_start as start, 
					month_end as end
				from 
					report_dates
				where 
					report_dates.report_date >= :start_date
					and report_dates.report_date <= :now_date
				order by report_dates.report_date
			', array('now_date'=>$date, 'start_date'=>$startDate));
			
			foreach ($records as $record)
			{
				$record->name = Phpr_Datetime::parse($record->name, Phpr_Datetime::universalDateFormat)->format('%n, %Y');
				$record->start = Phpr_Datetime::parse($record->start, Phpr_Datetime::universalDateFormat)->format('%x');
				$record->end = Phpr_Datetime::parse($record->end, Phpr_Datetime::universalDateFormat)->format('%x');
			}

			return $records;
		}
		
		protected static function getStartReportingDate()
		{
			if (self::$startReportingDate !== false)
				return self::$startReportingDate;

			return self::$startReportingDate = Db_DbHelper::scalar('select date(order_datetime) from shop_orders order by id limit 0,1');
		}
	}

?>