<?

	/**
	 * PHP Road Trace Log Class
	 *
	 * Trace log class allows the application to write the tracing messages to trace log files,
	 * or to the database table 'trace_log'.
	 *
	 * To configure the trace log use the TRACE_LOG parameter in the application configuration file:
	 * $CONFIG["TRACE_LOG"]["ORDERS"] = PATH_APP."/logs/orders.txt";
	 * $CONFIG["TRACE_LOG"]["INFO"] = PATH_APP."/logs/support.txt";
	 * The second-level key determines the listener name. Use the listener names to write tracing message
	 * to different files: Phpr::$trace_log->write( 'Hello', 'ORDERS' );
	 * To write record to the database table 'trace_log' specify null value instead of the log file path:
	 * $CONFIG["TRACE_LOG"]["ORDERS"] = null;
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$trace_log.
	 *
	 * @see Phpr
	 */
	class Phpr_TraceLog
	{
		private $_listeners;

		/**
		 * Creates a new Phpr_ErrorLog instance.
		 */
		public function __construct()
		{
			$this->loadConfiguration();
		}

		/**
		 * Writes a tracing message to a log file.
		 * @param mixed $Message Specifies a message to log. The message can be an object, array or string.
		 * @param string $Listener Specifies a listener name to use. If this parameter is omitted, the first listener will be used.
		 * @return boolean Returns true if message was logged successfully.
		 */
		public function write( $Message, $Listener = null )
		{
			if ( !count($this->_listeners) )
				return false;

			// Evaluate the listener name and ensure whether it exists
			//
			if ( $Listener === null )
			{
				$keys = array_keys($this->_listeners);
				$Listener = $keys[0];
			} else
				if ( !array_key_exists($Listener, $this->_listeners) )
					return false;

			// Convert the message to string
			//
			if ( is_array($Message) || is_object($Message) )
				$Message = print_r( $Message, true );

			// Write the message
			//
			return $this->writeLogMessage( $Message, $Listener );
		}
		
		public function addListener($listenerName, $filePath) {
			if (!Phpr::$config->get('NO_TRACELOG_CHECK'))
			{
				if ( $filePath !== null )
				{
					// Check whether the file or directory is writable
					//
					if ( file_exists($filePath) )
					{
						if ( !is_writable($filePath) )
						{
							$exception = new Phpr_SystemException( 'The trace log file is not writable: '.$filePath );
							$exception->hint_message = 'Please assign writing permissions on the trace log file for the Apache user.';
							throw $exception;
						}
					}
					else
					{
						$directory = dirname($filePath);
						if ( !is_writable($directory) )
						{
							$exception = new Phpr_SystemException( 'The trace log file directory is not writable: '.$directory );
							$exception->hint_message = 'Please assign writing permissions on the trace log directory for the Apache user.';
							throw $exception;
						}
					}
				}
			}

			$this->_listeners[$listenerName] = $filePath;
		}

		/**
		 * Writes a message to the trace log.
		 * You may override this method in the inherited class and write messages to a database table.
		 * @param string $Message A message to write.
		 * @param string $Listener Specifies a listener name to use.
		 * @return boolean Returns true if the message was logged successfully.
		 */
		protected function writeLogMessage( $Message, $Listener )
		{
			if ( $this->_listeners[$Listener] !== null )
				return Phpr_LogHelper::writeLine( $this->_listeners[$Listener], $Message );
			else
			{
				if (!class_exists('Phpr_Trace_Log_Record') && !Phpr::$classLoader->load('Phpr_Trace_Log_Record'))
					return;

				Phpr_Trace_Log_Record::add( $Listener, $Message );
			}
		}

		/**
		 * Loads the error log configuration
		 */
		protected function loadConfiguration()
		{
			$this->_listeners = array();

			foreach ( Phpr::$config->get( "TRACE_LOG", array() ) as $listenerName=>$filePath )
			{
				$this->addListener($listenerName, $filePath);
			}
		}
	}