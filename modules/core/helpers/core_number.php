<?

	/**
	 * Core number helpers
	 */
	class Core_Number {
		/**
		 * Returns centimeters (cm) from inches (in)
		 * @param number $cm number
		 * @return number Returns centimeters (cm)
		 */
		public static function in_to_cm($in, $precision = 2) {
			return round($in * 2.54, $precision);
		}
		
		/**
		 * Returns inches (in) from centimeters (cm)
		 * @param mixed $in number
		 * @return number Returns inches (in)
		 */
		public static function cm_to_in($cm, $precision = 2) {
			return round($cm / 2.54, $precision);
		}
		
		/**
		 * Returns kilograms (kg) from pounds (lb)
		 * @param number $lb number
		 * @return number Returns kilograms (kg)
		 */
		public static function lb_to_kg($lb, $precision = 2) {
			return round($lb * 0.45359237, $precision);
		}
		
		/**
		 * Returns pounds (lb) from kilograms (kg)
		 * @param number $kg number
		 * @return number Returns pounds (lb)
		 */
		public static function kg_to_lb($kg, $precision = 2) {
			return round($kg / 0.45359237, $precision);
		}
		
		/**
		 * Returns true if the passed value is a number
		 * @param number $value number
		 * @return boolean Returns boolean
		 */
		public static function is_valid($value) {
			return preg_match('/^[0-9]*?\.?[0-9]*$/', $value);
		}
	}