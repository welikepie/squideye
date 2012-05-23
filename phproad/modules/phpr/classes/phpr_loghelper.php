<?php

	/**
	 * @ignore
	 *
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
	 *
	 * PHP Road Text file log helper class.
	 *
	 * This class allows the error and text logging classes to write messages to the text files.
	 * 
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_LogHelper
	{
		/**
		 * Writes a line to the text file.
		 * @param string FilePath Specifies a path to the log file.
		 * @param string Messages Specifies a message to write.
		 * @return boolean Returns true if the message was logged successfully.
		 */
		public static function writeLine( $FilePath, $Message )
		{
			$Message = sprintf( "[%s] %s\n", date("Y-m-d H:i:s"), $Message );

			if ( !($fp = @fopen( $FilePath, 'a' )) )
				return false;

			flock( $fp, LOCK_EX );

			if ( !@fwrite( $fp, $Message ) )
			{
				fclose( $fp );
				return false;
			}

			flock( $fp, LOCK_UN );
			fclose( $fp );

			try
			{
//				@chmod( $FilePath, 0644 );
			}
			catch (Exception $e)
			{
			}

			return true;
		}
	}

?>