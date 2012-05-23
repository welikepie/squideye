<?

	/**
	 * PHP Road Error Log Class
	 *
	 * Error log allows the application to write the error messages to a file.
	 *
	 * To enable the error logging set the ERROR_LOG value to true in the 
	 * application configuration file: $CONFIG['ERROR_LOG'] = true;
	 *
	 * By default the error log file (errors.txt) is situated in the application logs directory (logs/errors.txt).
	 * You may specify another location and the file name by setting the ERROR_LOG_FILE configuration parameter:
	 * $CONFIG['ERROR_LOG_FILE'] = "/home/logs/blogerrors.txt". In both cases the log file must be writable.
	 *
	 * PHP Road exceptions (the exceptions inherited from the Phpr_Exception class)
	 * logs themselves automatically. You may log the custom exception using the LogException() method.
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$error_log.
	 */
	class Phpr_ErrorLog
	{
		private $_logFileName;
		private $_isEnabled;
		private $_ignoreExceptions;
		
		public static $disable_db_logging = false;

		/**
		 * Creates a new Phpr_ErrorLog instance.
		 */
		public function __construct()
		{
			$this->loadConfiguration();
		}
		
		public static function encode_error_details($value) {
			$security = Phpr_SecurityFramework::create();
			list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
			
			return base64_encode($security->encrypt(json_encode($value), $key_1, $key_2));
		}
		
		public static function decode_error_details($value) {
			$security = Phpr_SecurityFramework::create();
			list($key_1, $key_2) = Phpr::$config->get('ADDITIONAL_ENCRYPTION_KEYS', array('jd$5ka#1', '9ao@!d4k'));
		
			return json_decode($security->decrypt(base64_decode($value), $key_1, $key_2));
		}
		
		public static function get_exception_details($exception) {
			$error = (object) array(
				'log_id' => property_exists($exception, 'log_id') ? $exception->log_id : null,
				'log_status' => property_exists($exception, 'log_status') ? $exception->log_status : null,
				'message' => Core_String::ucfirst(nl2br(Phpr_Html::Encode($exception->getMessage()))),
				'hint' => isset($exception->hint_message) && strlen($exception->hint_message) ? $exception->hint_message : null,
				'is_document' => $exception instanceof Cms_ExecutionException,
				'document' => $exception instanceof Cms_ExecutionException ? $exception->document_name() : Phpr_Files::rootRelative($exception->getFile()),
				'document_type' => $exception instanceof Cms_ExecutionException ? $exception->document_type() : 'PHP document',
				'line' => $exception instanceof Cms_ExecutionException ? $exception->code_line : $exception->getLine(),
				'class_name' => get_class($exception),
				'code_highlight' => (object) array(
				'brush' => $exception instanceof Cms_ExecutionException ? 'php' : 'php',
				'lines' => array()
			  ),
			  'call_stack' => array()
			);

			// code highlight
			$code_lines = null;
			
			if ($exception instanceof Cms_ExecutionException)
			{
				$code_lines = explode("\n", $exception->document_code());

				foreach ($code_lines as $i => $line)
					$code_lines[$i] .= "\n";

				$error_line = $exception->code_line-1;
			} else
			{
				$file = $exception->getFile();
				if (file_exists($file) && is_readable($file))
				{
					$code_lines = @file($file);
					$error_line = $exception->getLine()-1;
				}
			}
			
			if ($code_lines)
			{
				$start_line = $error_line-6;
				if ($start_line < 0)
					$start_line = 0;
					
				$end_line = $start_line + 12;
				$line_num = count($code_lines);
				if ($end_line > $line_num-1)
					$end_line = $line_num-1;

				$code_lines = array_slice($code_lines, $start_line, $end_line-$start_line+1);
				
				$error->code_highlight->start_line = $start_line;
				$error->code_highlight->end_line = $end_line;
				$error->code_highlight->error_line = $error_line;
				
				foreach($code_lines as $i => $line) {
					$error->code_highlight->lines[$start_line+$i] = $line;
				}
			}
			
			// stack trace
			if($error->is_document) {
				$last_index = count($exception->call_stack) - 1;
				
				foreach($exception->call_stack as $index=>$stack_item) {
					$error->call_stack[] = (object) array(
			      'id' => $last_index-$index+1,
			      'document' => h($stack_item->name),
			      'type' => h($stack_item->type)
			    );
				}
			}	
			else {
				$trace_info = $exception->getTrace();
				$last_index = count($trace_info) - 1;
				
				foreach($trace_info as $index => $event) {
					$functionName = (isset($event['class']) && strlen($event['class'])) ? $event['class'].$event['type'].$event['function'] : $event['function'];

					if($functionName == 'Phpr_SysErrorHandler' || $functionName == 'Phpr_SysExceptionHandler')
						continue;
					
					$file = isset($event['file']) ? Phpr_Files::rootRelative($event['file']) : null;
					$line = isset($event['line']) ? $event['line'] : null;

					$args = null;
					if ( isset($event['args']) && count($event['args']) )
						$args = Phpr_Exception::_formatTraceArguments($event['args'], false);
					
					$error->call_stack[] = (object) array(
						'id' => $last_index-$index+1,
						'function_name' => $functionName,
						'args' => $args ? $args : '',
						'document' => $file,
						'line' => $line
					);
				}	
			}
			
			return $error;
		}

		/**
		 * Writes an exception information to the log file.
		 * @param Exception $exception Specifies the exception to log.
		 * @return boolean Returns true if exception was logged successfully.
		 */
		public function logException( Exception $exception )
		{
			if ( !$this->_isEnabled )
				return false;

			foreach ( $this->_ignoreExceptions as $IgnoredExceptionClass )
			{
				if ( $exception instanceof $IgnoredExceptionClass )
					return false;
			}
			
			if ($exception instanceof Cms_ExecutionException)
				$message = sprintf( "%s: %s. In %s, line %s", 
					get_class($exception), 
					$exception->getMessage(),
					'"'.$exception->document_name().'" ('.$exception->document_type().')',
					$exception->code_line );
			else
				$message = sprintf( "%s: %s. In %s, line %s", 
					get_class($exception), 
					$exception->getMessage(),
					$exception->getFile(),
					$exception->getLine() );
			
			$error = self::get_exception_details($exception);
			$log_to_db = !($exception instanceof Phpr_DatabaseException);
			
			$details = null;
			
			if(Phpr::$config->get('ENABLE_DB_ERROR_DETAILS', true))
				$details = self::encode_error_details($error);

			return $this->writeLogMessage($message, $log_to_db, $details);
		}

		/**
		 * Writes a message to the error log.
		 * You may override this method in the inherited class and write messages to a database table.
		 * @param string $message A message to write.
		 * @param boolean $log_to_db Whether or not to log to the database.
		 * @param string $details The error details string.
		 * @return boolean Returns true if the message was logged successfully.
		 */
		protected function writeLogMessage($message, $log_to_db = true, $details = null)
		{
			$record_id = null;
			
			if (!class_exists('Phpr_LogHelper') && !Phpr::$class_loader->load('Phpr_LogHelper'))
				echo $message;
			
			if ((Phpr::$config->get('LOG_TO_DB') || $this->_logFileName == null) && Db::$connection && !self::$disable_db_logging && $log_to_db)
			{
				if (!class_exists('Phpr_Trace_Log_Record') && !Phpr::$class_loader->load('Phpr_Trace_Log_Record'))
					return;

				$record = Phpr_Trace_Log_Record::add('ERROR', $message, $details);
				$record_id = $record->id;
				$ttl = Phpr::$config->get('ERROR_LOG_TTL', 14);
				if(is_int($ttl) && $ttl > 0)
					Db_DbHelper::query('delete from trace_log where log=:log and record_datetime < DATE_SUB(:date, INTERVAL :days DAY)', array('date'=>$record->record_datetime, 'days'=>$ttl, 'log'=>'ERROR'));
			}
			
			if(Phpr::$config->get('ENABLE_ERROR_STRING', true))
				$message .= ($details ? ' Encoded details: ' . $details : '');
			
			return array('id' => $record_id, 'status' => Phpr_LogHelper::writeLine($this->_logFileName, $message));
		}

		/**
		 * Loads the error log configuration
		 */
		protected function loadConfiguration()
		{
			// Determine if the error log is enabled
			//
			$this->_isEnabled = Phpr::$config !== null && Phpr::$config->get( "ERROR_LOG", false );

			if ( $this->_isEnabled )
			{
				// Load the log file path
				//
				$this->_logFileName = Phpr::$config->get( "ERROR_LOG_FILE", PATH_APP."/logs/errors.txt" );

				// Check whether the file and directory are writable
				//
				if ( file_exists($this->_logFileName) )
				{
					if ( !is_writable($this->_logFileName) )
					{
						$exception = new Phpr_SystemException( 'The error log file is not writable: '.$this->_logFileName );
						$exception->hint_message = 'Please assign writing permissions on the error log file for the Apache user.';
						throw $exception;
					}
				}
				else
				{
					$directory = dirname($this->_logFileName);
					if ( !is_writable($directory) )
					{
						$exception = new Phpr_SystemException( 'The error log file directory is not writable: '.$directory );
						$exception->hint_message = 'Please assign writing permissions on the error log directory for the Apache user.';
						throw $exception;
					}
				}
			}

			// Load the ignored exceptions list
			//
			$this->_ignoreExceptions = Phpr::$config !== null ? Phpr::$config->get( "ERROR_IGNORE", array() ) : array();
		}
	}
