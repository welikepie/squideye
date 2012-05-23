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
	 * PHP Road HTML helper
	 *
	 * This class contains functions that may be useful for working with HTML.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Html
	{
		/**
		 * Converts all applicable characters to HTML entities. For example: "<-" becomes "&lt;-"
		 * @param string $Str Specifies the string to encode.
		 * @return string
		 */
		public static function encode( $Str )
		{
			return htmlentities( $Str, ENT_COMPAT, 'UTF-8' );
		}

		/**
		 * Converts all HTML entities to their applicable characters . For example: "&lt;-" becomes "<-"
		 * @param string $Str Specifies the string to decode.
		 * @return string
		 */
		public static function decode( $Str )
		{
			return strtr( $Str, array_flip(get_html_translation_table(HTML_ENTITIES, ENT_QUOTES)) );
		}

		/**
		 * Formats a list of attributes to use in the opening tag.
		 * This function is mostly used by other helpers.
		 * @param array $Attrubutes Specifies a list of attributes.
		 * @param array $Defaults Specifies a list of default attribute values.
		 * If the attribute is omitted in the Attributes list the default value will be used.
		 * @return string
		 */
		public static function formatAttributes( $Attributes, $Defaults = array() )
		{
			foreach ( $Defaults as $AttrName=>$AttrValue )
				if ( !array_key_exists($AttrName, $Attributes) )
					$Attributes[$AttrName] = $Defaults[$AttrName];

			$result = array();
			foreach ( $Attributes as $AttrName=>$AttrValue )
			{
				if (strlen($AttrValue))
					$result[] = $AttrName."=\"".$AttrValue."\"";
			}

			return implode(" ", $result);
		}

		/**
		 * Truncates HTML string
		 * Thanks to www.phpinsider.com/smarty-forum/viewtopic.php?t=533
		 * @param string $String Specifies a string to truncate
		 * @param integer $Length Specifies a string length
		 * @return string
		 */
		public static function trunc( $String, $Length )
		{
			if( !empty($String) && $Length>0 )
			{
				$isText = true;
				$ret = "";
				$i = 0;

				$currentChar = "";
				$lastSpacePosition = -1;
				$lastChar = "";

				$tagsArray = array();
				$currentTag = "";
				$tagLevel = 0;

				$noTagLength = strlen( strip_tags( $String ) );

				for( $j=0; $j<strlen( $String ); $j++ )
				{
					$currentChar = substr( $String, $j, 1 );
					$ret .= $currentChar;

					if ($currentChar == "<") 
						$isText = false;

					if( $isText )
					{
						if( $currentChar == " " )
							$lastSpacePosition = $j;
						else 
							$lastChar = $currentChar;

					$i++;
					} else
						$currentTag .= $currentChar;

					if( $currentChar == ">" )
					{
						$isText = true;

						if ( ( strpos( $currentTag, "<" ) !== FALSE ) &&
							( strpos( $currentTag, "/>" ) === FALSE ) &&
							( strpos( $currentTag, "</") === FALSE ) )
						{
							if( strpos( $currentTag, " " ) !== FALSE )
								$currentTag = substr( $currentTag, 1, strpos( $currentTag, " " ) - 1 );
							else
								$currentTag = substr( $currentTag, 1, -1 );

							array_push( $tagsArray, $currentTag );
						}
						else
							if( strpos( $currentTag, "</" ) !== FALSE )
								array_pop( $tagsArray );

						$currentTag = "";
					}

					if( $i >= $Length)
						break;
				}

				if ($Length < $noTagLength)
				{
					if( $lastSpacePosition != -1 )
						$ret = substr( $String, 0, $lastSpacePosition ).'...';
					else
						$ret = substr( $String, $j ).'...';
				}

				while( sizeof( $tagsArray ) != 0 )
				{
					$aTag = array_pop( $tagsArray );
					$ret .= "</" . $aTag . ">\n";
				}

			} else
				$ret = "";

			return $ret;
		}

		/**
		 * Strips HTML tags and converts HTML entities to characters
		 * @param string $Str A string to process
		 * @param int $Len Optional length to trim the string
		 * @return string
		 */
		public static function plainText($Str, $Len = null)
		{
			$Str = strip_tags($Str);
			
			if ($Len !== null)
				$Str = self::strTrim($Str, $Len);
			
			return htmlspecialchars_decode($Str);
		}
		
		/**
		 * Replaces new line characters with HTML paragraphs and line breaks
		 * @param string $text Specifies a text to process
		 * @return string
		 */
		public static function paragraphize( $text )
		{
			$text = preg_replace( '/\r\n/m', "[-LB-]", $text );
			$text = preg_replace( '/\n/m', "[-LB-]", $text );
			$text = preg_replace( '/\r/m', "[-LB-]", $text );
			$text = str_replace( "[-LB-][-LB-][-LB-]", "[-LB-][-LB-]", $text );

			$text = preg_replace( '/\s+/m', " ", $text );

			$text = preg_replace( '/\[-LB-\]\[-LB-\]/m', "</p><p>", $text );
			
			$text = preg_replace( '/\[-LB-\]/m', "<br/>\r\n", $text );
			$text = "<p>".$text."</p>";
			
			$text = str_replace( "<p></p>", "", $text );
			$text = preg_replace( ",\<p\>\s*\<br/\>\s*\</p\>,m", "", $text );
			$text = preg_replace( ",\<p\>\s*\<br/\>,m", "<p>", $text );
			$text = str_replace( "<br/>\r\n</p>", "</p>", $text );
			$text = str_replace( "\r\n\r\n", "", $text );
			$text = str_replace( "</p><p>", "</p>\r\n\r\n<p>", $text );
		
			return $text;
		}

		/**
		 * Replaces HTML paragraphs and line breaks with new line characters
		 * @param string $text Specifies a text to process
		 * @return string
		 */
		public static function deparagraphize( $text )
		{
			$result = str_replace( '<p>', '', $text );
			$result = str_replace( '</p>', '', $result );
			$result = str_replace( '<br/>', '', $result );
			$result = str_replace( '<br>', '', $result );
			
			return $result;
		}

		/**
		 * Truncates a string
		 * @param string $Str A string to process
		 * @param int $Len Length to trim the string
		 * @param bool $Right Truncate the string from the beginning
		 * @return string
		 */
		public static function strTrim($Str, $Len, $Right = false)
		{
			$StrLen = mb_strlen($Str);
			if ($StrLen > $Len)
			{
				if (!$Right)
					return mb_substr($Str, 0, $Len-3).'...';
				else
					return '...'.mb_substr($Str, $StrLen-$Len+3, $Len-3);
			}
				
			return $Str;
		}

		/**
		 * Truncates a string by removing characters from the middle of the string
		 * @param string $Str A string to process
		 * @param int $Len Length to trim the string
		 * @return string
		 */
		public static function strTrimMiddle($Str, $Len)
		{
			$StrLen = mb_strlen($Str);
			if ($StrLen > $Len)
			{
				if ($Len > 3)
					$Len = $Len - 3;

				$CharsStart = floor($Len/2);
				$CharsEnd = $Len - $CharsStart;

				return trim(mb_substr($Str, 0, $CharsStart)).'...'.trim(mb_substr($Str, -1*$CharsEnd));
			}
				
			return $Str;
		}

		/**
		 * Removes all line breaks and repeating spaces from a string
		 * @param string $str Specifies a string to process
		 * @return string
		 */
		public static function removeRedundantSpaces($str)
		{
			$str = str_replace("\r\n", ' ', $str);
			$str = str_replace("\n", ' ', $str);
			$str = str_replace("\t", ' ', $str);
			
			$cnt = 1;
			while ($cnt)
				$str = str_replace('  ', ' ', $str, $cnt);

			return $str;
		}
		
		/**
		 * Adds the <span class="highlight"></span> elements around specific words. The string should not contain
		 * any HTML characters. The function trims all new line characters.
		 * @param string $str Specifies a string to process
		 * @param array $words Array of words to highlight
		 * @param int $trim_length Allows to leave only a part of the source string, surrounding the 
		 * first occurrence of specified words. The parameter specifies how many symbols to leave before and after
		 * the first occurrence. 
		 * @param int &$cnt Returns a number of words highlighted
		 * @return string
		 */
		public static function highlightWords($str, $words, $trim_length, &$cnt)
		{
			$cnt = 0;

			if (!$words)
				return $str;
			
			$str = self::removeRedundantSpaces($str);

			/*
			 * Cut the string
			 */

			$upper_str = mb_strtoupper($str);
			
			if ($trim_length)
			{
				foreach ($words as $word)
				{
					$pos = mb_strpos($upper_str, mb_strtoupper($word));
					if ($pos !== false)
					{
						$orig_length = mb_strlen($str);
						$trim_start = max($pos-$trim_length, 0);
						$trim_end = $pos+mb_strlen($word)+$trim_length;

						$str = mb_substr($str, $trim_start, $pos-$trim_start+mb_strlen($word)+$trim_length);

						if ($trim_start > 0)
							$str = '...'.$str;

						if ($orig_length > $trim_end)
							$str .= '...';

						break;
					}
				}
			}

			/*
			 * Highlight all occurrences
			 */

			$str = h($str);
			$upper_str = mb_strtoupper($str);

			foreach ($words as $word)
			{
				$pos = mb_strpos($upper_str, mb_strtoupper($word));
				if ($pos !== false)
				{
					$cnt++;
					$str = mb_substr($str, 0, $pos).
						'<span class="highlight">'.
						mb_substr($str, $pos, mb_strlen($word)).
						'</span>'.
						mb_substr($str, $pos+mb_strlen($word));

					$upper_str = mb_strtoupper($str);
				}
			}
			
			return $str;
		}
		
		/**
		 * Returns a CSS class string determining a current browser.
		 *
		 * PHP CSS Browser Selector v0.0.1
		 * Bastian Allgeier (http://bastian-allgeier.de)
		 * http://bastian-allgeier.de/css_browser_selector
		 * License: http://creativecommons.org/licenses/by/2.5/
		 * Credits: This is a php port from Rafael Lima's original Javascript CSS Browser Selector: http://rafael.adm.br/css_browser_selector
		 */
		public static function css_browser_selector($ua=null) 
		{
			if ($ua)
				$ua = strtolower($ua);
			else {
				if (array_key_exists('HTTP_USER_AGENT', $_SERVER))
					$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
			}

			$g = 'gecko';
			$w = 'webkit';
			$s = 'safari';
			$b = array();

			// browser
			if(!preg_match('/opera|webtv/i', $ua) && preg_match('/msie\s(\d)/', $ua, $array)) {
					$b[] = 'ie ie' . $array[1];
			}	else if(strstr($ua, 'firefox/2')) {
					$b[] = $g . ' ff2';		
			}	else if(strstr($ua, 'firefox/3.5')) {
					$b[] = $g . ' ff3 ff3_5';
			}	else if(strstr($ua, 'firefox/3')) {
					$b[] = $g . ' ff3';
			} else if(strstr($ua, 'gecko/')) {
					$b[] = $g;
			} else if(preg_match('/opera(\s|\/)(\d+)/', $ua, $array)) {
					$b[] = 'opera opera' . $array[2];
			} else if(strstr($ua, 'konqueror')) {
					$b[] = 'konqueror';
			} else if(strstr($ua, 'chrome')) {
					$b[] = $w . ' ' . $s . ' chrome';
			} else if(strstr($ua, 'iron')) {
					$b[] = $w . ' ' . $s . ' iron';
			} else if(strstr($ua, 'applewebkit/')) {
					$b[] = (preg_match('/version\/(\d+)/i', $ua, $array)) ? $w . ' ' . $s . ' ' . $s . $array[1] : $w . ' ' . $s;
			} else if(strstr($ua, 'mozilla/')) {
					$b[] = $g;
			}

			// platform				
			if(strstr($ua, 'j2me')) {
					$b[] = 'mobile';
			} else if(strstr($ua, 'iphone')) {
					$b[] = 'iphone';		
			} else if(strstr($ua, 'ipod')) {
					$b[] = 'ipod';		
			} else if(strstr($ua, 'mac')) {
					$b[] = 'mac';		
			} else if(strstr($ua, 'darwin')) {
					$b[] = 'mac';		
			} else if(strstr($ua, 'webtv')) {
					$b[] = 'webtv';		
			} else if(strstr($ua, 'win')) {
					$b[] = 'win';		
			} else if(strstr($ua, 'freebsd')) {
					$b[] = 'freebsd';		
			} else if(strstr($ua, 'x11') || strstr($ua, 'linux')) {
					$b[] = 'linux';		
			}

			return join(' ', $b);
		}
	}

?>