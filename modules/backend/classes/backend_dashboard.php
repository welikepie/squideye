<?

	class Backend_Dashboard
	{
		protected static $interval_start = null;
		protected static $interval_end = null;
		
		public static function get_interval_start($as_date = false)
		{
			if (self::$interval_start !== null)
				return $as_date ? self::$interval_start : self::$interval_start->format('%x');
			
			$date = Phpr_Date::userDate(Phpr_DateTime::now())->getDate();
			self::$interval_start = $date = $date->substractInterval(new Phpr_DateTimeInterval(30));
			
			return $as_date ? $date : $date->format('%x');
		}
		
		public static function get_interval_end($as_date = false)
		{
			if (self::$interval_end !== null)
				return $as_date ? self::$interval_end : self::$interval_end->format('%x');

			$date = Phpr_Date::userDate(Phpr_DateTime::now())->getDate();
			
			$settings = Cms_Stats_Settings::get();
			if (!$settings->dashboard_display_today)
				self::$interval_end = $date = $date->substractInterval(new Phpr_DateTimeInterval(1));
			else
				self::$interval_end = $date;
			
			return $as_date ? $date : $date->format('%x');
		}
		
		public static function get_active_interval_start()
		{
			$interval_end = self::get_interval_end(true);
			return $interval_end->substractInterval(new Phpr_DateTimeInterval(6));
		}
		
		public static function evalPrevPeriod($start, $end, &$prev_start, &$prev_end)
		{
			$interval = $end->substractDateTime($start);
			$oneDayInterval = new Phpr_DateTimeInterval(1);
			$prev_end = $start->substractInterval($oneDayInterval);
			$prev_start = $prev_end->substractInterval($interval);
		}
		
		public static function timeToSeconds($str)
		{
			$time = explode(':', $str);
			return $time[0]*3600 + $time[1]*60 + $time[2];
		}
	}

?>