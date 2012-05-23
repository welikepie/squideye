<?

	class Db_WhereBase extends Db_Base 
	{
		private $where = array();
		
		private static $get_matches;

		public function reset()
		{
			$this->where = array();
		}
	
		protected function _where($operator = 'AND', $cond) 
		{
			if (is_null($cond)) 
				return $this;
		
			$args = func_get_args();
			// Off $operator
			array_shift($args);
			// Off $cond
			array_shift($args);

			if ($cond instanceof Db_WhereBase)
				$cond = $cond->build_where();
			else 
			{
				if(!self::$get_matches) {
					self::$get_matches = create_function(
						'$matches',
						'return \':__table_name__.\' . $matches[0];'
					);
				}
			
				$cond = preg_replace_callback('/^([a-z_0-9`]+)[\s|=]+/i',
					self::$get_matches,
					$cond);

				if (array_key_exists(0, $args) && is_array($args[0]))
					$args = $args[0];

				$cond = $this->prepare($cond, $args);
			}
		
			if (count($this->where) > 0)
				$cond = ' ' . $operator . ' (' . trim($cond) . ')';
			else
				$cond = ' (' . trim($cond) . ')';

			$this->where[] = $cond;
			return $this;
		}
	
		/**
		 * @return Where
		 */
		public function where() 
		{
			$args = func_get_args();
			return call_user_func_array(array(&$this, '_where'), array_merge(array('AND'), $args));
		}

		/**
		 * @return Where
		 */
		public function orWhere() 
		{
			$args = func_get_args();
			return call_user_func_array(array(&$this, '_where'), array_merge(array('OR'), $args));
		}

		public function build_where() 
		{
			$where = array();

			if (count($this->where))
				$where[] = implode(' ', $this->where);

			return implode(' ', $where);
		}
		
		public function reset_where()
		{
			$this->where = array();
		}
	}

?>