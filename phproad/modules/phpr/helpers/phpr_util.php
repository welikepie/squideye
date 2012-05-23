<?php

	/**
	 * PHP Road
	 *
	 * PHP application framework
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov, Andy Chentsov
	 * @since		Version 1.0
	 * @filesource
	 */

	/**
	 * PHP Road general-purpose utility
	 *
	 * This class contains functions used by other PHP Road classes.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Util
	{
		/**
		 * Converts the argument passed in to an array if it is defined and not already an array.
		 *
		 * @param mixed $value
		 * @return mixed[]
		 */
		public static function splat($value, $explode = false) 
		{
			if (is_string($value) && $explode)
				$value = explode(',', $value);

			if (!is_array($value))
				$value = array($value);

			return $value;
		}

		/**
		 * Converts the argument passed in to an array (argument as key) if it is defined and not already an array.
		 *
		 * @param mixed $value
		 * @return mixed[]
		 */
		public static function splat_keys($value, $strict = true) 
		{
			if (!is_array($value)) 
			{
				if ($strict && (is_null($value) || (is_string($value) && (trim($value) == ''))))
					return $value;

				$value = array($value => array());
			}
			
			return $value;
		}

		/**
		 * Set value for each key in array
		 *
		 * @param mixed[] $array
		 * @param mixed $value
		 * @return mixed[]
		 */
		public static function indexing($array, $value) 
		{
			$keys = array_keys($array);
			$result = array();

			foreach($keys as $key) 
			{
				if (!is_string($key)) 
					continue;
					
				$result[$key] = $value;
			}
			return $result;
		}

		/*
		 * Returns first non empty argument value
		 */
		public static function any()
		{
			$args = func_get_args();
			foreach ($args as $arg)
			{
				if (!empty($arg))
					return $arg;
			}

			return null;
		}
		
		/**
		* Creates an associative array from two values
		* 
		* (1, [4, 8, 12, 22]) => ([1, 4], [1, 8], [1, 12], [1, 22])
		*
		* @param mixed $first
		* @param array $second
		*/
		public static function pairs($first, $second)
		{
			$result = array();
			foreach($second as $value) 
				$result[] = array($first, $value);
				
			return $result;
		}
	}

?>