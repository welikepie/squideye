<?

	$date = new Phpr_DateTime();
	$date->setDate(2008, 1, 1);

	$interval = new Phpr_DateTimeInterval(1);
	$prevMonthCode = -1;
	$prevYear = 2008;
	$prevYearCode = -1;
	
	for ($i = 1; $i <= 3650; $i++)
	{
		$year = $date->getYear();
		$month = $date->getMonth();

		if ($prevYear != $year)
			$prevYear = $year;

		if ($prevYearCode != $year)
		{
			$prevYearCode = $year;
			$yDate = new Phpr_DateTime();
			$yDate->setDate( $year, 1, 1 );
			$yearStart = $yDate->toSqlDate();
			
			$yDate->setDate( $year, 12, 31 );
			$yearEnd = $yDate->toSqlDate();
		}

		/*
		 * Months
		 */

		$monthCode = $year.'.'.$month;
		if ($prevMonthCode != $monthCode)
		{
			$monthStart = $date->toSqlDate();
			$monthFormatted = $date->format('%m.%Y');
			$prevMonthCode = $monthCode;
			$monthEnd = Phpr_Date::lastMonthDate($date)->toSqlDate();
		}

		Db_DbHelper::query(
			"insert into report_dates(report_date, year, month, day, 
				month_start, month_code, month_end, year_start, year_end) 
				values (:report_date, :year, :month, :day, 
				:month_start, :month_code, :month_end,
				:year_start, :year_end)", 
			array(
				'report_date'=>$date->toSqlDate(),
				'year'=>$year, 
				'month'=>$date->getMonth(), 
				'day'=>$date->getDay(), 
				'month_start'=>$monthStart, 
				'month_code'=>$monthCode, 
				'month_end'=>$monthEnd,
				'year_start'=>$yearStart, 
				'year_end'=>$yearEnd 
			));
		$date = $date->addInterval($interval);
	}

?>