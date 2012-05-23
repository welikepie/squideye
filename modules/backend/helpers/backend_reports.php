<?

	class Backend_Reports
	{
		public static function intervalSelector($element)
		{
			$dateFormat = str_replace('%', null, Phpr::$lang->mod( 'phpr', 'short_date_format', 'dates'));
			$week = Phpr::$lang->mod( 'phpr', 'week_abbr', 'dates');

			$days = Backend_Html::loadDatesLangArr('A_weekday_', 7);
			$daysShort = Backend_Html::loadDatesLangArr('a_weekday_', 7, 7);
			$months = Backend_Html::loadDatesLangArr('n_month_', 12);
			$monthShort = Backend_Html::loadDatesLangArr('b_month_', 12);
			
			$intYears = self::formatDatesHash(Backend_ReportsData::listReportYears());
			$intMonths = self::formatDatesHash(Backend_ReportsData::listReportMonths());

			$result = "new DateRangePicker({
				inputs: [$('{$element}').getElement('.start'),$('{$element}').getElement('.end')], 
				typeHiddenElement: $('{$element}').getElement('input.type'),
				rangesHiddenElement: $('{$element}').getElement('input.ranges'),
				type: 'interval',
				displayTrigger: $('{$element}').getElement('a'),
				displayElement: $('{$element}').getElement('h4 span.interval'),
				typeDisplayElement: $('{$element}').getElement('h4 span.type'),
				onSetRange: dateRangeUpdated,
				'intYears': $intYears,
				'intMonths': $intMonths,
				'format': '$dateFormat', 
				'date': '', 
				'locale': {
					'days': [$days],
					'daysShort': [$daysShort],
					'daysMin': [$daysShort],
					'months': [$months],
					'monthsShort': [$monthShort],
					'weekMin': '$week'
				}});";

			return $result;
		}
		
		public static function unique($namespace, $value)
		{
			global $report_unique_values;
			if (!is_array($report_unique_values))
				$report_unique_values = array();

			if (!array_key_exists($namespace, $report_unique_values))
				$report_unique_values[$namespace] = array();

			if (!in_array($value, $report_unique_values[$namespace]))
			{
				$report_unique_values[$namespace][] = $value;
				return true;
			}
			
			return false;
		}
		
		protected static function formatDatesHash($array)
		{
			$result = array();
			foreach ($array as $index=>$obj)
			{
				$item = "['$obj->name', '$obj->start', '$obj->end']";
				$result[] = $item;
			}
			
			return "[".implode(', ', $result)."]";
		}
		
		public static function scoreboardDiff($current, $previous, $invert = false, $invert_data = false)
		{
			if ($previous > 0 && $current > 0 && $previous != $current)
			{
				$alg = $current > $previous;
				if ($invert_data)
					$alg = !$alg;
				
				if ($alg)
					$value = round(($current-$previous)/$previous*100, 2);
				else
					$value = -1*round(($previous-$current)/$current*100, 2);
				
				$positive = $previous > $current;
				if ($invert)
					$positive = !$positive;
				
				$class = $positive ? 'decline' : 'growth';
			
				return '<span class="'.$class.'">'.$value.'%</span>';
			} 
			
			return null;
		}
		
		public static function secondsToTime($value)
		{
			$hours = floor($value/3600);
			$mins = floor(fmod($value, 3600)/60);
			$sec = fmod($value, 60);
			
			if ($hours < 10) $hours = '0'.$hours;
			if ($mins < 10) $mins = '0'.$mins;
			if ($sec < 10) $sec = '0'.$sec;

			return $hours.':'.$mins.':'.$sec;
		}
		
		public static function scoreboardDiffCalculated($value, $invert = false)
		{
			if (strlen($value) && $value !== 0)
			{
				$positive = substr($value, 0, 1) != '-';
				if ($invert)
					$positive = !$positive;

				$class = $positive ? 'growth' : 'decline';
			
				return '<span class="'.$class.'">'.$value.'</span>';
			} 
			
			return null;
		}
	}

?>