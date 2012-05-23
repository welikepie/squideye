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
	 * PHP Road DateTimeInterval Class
	 *
	 * Phpr_DateTimeInterval class represents a period, or interval of time.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_DateTimeInterval
	{
		protected $intValue = 0;

		const minSecondsValue = -922337203685;
		const maxSecondsValue = 922337203685;

		/**
		 * Creates a new Phpr_DateTimeInterval instance.
		 * @param integer $Days Specifies a number of days
		 * @param integer $Hours Specifies a the number of hours
		 * @param integer $Minutes Specifies a number of minutes
		 * @param integer $Seconds Specifies a number of seconds
		 */
		public function __construct( $Days = 0, $Hours = 0, $Minutes = 0, $Seconds = 0 )
		{
			$this->setAsDaysAndTime( $Days, $Hours, $Minutes, $Seconds );
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Converts a time value to internal format.
		 * @param integer $Hour Specifies a hour
		 * @param integer $Minute Specifies a minute
		 * @param string $Second Specifies a second
		 * @return integer
		 */
		public static function convertTimeVal( $Hour, $Minute, $Second )
		{
			$Seconds = $Hour*3600 + $Minute*60 + $Second;

			if ( $Seconds > Phpr_DateTimeInterval::maxSecondsValue || $Seconds < Phpr_DateTimeInterval::minSecondsValue )
				throw new Phpr_SystemException( "Datetime interval is out of range" );

			return $Seconds*Phpr_DateTime::intInSecond;
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally
		 * Returns the integer representation of the value
		 * @return integer
		 */
		public function getInteger()
		{
			return $this->intValue;
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Sets the interval value to the value corresponding the integer value specified.
		 * @param integer $Value Specifies a integer value
		 */
		public function setInteger( $Value )
		{
			$this->intValue = $Value;
		}

		/**
		 * Returns a number of whole days in the interval.
		 * @return integer
		 */
		public function getDays()
		{
			return $this->floor( $this->intValue/Phpr_DateTime::intInDay );
		}

		/**
		 * Returns a number of whole hours in the interval.
		 * @return integer
		 */
		public function getHours()
		{
			return $this->floor( ($this->intValue / Phpr_DateTime::intInHour) % 24 );
		}

		/**
		 * Returns a number of whole minutes in the interval.
		 * @return integer
		 */
		public function getMinutes()
		{
			return $this->floor( ($this->intValue / Phpr_DateTime::intInMinute) % 60 );
		}

		/**
		 * Returns a number of whole seconds in the interval.
		 * @return integer
		 */
		public function getSeconds()
		{
			return $this->floor( $this->modulus($this->intValue / Phpr_DateTime::intInSecond, 60) );
		}

		/**
		 * Returns a total number of days in the interval.
		 * @return float
		 */
		public function getDaysTotal()
		{
			return $this->intValue/Phpr_DateTime::intInDay;
		}

		/**
		 * Returns a total number of seconds in the interval.
		 * @return float
		 */
		public function getSecondsTotal()
		{
			return $this->intValue / Phpr_DateTime::intInSecond;
		}

		/**
		 * Returns a total number of minutes in the interval.
		 * @return float
		 */
		public function getMinutesTotal()
		{
			return $this->intValue / Phpr_DateTime::intInMinute;
		}

		/**
		 * Returns a total number of hours in the interval.
		 * @return float
		 */
		public function getHoursTotal()
		{
			return $this->intValue / Phpr_DateTime::intInHour;
		}

		/**
		 * Returns a positive length of the interval.
		 * @return Phpr_DateTimeInterval
		 */
		public function length()
		{
			$Result = new Phpr_DateTimeInterval;

			if ( $this->intValue < 0 )
				$Result->setInteger( $this->intValue*(-1) );
			else
				$Result->setInteger( $this->intValue );

			return $Result;
		}

		/**
		 * Compares this object with another Phpr_DateTimeInterval object, 
		 * Returns:
		 * 1 if this object value is more than the value specified,
		 * 0 if values are equal,
		 * -1 if this object value is less than the value specified.
		 * @param Phpr_DateTimeInterval $Value Value to compare with
		 * @return integer
		 */
		public function compare( Phpr_DateTimeInterval $Value )
		{
			if ( $this->intValue > $Value->getInteger() )
				return 1;

			if ( $this->intValue < $Value->getInteger() )
				return -1;

			return 0;
		}

		/**
		 * Compares two intervals.
		 * Returns 1 if the first value is more than the second value,
		 * 0 if values are equal,
		 * -1 if the first value is less than the second value.
		 * @param Phpr_DateTimeInterval $Value1 Specifies the first interval
		 * @param Phpr_DateTimeInterval $Value2 Specifies the second interval
		 * @return integer
		 */
		public static function compareIntervals( Phpr_DateTimeInterval $Value1, Phpr_DateTimeInterval $Value2 )
		{
			if ( $Value1->getInteger() > $Value2->getInteger() )
				return 1;

			if ( $Value1->getInteger() < $Value2->getInteger() )
				return -1;

			return 0;
		}

		/**
		 * Determines whether a value of this object matches a value of the Phpr_DateTimeInterval object specified.
		 * @param Phpr_DateTimeInterval $Value Specifies a value to compare with
		 * @return boolean
		 */
		public function equals( Phpr_DateTimeInterval $Value )
		{
			return $this->intValue == $Value->getInteger();
		}

		/**
		 * Determines whether the value of this object matches the value of the Phpr_DateTimeInterval object specified.
		 * @param Phpr_DateTimeInterval $Value Value to compare with
		 * @return boolean
		 */
		public function add( Phpr_DateTimeInterval $Value )
		{
			$Result = new Phpr_DateTimeInterval();

			$Result->setInteger( $this->intValue + $Value->getInteger() );

			return $Result;
		}

		/**
		 * Substructs the specified Phpr_DateTimeInterval object from this object value 
		 * and returns a new Phpr_DateTimeInterval instance.
		 * @param Phpr_DateTimeInterval $Value Specifies the interval to substract
		 * @return Phpr_DateTimeInterval
		 */
		public function substract( Phpr_DateTimeInterval $Value )
		{
			$Result = new Phpr_DateTimeInterval();

			$Result->setInteger( $this->intValue - $Value->getInteger() );

			return $Result;
		}

		/**
		 * Sets the interval value to the specified number of hours, minutes and seconds.
		 * @param integer $Hours Specifies the number of hours
		 * @param integer $Minutes Specifies the number of minutes
		 * @param integer $Seconds Specifies the number of seconds
		 */
		public function setAsTime( $Hours, $Minutes, $Seconds )
		{
			$this->intValue = $this->convertTimeVal( $Hours, $Minutes, $Seconds );
		}

		/**
		 * Sets the interval value to the specified number of days, hours, minutes and seconds.
		 * @param integer $Days Specifies the number of days
		 * @param integer $Hours Specifies the number of hours
		 * @param integer $Minutes Specifies the number of minutes
		 * @param integer $Seconds Specifies the number of seconds
		 */
		public function setAsDaysAndTime( $Days, $Hours, $Minutes, $Seconds )
		{
			$this->intValue = $Days*(Phpr_DateTime::intInDay) + $this->convertTimeVal( $Hours, $Minutes, $Seconds );
		}
		
		/**
		 * Returns the interval value as string.
		 * Example: less than a minute
		 */
		public function intervalAsString()
		{
			$mins = floor($this->getMinutesTotal());
			if ($mins < 1)
				return 'less than a minute';

			if ($mins < 60)
				return 'about '.Phpr_Strings::word_form($mins, 'minute', true);

			$hours = floor($this->getHoursTotal());
			if ($hours < 24)
				return 'about '.Phpr_Strings::word_form($hours, 'hour', true);

			$days = floor($this->getDaysTotal());
			return Phpr_Strings::word_form($days, 'day', true);
		}

		/**
		 * Rounds variables toward negative infinity.
		 * @param Float $Value Specifies the value
		 * @return integer
		 */
		protected function floor( $Value )
		{
			if ( $Value > 0 )
				return floor( $Value );
			else
				return ceil( $Value );
		}

		/**
		 * Computes the remainder after dividing the first parameter by the second.
		 * @param integer $a Specifies the first parameter
		 * @param integer $b Specifies the second parameter
		 * @return float
		 */
		protected function modulus( $a, $b )
		{
			$neg = $a < 0;
			if ( $neg )
				$a *= -1;

			$res = $a-floor($a/$b)*$b;

			if ( $neg )
				$res *= -1;

			return $res;
		}
	}

?>