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
	 * PHP Road DateTime Class
	 *
	 * Phpr_DateTime class represents a date and time value and provides a datetime arithmetic functions.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_DateTime
	{
		protected $intValue = 0;
		protected $timeZone = null;

		protected $daysToMonthReg = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334, 365 );
		protected $daysToMonthLeap = array( 0, 31, 60, 91, 121, 152, 182, 213, 244, 274, 305, 335, 366 );

		const maxIntValue = 3155378975999999999;
		const maxMlSeconds = 315537897600000;
		const minMlSeconds = -315537897600000;
		const mlSecondsInDay = 86400000;
		const mlSecondsInHour = 3600000;
		const mlSecondsInMinute = 60000;
		const mlSecondsInSecond = 1000;
		const daysIn400Years = 146097;
		const daysIn100Years = 36524;
		const daysIn4Years = 1461;
		const intInDay = 864000000000;
		const intInHour = 36000000000;
		const intInMinute = 600000000;
		const intInSecond = 10000000;
		const timestampOffset = 621355968000000000;

		const elementYear = 0;
		const elementDayOfYear = 1;
		const elementMonth = 2;
		const elementDay = 3;

		/**
		 * Represents the universal date format: 2006-02-20
		 * @var string
		 */
		const universalDateFormat = '%Y-%m-%d';

		/**
		 * Represents the universal time format: 20:00:00
		 * @var string
		 */		
		const universalTimeFormat = '%H:%M:%S';
		
		/**
		 * Represents the universal date/time format: 2006-02-20 20:00:00
		 * @var string
		 */
		const universalDateTimeFormat = '%Y-%m-%d %H:%M:%S';
		
		/**
		 * Creates a new Phpr_DateTime instance and sets its value to a local date and time.
		 * @param string $DateTime Optional. Specifies the date and time in format '2006-01-01 10:00:00' to assign to the instance.
		 * If this parameter is omitted, the current time will be used.
		 * @param DateTimeZone $TimeZone Optional. Specifies the time zone to assign to the instance.
		 * If this parameter is omitted, the default time zone will be used.
		 */
		public function __construct( $DateTime = null, $TimeZone = null )
		{ 
			$this->timeZone = $TimeZone === null ? new DateTimeZone(date_default_timezone_get()) : $TimeZone;

			if ( $DateTime === null )
				$this->intValue = self::getCurrentDateTime();
			else
			{
				$Obj = Phpr_DateTimeFormat::parseDateTime($DateTime, self::universalDateTimeFormat, $TimeZone);
				if ( $Obj === false )
					throw new Phpr_ApplicationException( "Can not parse date/time string: $DateTime" );

				$this->intValue = $Obj->getInteger();
			}
		}

		public function __toString()
		{
			return $this->format( self::universalDateTimeFormat );
		}

		/**
		 * Returns a time zone associated with the date time object.
		 * @return DateTimeZone
		 */
		public function getTimeZone()
		{
			return $this->timeZone;
		}

		/**
		 * Sets the time zone for the date time object.
		 * @param DateTimeZone $TimeZone Specifies the time zone to assign to the instance.
		 */
		public function setTimeZone( DateTimeZone $TimeZone )
		{
			$diff = Phpr_DateTime::getZonesOffset( $this->timeZone, $TimeZone );

			$this->intValue -= $diff*Phpr_DateTime::mlSecondsInSecond*10000;
			$this->timeZone = $TimeZone;
		}
		
		/**
		 * Assign a time zone for the date time object, without changing the time value.
		 * @param DateTimeZone $TimeZone Specifies the time zone to assign to the instance.
		 */
		public function assignTimeZone( DateTimeZone $TimeZone )
		{
			$this->timeZone = $TimeZone;
		}

		/**
		 * Sets the object value to a date specified.
		 * @param integer $Year Specifies the year
		 * @param integer $Month Specifies the month
		 * @param integer $Day Specifies the day
		 */
		public function setDate( $Year, $Month, $Day )
		{
			$this->intValue = $this->convertDateVal( $Year, $Month, $Day );
		}

		/**
		 * Sets the object value to a date and time specified.
		 * @param integer $Year Specifies the year
		 * @param integer $Month Specifies the month
		 * @param string $Day Specifies the day
		 * @param integer $Hour Specifies the hour
		 * @param integer $Minute Specifies the minute
		 * @param string $Second Specifies the second
		 */
		public function setDateTime( $Year, $Month, $Day, $Hour, $Minute, $Second )
		{
			$this->intValue = $this->convertDateVal( $Year, $Month, $Day ) + $this->convertTimeVal( $Hour, $Minute, $Second );
		}
		
		/**
		 * Sets the object value to a date specified with a PHP timestamp
		 * @param int $timestamp PHP timestamp
		 */
		public function setPHPDateTime( $timestamp )
		{
			$this->setDateTime( 
				(int)date('Y', $timestamp),
				(int)date('n', $timestamp),
				(int)date('j', $timestamp),
				(int)date('G', $timestamp),
				(int)date('i', $timestamp),
				(int)date('s', $timestamp)
			);
		}

		/**
		 * Returns the hour component of the time represented by the object.
		 * @return integer
		 */
		public function getHour()
		{
			return floor( ($this->intValue / Phpr_DateTime::intInHour) % 24 );
		}

		/**
		 * Returns the minute component of the time represented by the object.
		 * @return integer
		 */
		public function getMinute()
		{
			return floor( ($this->intValue / Phpr_DateTime::intInMinute) % 60 );
		}

		/**
		 * Returns the second element of the time represented by the object.
		 * @return integer
		 */
		public function getSecond()
		{
			return floor( $this->modulus($this->intValue / Phpr_DateTime::intInSecond, 60) );
		}

		/**
		 * Returns the year component of the date represented by the object.
		 * @return integer
		 */
		public function getYear()
		{
			return floor( $this->convertToDateElement(Phpr_DateTime::elementYear) );
		}

		/**
		 * Returns the month component of the date, represented by the object, 1-based.
		 * @return integer
		 */
		public function getMonth()
		{
			return floor( $this->convertToDateElement(Phpr_DateTime::elementMonth) );
		}

		/**
		 * Returns the day of the month represented by the object.
		 * @return integer
		 */
		public function getDay()
		{
			return $this->convertToDateElement(Phpr_DateTime::elementDay);
		}

		/**
		 * Returns a new Phpr_DateTime object corresponding the sum of this object and a number of years specified.
		 * @param integer $Years Specifies the number of years to add.
		 * @return Phpr_DateTime
		 */
		public function addYears( $Years )
		{
			return $this->addMonths( $Years * 12 );
		}

		/**
		 * Returns a new Phpr_DateTime object corresponding the sum of this object 
		 * and a number of months specified.
		 * @param integer $Months Specifies a number of months to add.
		 * @return Phpr_DateTime
		 */
		public function addMonths( $Months )
		{
			if ( $Months < -120000 || $Months > 120000 )
				throw new Phpr_ApplicationException( "Month is out of range" );

			$Year = $this->convertToDateElement( Phpr_DateTime::elementYear );
			$Month = $this->convertToDateElement( Phpr_DateTime::elementMonth );
			$Day = $this->convertToDateElement( Phpr_DateTime::elementDay );

			$monthSum = $Month + $Months - 1;

			if ( $monthSum >= 0 ) {
				$Month = floor($monthSum % 12) + 1;
				$Year += floor($monthSum/12);
			} else {
				$Month = floor(12 + ($monthSum + 1) % 12);
				$Year += floor(($monthSum - 11) / 12);
			}

			$daysInMonth = Phpr_DateTime::daysInMonth( $Year, $Month );

			if ( $Day > $daysInMonth )
				$Day = $daysInMonth;

			$Result = new Phpr_DateTime();

			$incValue = $this->modulus($this->intValue, Phpr_DateTime::intInDay);

			$Result->setInteger( $this->convertDateVal($Year, $Month, $Day) + $incValue );

			return $Result;
		}

		/**
		 * Adds an interval to a current value and returns a new Phpr_DateTime object.
		 * @param Phrp_DateTimeInterval $Interval Specifies an interval to add.
		 * @return Phpr_DateTime
		 */
		public function addInterval( Phpr_DateTimeInterval $Interval )
		{
			$Result = new Phpr_DateTime( null, $this->timeZone );
			$Result->setInteger( $this->intValue + $Interval->getInteger() );

			return $Result;
		}

		/**
		 * Returns a new Phpr_DateTime object that is the sum of the date and time 
		 * represented by this object and a number of days specified.
		 * @param float $Value Specifies a number of days to add.
		 * @return Phpr_DateTime
		 */
		public function addDays( $Value )
		{
			return $this->addIntervalInternal( $Value, Phpr_DateTime::mlSecondsInDay );
		}

		/**
		 * Returns a new Phpr_DateTime object that is the sum of the date and time 
		 * represented by this object and a number of hours specified.
		 * @param float $Hours Specifies a number of hours to add.
		 * @return Phpr_DateTime
		 */
		public function addHours( $Hours )
		{
			return $this->addIntervalInternal( $Hours, Phpr_DateTime::mlSecondsInHour );
		}

		/**
		 * Returns a new Phpr_DateTime object corresponding the sum of this object date and time
		 * and a number of minutes specified.
		 * @param Float $Minutes Specifies a number of minutes to add.
		 * @return Phpr_DateTime
		 */
		public function addMinutes( $Minutes )
		{
			return $this->addIntervalInternal( $Minutes, Phpr_DateTime::mlSecondsInMinute );
		}

		/**
		 * Returns a new Phpr_DateTime object corresponding the sum of this object and a number of seconds specified.
		 * @param Float $Seconds Specifies a number of seconds to add.
		 * @return Phpr_DateTime
		 */
		public function addSeconds( $Seconds )
		{
			return $this->addIntervalInternal( $Seconds, Phpr_DateTime::mlSecondsInSecond );
		}

		/**
		 * Compares this object with another Phpr_DateTime object, 
		 * Returns 1 if this object value is more than a specified value,
		 * 0 if values are equal and 
		 * -1 if this object value is less than a specified value.
		 * This method takes into account the time zones of the date time objects.
		 * @param Phpr_DateTime $Value Specifies the Phpr_DateTime object to compare with.
		 * @return integer
		 */
		public function compare( Phpr_DateTime $Value )
		{
			if ( $this->intValue > $Value->getInteger() )
				return 1;

			if ( $this->intValue < $Value->getInteger() )
				return -1;

			return 0;
		}

		/**
		 * Compares two Phpr_DateTime values.
		 * Returns 1 if the first value is more than the second value,
		 * 0 if values are equal and 
		 * -1 if the first value is less than the second value.
		 * This method takes into account the time zones of the date time objects.
		 * @param DateTime $Value1 Specifies the first value
		 * @param DateTime $Value2 Specifies the second value
		 * @return integer
		 */
		public static function compareDates( Phpr_DateTime $Value1, Phpr_DateTime $Value2 )
		{
			if ( $Value1->getInteger() > $Value2->getInteger() )
				return 1;

			if ( $Value1->getInteger() < $Value2->getInteger() )
				return -1;

			return 0;
		}

		/**
		 * Determines whether a value of this object matches a value of a specified Phpr_DateTime object.
		 * This method takes into account the time zones of the date time objects.
		 * @param Phpr_DateTime $Value Specifies a value to compare with
		 * @return boolean
		 */
		public function equals( Phpr_DateTime $Value )
		{
			return $this->intValue == $Value->getInteger();
		}

		/**
		 * Returns the date component of a date and time value represented by the object.
		 * @return Phpr_DateTime
		 */
		public function getDate()
		{
			$Result = new Phpr_DateTime();
			$Result->setInteger( $this->intValue - $this->modulus($this->intValue, Phpr_DateTime::intInDay) );

			return $Result;
		}

		/**
		 * Returns the day of the week as a decimal number [1,7], with 1 representing Monday,
		 * for a date represented by this object.
		 * @return integer
		 */
		public function getDayOfWeek()
		{
			$result = (($this->intValue / Phpr_DateTime::intInDay) + 1) % 7;

			if ( $result == 0 )
				$result = 7;

			return $result;
		}

		/**
		 * Returns the day of the year for a date represented by this object, zero-based.
		 * @return integer
		 */
		public function getDayOfYear()
		{
			return $this->convertToDateElement( Phpr_DateTime::elementDayOfYear ) - 1;
		}

		/**
		 * Returns the number of days in the specified month of the specified year.
		 * @param integer $Year Specifies the year
		 * @param integer $Month Specifies the month
		 * @return integer
		 */
		public function daysInMonth( $Year, $Month )
		{
			if ( $Month < 1 || $Month > 12 )
				throw new Phpr_ApplicationException( "The Month argument is ouf range" );

			$DaysNum = $this->yearIsLeap($Year) ? $this->daysToMonthLeap : $this->daysToMonthReg;

			return $DaysNum[$Month] - $DaysNum[$Month - 1];
		}

		/**
		 * Determines whether the year is leap.
		 * @param integer $Year Specifies the year
		 * @return boolean
		 */
		public static function yearIsLeap( $Year )
		{
			if ( ($Year % 4) != 0)
				return false;

			if ( ($Year % 100) == 0)
				return ($Year % 400) == 0;

			return true;
		}

		/**
		 * Returns a Phpr_DateTime object representing the date/and time value in GMT.
		 * @return Phpr_DateTime
		 */
		public function gmt()
		{
			$Result = new Phpr_DateTime( null, $this->timeZone );
			$Result->setInteger( $this->intValue );
			$Result->setTimeZone( new DateTimeZone("GMT") );

			return $Result;
		}

		/**
		 * Returns the Phpr_DateTime object corresponding the current GMT date and time.
		 * @param DateTimeZone $TimeZone Optional, specifies the time zone to assign to the instance.
		 * @return Phpr_DateTime
		 */
		public static function gmtNow( DateTimeZone $TimeZone = null )
		{
			$Result = new Phpr_DateTime( null, $TimeZone );
			$Result->setInteger( time()*(Phpr_DateTime::intInSecond) + Phpr_DateTime::timestampOffset );

			return $Result;
		}

		/**
		 * Returns the instance of the Phpr_DateTime class representing the current local date and time.
		 * @return Phpr_DateTime
		 */
		public static function now()
		{
			return new Phpr_DateTime();
		}

		/**
		 * Substructs a specified Phpr_DateTime object from this object value 
		 * and returns the date and time interval.
		 * This method takes into account the time zones of the date time objects.
		 * @param Phpr_DateTime $Value Specifies the value to substract
		 * @return Phpr_DateTimeInterval
		 */
		public function substractDateTime( Phpr_DateTime $Value )
		{
			$Result = new Phpr_DateTimeInterval();
			$Result->setInteger( $this->intValue - $Value->getInteger() );

			return $Result;
		}

		/**
		 * Substructs a specified Phpr_DateTimeInterval object from this 
		 * object value and returns a new Phpr_DateTime instance.
		 * @param Phpr_DateTimeInterval $Value Specifies an interval to substract
		 * @return Phpr_DateTime
		 */
		public function substractInterval( Phpr_DateTimeInterval $Value )
		{
			$Result = new Phpr_DateTime();
			$Result->setInteger( $this->intValue - $Value->getInteger() );

			return $Result;
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Changes the internal date time value.
		 * @param integer $Value Specifies the integer value
		 */
		public function setInteger( $Value )
		{
			$this->intValue = $Value;
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Returns the integer representation of a date.
		 * @return integer
		 */
		public function getInteger()
		{
			return $this->intValue;
		}

		/**
		 * Returns the Phpr_DateTimeInterval object representing the interval elapsed since midnight.
		 * @return Phpr_DateTimeInterval
		 */
		public function getTimeInterval()
		{
			$Result = new Phpr_DateTimeInterval();
			$Result->setInteger( $this->modulus($this->intValue, Phpr_DateTime::intInDay) );

			return $Result;
		}

		/**
		 * Returns a string representation of the date and time, according the user language date/time format.
		 * @param string $Format Specifies the formatting string. For example: %F %X.
		 * @return string
		 */
		public function format( $Format )
		{
			return Phpr_DateTimeFormat::formatDateTime( $this, $Format );
		}

		/**
		 * Converts the Phpr_DateTime value to a string, according the full date format (%F format specifier).
		 * @return string
		 */
		public function toShortDateFormat()
		{
			return $this->format( '%x' );
		}

		/**
		 * Converts the Phpr_DateTime value to a string, according the full date format (%F format specifier).
		 * @return string
		 */
		public function toLongDateFormat()
		{
			return $this->format( '%F' );
		}

		/**
		 * Converts the Phpr_DateTime value to a string, according the time format (%X format specifier).
		 * @return string
		 */
		public function toTimeFormat()
		{
			return $this->format( '%X' );
		}

		/**
		 * Converts a string to a Phpr_DateTime object.
		 * If a specified string can not be converted to a date/time value, returns boolean false.
		 * @param string $Str Specifies the string to parse. For example: %x %X.
		 * @param string $Format Specifies the date/time format.
		 * @param DateTimeZone $TimeZone Optional. Specifies a time zone to assign to a new object.
		 * @return mixed
		 */
		public static function parse( $Str, $Format = null, DateTimeZone $TimeZone = null )
		{
			if ($Format == null)
				$Format = self::universalDateTimeFormat;
			
			return Phpr_DateTimeFormat::parseDateTime( $Str, $Format, $TimeZone );
		}

		/**
		 * @ignore
		 * This method is used by the PHP Road internally.
		 * Evaluates an offset between time zones of two specified time zones.
		 * @param DateTimeZone $Zone1 Specifies the first DateTimeZone instance.
		 * @param DateTimeZone $Zone2 Specifies the second DateTimeZone instance.
		 */
		public static function getZonesOffset( DateTimeZone $Zone1, DateTimeZone $Zone2 )
		{
			$temp = new DateTime();
			return $Zone1->getOffset($temp) - $Zone2->getOffset($temp);
		}

		/**
		 * Determines whether the string specified is a database null date representation
		 */
		public static function isDbNull( $Str )
		{
			if ( !strlen($Str) )
				return true;

			if ( substr($Str, 0, 10) == '0000-00-00' )
				return true;

			return false;
		}
		
		/**
		 * Returns object value in SQL date-time format
		 */
		public function toSqlDateTime()
		{
			return $this->format(self::universalDateTimeFormat);
		}
		
		/**
		 * Returns object value in SQL date format
		 */
		public function toSqlDate()
		{
			return $this->format(self::universalDateFormat);
		}
		
		/**
		 * Returns the integer value corresponding a current date and time.
		 * @return integer
		 */
		protected function getCurrentDateTime()
		{
			return ($this->timeZone->getOffset(new DateTime())+time())*(Phpr_DateTime::intInSecond) + Phpr_DateTime::timestampOffset;
		}

		/**
		 * Converts the value to a date element.
		 * @param integer $Element Specifies the element value
		 * @return integer
		 */
		protected function convertToDateElement( $Element )
		{
			$Days = floor($this->intValue/(Phpr_DateTime::intInDay));

			$Years400 = floor($Days/Phpr_DateTime::daysIn400Years);
			$Days -= $Years400 * Phpr_DateTime::daysIn400Years;

			$Years100 = floor($Days/Phpr_DateTime::daysIn100Years);
			if ( $Years100 == 4 )$Years100 = 3;
			$Days -= $Years100 * Phpr_DateTime::daysIn100Years;

			$Years4 = floor($Days/Phpr_DateTime::daysIn4Years);
			$Days -= $Years4 * Phpr_DateTime::daysIn4Years;

			$Years = floor($Days/365);

			if ( $Years == 4 ) $Years = 3;

			if ( $Element == Phpr_DateTime::elementYear )
				return $Years400*400 + $Years100*100 + $Years4*4 + $Years + 1;

			$Days -= $Years*365;

			if ( $Element == Phpr_DateTime::elementDayOfYear )
				return $Days + 1;

			$DaysNum = ($Years == 3 && ($Years4 != 24 || $Years100 == 3)) ? $this->daysToMonthLeap : $this->daysToMonthReg;

			$shifted = $Days >> 6;

			while ( $Days >= $DaysNum[$shifted] )
				$shifted++;

			if ( $Element == Phpr_DateTime::elementMonth )
				return $shifted;

			return $Days - $DaysNum[$shifted - 1] + 1;
		}

		/**
		 * Adds a scaled value to a current internal value and returns a new DateTime object.
		 * @param Double $Value Specifies a value to add.
		 * @param integer $ScaleFactor Specifies a scale factor.
		 * @return Phpr_DateTime
		 */
		protected function addIntervalInternal( $Value, $ScaleFactor )
		{
			$Value = $Value * $ScaleFactor;

			if ( $Value <= Phpr_DateTime::minMlSeconds || $Value >= Phpr_DateTime::maxMlSeconds )
				throw new Phpr_ApplicationException( "AddInervalInternal: argument is out of range" );

			$Result = new Phpr_DateTime( null, $this->timeZone );
			$Result->setInteger( $this->intValue + $Value*10000 );

			return $Result;
		}

		/**
		 * Computes the remainder after dividing the first parameter by the second.
		 * @param integer $a Specifies the first parameter
		 * @param integer $b Specifies the second parameter
		 * @return Float
		 */
		protected function modulus( $a, $b )
		{
			return $a-floor($a/$b)*$b;
		}

		/**
		 * Converts a date value to the internal representation.
		 * @param integer $Year Specifies the year
		 * @param integer $Month Specifies the month
		 * @param string $Day Specifies the day
		 * @return integer
		 */
		protected function convertDateVal( $Year, $Month, $Day )
		{
			if ( $Year < 1 || $Year > 9999 )
				throw new Phpr_ApplicationException( "Year is out of range" );

			if ( $Month < 1 || $Month > 12 )
				throw new Phpr_ApplicationException( "Month is out of range" );

			$dtm = !$this->yearIsLeap($Year) ? $this->daysToMonthReg : $this->daysToMonthLeap;

			$diff = $dtm[$Month] - $dtm[$Month-1];

			if ( $Day < 1 || $Day > $diff )
				throw new Phpr_ApplicationException( "Day is out of range" );

			$Year--;
			$days = floor($Year*365 + floor($Year/4) - floor($Year/100) + floor($Year/400)+ $dtm[$Month - 1] + $Day - 1);

			return $days*Phpr_DateTime::intInDay;
		}

		/**
		 * Converts a time value to internal format
		 * @param integer $Hour Specifies the hour
		 * @param integer $Minute Specifies the minute
		 * @param string $Second Specifies the second
		 * @return integer
		 */
		protected function convertTimeVal( $Hour, $Minute, $Second )
		{
			if ( $Hour < 0 || $Hour >= 24 )
				throw new Phpr_ApplicationException( "Hour is out of range" );

			if ( $Minute < 0 || $Minute >= 60 )
				throw new Phpr_ApplicationException( "Minute is out of range" );

			if ( $Minute < 0 || $Minute >= 60 )
				throw new Phpr_ApplicationException( "Second is out of range" );

			return Phpr_DateTimeInterval::convertTimeVal( $Hour, $Minute, $Second );
		}
	}

?>