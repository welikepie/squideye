<?
	
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
	 * @ignore
	 * This class is used by the PHP Road internally
	 *
	 * PHP Road DateTimeFormat Class
	 *
	 * Phpr_DateTimeFormat provides methods for converting date/time values to strings vice versa.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_DateTimeFormat
	{
		const spType = 'spt';
		const spTypeString = 'string';
		const spTypeInt = 'integer';
		const spTypeComplex = 'complex';
		const spTypeLocLink = 'llink';
		const spComplexValue = 'value';
		const spLocLinkKey = 'llkey';
		const spIntMin = 'min';
		const spIntMax = 'max';
		const spDomain = 'domain';
		const spValueNum = 'valuenum';
		const spValueList = 'valuelist';
		const spMethod = 'method';
		const spCustomMethod = 'custom';
		const spIntPadding = 'padding';

		const spParserMeaning = 'pm';
		const spPrMnYear = 'year';
		const spPrMnMonth = 'month';
		const spPrMnDay = 'day';
		const spPrMnHour = 'hour';
		const spPrMnMinute = 'minute';
		const spPrMnSecond = 'second';
		const spPrMnCustom = 'custom';

		const formatPattern = "/(?P<specifier>%.)/";
		const parserPattern = "/(?P<datepart>[\w]+)/";

		const localizationPrefix = 'dates';

		private static $language = null;
		private static $parsedFormats = array();
		private static $unwrappedFormats = array();

		private static $formatSpecifiers = array
			(
				'a' => array( self::spType=>self::spTypeString, self::spDomain=>'a_weekday_', self::spMethod=>'GetDayOfWeek', self::spValueNum=>7 ),
				'A' => array( self::spType=>self::spTypeString, self::spDomain=>'A_weekday_', self::spMethod=>'GetDayOfWeek', self::spValueNum=>7 ),
				'b' => array( self::spType=>self::spTypeString, self::spDomain=>'b_month_', self::spMethod=>'GetMonth' ),
				'B' => array( self::spType=>self::spTypeString, self::spDomain=>'B_month_', self::spMethod=>'GetMonth' ),
				'c' => array( self::spType=>self::spTypeComplex, self::spComplexValue=>'%x %X' ),
				'C' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>99, self::spMethod=>self::spCustomMethod, self::spIntPadding=>2 ),
				'd' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>31, self::spMethod=>'GetDay', self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnDay ),
				'D' => array( self::spType=>self::spTypeComplex, self::spComplexValue=>'%m/%d/%y' ),
				'e' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>31, self::spMethod=>'GetDay', self::spParserMeaning=>self::spPrMnDay ),
				'F' => array( self::spType=>self::spTypeLocLink, self::spLocLinkKey=>'full_date_format' ),
				'H' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>23, self::spMethod=>'GetHour', self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnHour ),
				'I' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>12, self::spMethod=>self::spCustomMethod, self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnHour ),
				'j' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>366, self::spMethod=>'GetDayOfYear', self::spIntPadding=>3 ),
				'm' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>12, self::spMethod=>'GetMonth', self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnMonth ),
				'M' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>59, self::spMethod=>'GetMinute', self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnMinute ),
				'n' => array( self::spType=>self::spTypeString, self::spDomain=>'n_month_', self::spMethod=>'GetMonth' ),
				'p' => array( self::spType=>self::spTypeString, self::spDomain=>'ampm_', self::spMethod=>self::spCustomMethod, self::spParserMeaning=>self::spPrMnCustom, self::spValueList=>array('ampm_am', 'ampm_pm') ),
				'S' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>59, self::spMethod=>'GetSecond', self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnSecond ),
				'T' => array( self::spType=>self::spTypeComplex, self::spComplexValue=>'%H:%M:%S' ),
				'u' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>7, self::spMethod=>'GetDayOfWeek' ),
				'w' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>6, self::spMethod=>self::spCustomMethod ),
				'x' => array( self::spType=>self::spTypeLocLink, self::spLocLinkKey=>'short_date_format' ),
				'X' => array( self::spType=>self::spTypeLocLink, self::spLocLinkKey=>'time_format' ),
				'y' => array( self::spType=>self::spTypeInt, self::spIntMin=>0, self::spIntMax=>99, self::spMethod=>self::spCustomMethod, self::spIntPadding=>2, self::spParserMeaning=>self::spPrMnCustom ),
				'Y' => array( self::spType=>self::spTypeInt, self::spIntMin=>1, self::spIntMax=>9999, self::spMethod=>'GetYear', self::spParserMeaning=>self::spPrMnYear )
			);

		/**
		 * Initializes the object
		 */
		private static function init()
		{
			$Lang = Phpr::$lang->getlanguage();
			if ( $Lang != self::$language )
			{
				self::$language = $Lang;
				self::$parsedFormats = array();
				self::$unwrappedFormats = array();
			}
		}

		/**
		 * Unwraps the complex specifiers and returns the modified format string.
		 * @param string $Format Specifies the format string to process
		 * @param integer $cnt Specifies the number of complex specifiers found
		 * @return string
		 */
		private static function unwrapFormat( $Format, &$cnt )
		{
			$cnt = 0;

			if ( array_key_exists( $Format, self::$unwrappedFormats ) )
				return self::$unwrappedFormats[$Format];

			$FormatWrapped = $Format;

			self::init();

			// Parse format and unwrap complex specifiers
			//
			preg_match_all( self::formatPattern, $Format, $Matches );

			foreach ( $Matches['specifier'] as $MatchData ) {
				$MatchSpecifier = substr( $MatchData, 1 );

				if ( array_key_exists($MatchSpecifier, self::$formatSpecifiers) ) {
					$specifierDesc = self::$formatSpecifiers[$MatchSpecifier];

					$specifierValue = null;

					if ( $specifierDesc[self::spType] == self::spTypeLocLink ) {

						// Load specifier value from the localization resources
						//
						$specifierValue = Phpr::$lang->mod( 'phpr', $specifierDesc[self::spLocLinkKey], self::localizationPrefix );

						$cnt++;
					} else
						if ( $specifierDesc[self::spType] == self::spTypeComplex ) {
							$specifierValue = $specifierDesc[self::spComplexValue];
							$cnt++;
						}

					if ( !is_null($specifierValue) )
						$Format = str_replace( '%'.$MatchSpecifier, $specifierValue, $Format );
				}
			}

			self::$unwrappedFormats[$FormatWrapped] = $Format;

			return $Format;
		}

		/**
		 * Parses the date format string and returns the array of format specifiers.
		 * @param string $Format Specifies the format string to parse.
		 * @return array
		 */
		private static function parseFormat( &$Format )
		{
			// Preprocess the format
			//
			$count = null;

			do
				$Format = self::unwrapFormat( $Format, $count );
			while ( $count > 0 );

			// Check if parsed format is not cached
			//
			if ( array_key_exists( $Format, self::$parsedFormats ) )
				return self::$parsedFormats[$Format];

			// Parse the format
			//
			preg_match_all( self::formatPattern, $Format, $Matches );

			self::$parsedFormats[$Format] = $Matches['specifier'];

			return $Matches['specifier'];
		}

		/**
		 * Converts the specified Phpr_DateTime object value to string according the specified format.
		 * @param DateTime $DateTime Specifies the value to format
		 * @param string $Format Specifies the format string
		 * @return string
		 */
		public static function formatDateTime( Phpr_DateTime $DateTime, $Format )
		{
			self::init();

			$FormatSpecifiers = self::parseFormat( $Format );

			// Replace specifiers with date values
			//
			$processedSpecifiers = array();

			$methodValues = array();

			foreach ( $FormatSpecifiers as $MatchData ) {
				$MatchSpecifier = substr( $MatchData, 1 );

				// Skip unknown specifiers
				//
				if ( !array_key_exists($MatchSpecifier, self::$formatSpecifiers) )
					continue;

				// Obtain the specifier description
				//
				$specifierDesc = self::$formatSpecifiers[$MatchSpecifier];

				// Evaluate the specifier value in case if it was not evaluated so far
				//
				if ( !array_key_exists( $MatchSpecifier, $processedSpecifiers ) ) {
					$specifierValue = null;
					$method = $specifierDesc[self::spMethod];

					if ( $method != self::spCustomMethod ) {
						// Evaluate auto method values
						//
						if ( array_key_exists( $method, $methodValues ) )
							$methodValue = $methodValues[$method];
						else {
							$methodValue = $DateTime->$method();
							$methodValues[$method] = $methodValue;
						}
					} else {
						// Evaluate custom method values
						//
						switch ( $MatchSpecifier ) {
							case 'p' :
									$hours = $DateTime->getHour();
									$methodValue = ($hours < 12) ? 'am' : 'pm';

									break;
							case 'C' :
									$methodValue = floor($DateTime->getYear() / 100);

									break;
							case 'I' :
									$hours = $DateTime->getHour();
									$methodValue = ( $hours <= 12 ) ? $hours : $hours % 12;

									break;
							case 'w' :
									$weekDay = $DateTime->getDayOfWeek();
									if ( $weekDay == 7 )
										$weekDay = 0;
									$methodValue = $weekDay;

									break;
							case 'y' :
									$methodValue = $DateTime->getYear() % 100;

									break;
						}
					}

					if ( $specifierDesc[self::spType] == self::spTypeString ) {
						// Load the localization string for the string specifiers
						//
						$specifierValue = Phpr::$lang->mod( 'phpr', $specifierDesc[self::spDomain].$methodValue, self::localizationPrefix );
					} elseif ( $specifierDesc[self::spType] == self::spTypeInt ) {
						if ( array_key_exists( self::spIntPadding, $specifierDesc ) ) {
							$padding = $specifierDesc[self::spIntPadding];
							$methodValue = sprintf( "%0{$padding}d", $methodValue );
						}

						$specifierValue = $methodValue;
					}

					$processedSpecifiers[$MatchSpecifier] = $specifierValue ;
				} else
					$specifierValue = $processedSpecifiers[$MatchSpecifier];

				if ( !is_null($specifierValue) )
					$Format = str_replace( '%'.$MatchSpecifier, $specifierValue, $Format );
			}

			// Replace the %% sequence with the % character
			//
			$Format = str_replace( '%%', '%', $Format );

			return $Format;
		}

		/**
		 * Returns the array of the specified domain values.
		 * @param string $Domain Specifies the domain name
		 * @param integer $Num Specifies the number of values to load
		 * @return array
		 */
		private static function preloadDomainValues( $Domain, $Num )
		{
			$result = array();

			for ( $index = 1; $index <= $Num; $index++ )
				$result[$index] = Phpr::$lang->mod( 'phpr', $Domain.$index, self::localizationPrefix);

			return $result;
		}

		/**
		 * Returns the array of the specified domain values.
		 * @param array $ValueList Specifies the list of the domain values
		 * @return array
		 */
		private static function preloadDomainValueList( $ValueList )
		{
			$result = array();

			foreach ( $ValueList as $ValueName )
				$result[] = Phpr::$lang->mod( 'phpr', $ValueName, self::localizationPrefix);

			return $result;
		}

		/**
		 * Parses the string and returns the Phpr_DateTime value.
		 * If a specified string can not be converted to a date/time value, returns boolean false.
		 * @param string $Str Specifies the string to parse
		 * @param string $Format Specieis the date format expected
		 * @param DateTimeZone $TimeZone Optional. Specifies a time zone to assign to a new object.
		 * @return Phpr_DateTime
		 */
		public static function parseDateTime( $Str, $Format, $TimeZone = null )
		{
			self::init();

			$FormatSpecifiers = self::parseFormat( $Format );

			if ( !count($FormatSpecifiers) )
				return false;

			// Split string
			//
			$matches = array();
			preg_match_all( self::parserPattern, $Str, $matches );
			$stringMatches = $matches['datepart'];

			if ( !count($stringMatches) )
				return false;

			// Process format specifiers
			//
			$dateElements = array(
				self::spPrMnYear => null,
				self::spPrMnMonth => null,
				self::spPrMnDay => null,
				self::spPrMnHour => null,
				self::spPrMnMinute => null,
				self::spPrMnSecond => null
			);

			$Now = Phpr_DateTime::now();

			$ampm = null;

			foreach ( $FormatSpecifiers as $index=>$specifier ) {
				$specifier = substr( $specifier, 1 );

				// Skip unknown specifiers
				//
				if ( !array_key_exists($specifier, self::$formatSpecifiers) )
					continue;

				// Obtain the specifier description
				//
				$specifierDesc = self::$formatSpecifiers[$specifier];

				// Skip non-parserable specifiers
				//
				if ( !array_key_exists(self::spParserMeaning, $specifierDesc) )
					continue;

				// Return false if no value was provided for the specifier
				//
				if ( !array_key_exists($index, $stringMatches) )
					return false;

				$value = $stringMatches[$index];

				// Preprocess the specifier value
				//
				$type = $specifierDesc[self::spType];

				$domainValuesCache = array();

				if ( $type == self::spTypeInt ) {
					if ( !preg_match("/^[0-9]+$/", $value) )
						return false;

					$value = (int)$value;

					if ( array_key_exists(self::spIntMin, $specifierDesc) )
						if ( $value < $specifierDesc[self::spIntMin] || $value > $specifierDesc[self::spIntMax] )
							return false;
				} elseif ( $type == self::spTypeString ) {
					$domain = $specifierDesc[self::spDomain];

					if ( !array_key_exists($domain, $domainValuesCache) ) {
						if ( array_key_exists(self::spValueNum, $specifierDesc) )
							$domainValues = self::preloadDomainValues( $domain, $specifierDesc[self::spValueNum] );
						else
							$domainValues = self::preloadDomainValueList( $specifierDesc[self::spValueList] );

						$domainValuesCache[$domain] = $domainValues;
					} else 
						$domainValues = $domainValuesCache[$domain];

					$strIndex = null;
					foreach( $domainValues as $index=>$domainValue )

						if ( strcasecmp($domainValue, $value) == 0 ) {
							$strIndex = $index;
							break;
						}

					if ( is_null($strIndex) )
						return false;

					$value = $index;
				}

				// Assign value to a corresponding date element
				//
				$meaning = $specifierDesc[self::spParserMeaning];

				if ( $meaning != self::spPrMnCustom )
					$dateElements[$meaning] = $value;
				else {
					switch ( $specifier ) {
						case 'p' :
								$ampm = $value;

								break;
						case 'y' :
								$century = floor($Now->getYear()/100);
								$dateElements[self::spPrMnYear] = $century*100 + $value;

								break;
					}
				}
			}

			// Assemble result value
			//
			$year = is_null($dateElements[self::spPrMnYear]) ? $Now->getYear() : $dateElements[self::spPrMnYear];
			$month = is_null($dateElements[self::spPrMnMonth]) ? $Now->getMonth() : $dateElements[self::spPrMnMonth];
			$day = is_null($dateElements[self::spPrMnDay]) ? $Now->getDay() : $dateElements[self::spPrMnDay];
			$hour = is_null($dateElements[self::spPrMnHour]) ? 0 : $dateElements[self::spPrMnHour];
			$minute = is_null($dateElements[self::spPrMnMinute]) ? 0 : $dateElements[self::spPrMnMinute];
			$second = is_null($dateElements[self::spPrMnSecond]) ? 0 : $dateElements[self::spPrMnSecond];

			if ( !is_null($ampm) )
				if ( $ampm == 1 ) {
					if ( $hour < 12 )
						$hour += 12;
				} elseif ( $ampm == 0 )
					if ( $hour >= 12 )
						$hour -= 12;

			$Result = new Phpr_DateTime( null, $TimeZone );
			$Result->setDateTime( $year, $month, $day, $hour, $minute, $second );

			return $Result;
		}

		/**
		 * Returns the short week day name (corresponds the %A specifier).
		 * @param integer $DayNumber Specifies the day number, one of the DayOfWeek enumeration field values
		 * @return string
		 */
		public static function getShortWeekDayName( $DayNumber )
		{
			self::init();

			return Phpr::$lang->mod( 'phpr', 'a_weekday_'.$DayNumber, self::localizationPrefix );
		}

		/**
		 * Returns the full week day name (corresponds the %A specifier).
		 * @param integer $DayNumber Specifies the day number, one of the DayOfWeek enumeration field values
		 * @return string
		 */
		public static function getFullWeekDayName( $DayNumber )
		{
			self::init();

			return Phpr::$lang->mod( 'phpr', 'A_weekday_'.$DayNumber, self::localizationPrefix );
		}
	}
?>