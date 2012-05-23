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
	 * The flash provides a way to pass temporary objects between actions.
	 *
	 * The instance of this class is available in the Session object: Phpr::$session->flash
	 *
	 * @see Phpr
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Andy Duke
	 */
	class Phpr_Flash implements ArrayAccess, IteratorAggregate, Countable
	{
		public $flash = array();
	
		/**
		 * Creates a new Phpr_Flash instance
		 */
		public function __construct()
		{
			if ( !Phpr::$session->has('__flash') )
				return;

			$this->flash = Phpr::$session->get('__flash');
			$this->now();
		}

		/**
		 * Removes an object with a specified key or erases the flash data.
		 * @param string $Key Specifies a key to remove, optional
		 */
		public function discard( $Key = null )
		{
			if ( $Key === null )
				$this->flash = array();
			else
				unset( $this->flash[$Key] );
		}

		/**
		 * Stores the flash data to the session.
		 * @param string $Key Specifies a key to store, optional
		 */
		public function store( $Key = null )
		{
			if ( $Key === null)
				Phpr::$session->set( '__flash', $this->flash );
			else
				Phpr::$session->set( '__flash', array($Key=>$this->flash[$Key]) );
		}

		/*
		 * Removes the flash data from the session.
		 */
		public function now()
		{
			Phpr::$session->remove( '__flash' );
		}

		/**
		* Iterator implementation
		*/
		
		function offsetExists( $offset )
		{
			return isset($this->flash[$offset]);
		}
		
		function offsetGet( $offset )
		{
			if ( $this->offsetExists($offset) )
				return $this->flash[$offset];
			else
				return (false);
		}
		
		function offsetSet ($offset, $value )
		{
			if ($offset)
				$this->flash[$offset] = $value;
			else
				$this->glash[] = $value;
			$this->store();
		}
		
		function offsetUnset($offset)
		{
			unset( $this->flash[$offset] );
			$this->store();
		}
		
		function getIterator()
		{
			return new ArrayIterator( $this->flash );
		}

		/**
		* Returns a number of flash items
		* @return integer
		*/
		public function count()
		{
			return count($this->flash);
		}
	}

?>