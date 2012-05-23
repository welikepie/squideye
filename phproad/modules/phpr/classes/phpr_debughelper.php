<?

	class Phpr_DebugHelper
	{
		protected static $start_times = array();
		protected static $incremental = array();
		
		public static function start_timing($name)
		{
			self::$start_times[$name] = microtime(true);
		}
		
		public static function end_timing($name, $message = null, $add_memory_usage = false)
		{
			$time_end = microtime(true);
			
			$message = $message ? $message : $name;
			$time = $time_end - self::$start_times[$name];
			
			if ($add_memory_usage)
				$message .= ' Peak memory usage: '.Phpr_Files::fileSize(memory_get_peak_usage());

			Phpr::$traceLog->write('['.$time.'] '.$message);
		}
		
		public static function increment($name)
		{
			$time_end = microtime(true);
			$time = $time_end - self::$start_times[$name];
			if (!array_key_exists($name, self::$incremental))
				self::$incremental[$name] = 0;

			self::$incremental[$name] += $time;
		}
		
		public static function end_incremenral_timing($name, $message = null)
		{
			$message = $message ? $message : $name;
			$time = self::$incremental[$name];

			Phpr::$traceLog->write('['.$time.'] '.$message);
		}
	}
	
	Phpr_DebugHelper::start_timing('Application');

?>