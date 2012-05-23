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
	 * PHP Road Session Class
	 *
	 * This class incapsulates the PHP session.
	 *
	 * The instance of this class is available in the Phpr global object: Phpr::$session.
	 *
	 * @see Phpr
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Andy Duke
	 */
	class Phpr_Session implements ArrayAccess, IteratorAggregate, Countable
	{
		/**
		 * Flash object
		 *
		 * @var Phpr_Flash
		 */
		public $flash = null;

		/**
		* Begins a session.
		* You must always start the session before use any session data.
		* You may achieve the "auto start" effect by adding the following line to the application init.php script:
		* Phpr::$session->start();
		*
		* @return boolean
		*/
		public function start()
		{
			if ( $result = session_start() )
			{
				$this->flash = new Phpr_Flash();
				if ($this->flash)
				{
					if (array_key_exists('flash_partial', $_POST) && strlen($_POST['flash_partial']))
						$this->flash['system'] = 'flash_partial:'.$_POST['flash_partial'];
				}
			}
				
			return $result;
		}
		
		public function restoreDbData()
		{
			$session_id_param = Phpr::$config->get('SESSION_PARAM_NAME', 'ls_session_id');
			$session_id = Phpr::$request->getField($session_id_param);
			// if ($session_id)
			// 	session_id($session_id);
			
			if ($session_id)
			{
				$this->restore($session_id);
			}
		}

		/**
		 * Destroys all data registered to a session 
		 */
		public function destroy()
		{
			if ( !session_id() )
				session_start();

			$_SESSION = array();
			session_destroy();
		}

		/**
		* Determines whether the session contains a value
		* @param string $Name Specifies a value name
		* @return boolean
		*/
		public function has( $Name )
		{
			return isset( $_SESSION[$Name] );
		}

		/**
		* Returns a value from the session.
		* @param string $Name Specifies a value name
		* @param mixed $Default Specifies a default value
		* @return mixed
		*/
		public function get( $Name, $Default = null )
		{
			if ( $this->has($Name) )
				return $_SESSION[$Name];

			return $Default;
		}

		/**
		* Writes a value to the session.
		* @param string $Name Specifies a value name
		* @param mixed $Value Specifies a value to write.
		*/
		public function set( $Name, $Value = null )
		{
			if ( $Value === null )
				unset($_SESSION[$Name]);
			else
				$_SESSION[$Name] = $Value;
		}

		/**
		* Removes a value from the session.
		* @param string $Name Specifies a value name
		*/
		public function remove( $Name )
		{
			$this->set( $Name, null );
		}

		/**
		 * Destroys the session object.
		 */
		public function __destruct()
		{
		}
		
		public function reset()
		{
			foreach ($_SESSION as $name=>$value)
				unset($_SESSION[$name]);
				
			$this->resetDbSessions();
		}

		/**
		* Iterator implementation
		*/
		
		function offsetExists( $offset )
		{
			return isset($_SESSION[$offset]);
		}
		
		function offsetGet( $offset )
		{
			return $this->get( $offset, null );
		}
		
		function offsetSet ($offset, $value )
		{
			$this->set( $offset, $value );
		}
		
		function offsetUnset($offset)
		{
			unset( $_SESSION[$offset] );
		}
		
		function getIterator()
		{
			return new ArrayIterator( $_SESSION );
		}

		/**
		* Returns a number of flash items
		* @return integer
		*/
		public function count()
		{
			return count($_SESSION);
		}
		
		/*
		 * Sessions in the database
		 */
		
		public function resetDbSessions()
		{
			Db_DbHelper::query('delete from db_session_data where datediff(NOW(), created_at) > 1');
		}
		
		public function store()
		{
			$session_id = session_id();
			
			Db_DbHelper::query('delete from db_session_data where session_id=:session_id', array('session_id'=>$session_id));
			
			$data = serialize($_SESSION);
			Db_DbHelper::query('insert into db_session_data(session_id, session_data, created_at, client_ip) values (:session_id, :session_data, NOW(), :client_ip)', array(
				'session_id'=>$session_id,
				'session_data'=>$data,
				'client_ip'=>Phpr::$request->getUserIp()
			));
		}

		public function restore($session_id)
		{
			$data = Db_DbHelper::scalar('select session_data from db_session_data where session_id=:session_id and client_ip=:client_ip', array(
				'session_id'=>$session_id,
				'client_ip'=>Phpr::$request->getUserIp()
			));
			
			if (strlen($data))
			{
				try
				{
					$data = unserialize($data);
					if (is_array($data))
					{
						foreach ($data as $key=>$value)
							$this->set($key, $value);
					}
				} catch (Exception $ex) {}
			}
		}
	}

?>