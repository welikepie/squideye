<?

	class Db_SqlBase extends Db_WhereBase 
	{
		protected $_driver = null;
		
		public $parts = array(
			'calc_rows' => false,
			'from' => array(),
			'fields' => array(),
			'order' => array()
			);
			
		public function reset() {
			parent::reset();
			$this->parts = array(
				'calc_rows' => false,
				'from' => array(),
				'fields' => array(),
				'order' => array()
				);
		}

		public function select($fields = '*', $table_fields = '', $replace_columns = false) 
		{
			if (func_num_args() > 1) 
			{
				// 1. select(table, fields), append table. to fields
				$table = $fields;
				$fields = explode(',', $table_fields);
				foreach($fields as &$field) {
					if (strstr($field, '.') === false) {
						$field = $table . '.' . $field;
					}
				}
			} else {
				// 2. select(fields)
				$fields = explode(',', $fields);
			}

			// Merge fields
			if (!$replace_columns) {
				$this->parts['fields'] = array_merge($this->parts['fields'], $fields);
			} else {
				$this->parts['fields'] = $fields;
			}
			return $this;
		}
		
		/**
		 * @param string $table
		 * @param string|string[] $columns
		 * @return SQL
		 */
		public function from($table, $columns = '*', $replace_columns = false) 
		{
			$this->set_part('from', $table);
			
			if ((strpos($columns, ',') === false) && (strpos($columns, '.') === false) && (strpos($columns, '(') === false))
				$columns = $table . '.' . $columns;

			if (!$replace_columns)
				$this->set_part('fields', $columns);
			else
				$this->parts['fields'] = Util::splat($columns);

			return $this;
		}
		
		/**
		 * Adds column(s) to the query field list
		 * @param string $column Column definition
		 * @return SQL
		 */
		public function addColumn($column)
		{
			$this->select($column);
			
			return $this;
		}

		public function join($table_name, $cond, $columns = '', $type = 'left') 
		{
			if (!in_array(strtolower($type), array('left', 'inner', 'left outer', 'full outer')))
				$type = null;
			
			if ($columns == '*')
				$columns = $table_name . '.*';

			$this->parts['join'][] = array(
			'type' => $type,
			'name' => $table_name,
			'cond' => $cond
			);
			
			if (trim($columns) != '')
				$this->set_part('fields', $columns);
			
			return $this;
		}

		public function group($spec) 
		{
			if (is_string($spec)) {
				if ($spec == '') return $this;
				$spec = explode(',', $spec);
			} else
				settype($spec, 'array');

			foreach ($spec as $val) 
			{
				if (strpos($val, '.') === false)
					$val = ':__table_name__.' . $val;

				$this->set_part('group', trim($val));
			}
			
			return $this;
		}
		
		public function having($spec)
		{
			if (is_string($spec)) {
				if ($spec == '') return $this;
				$spec = explode(',', $spec);
			} else
				settype($spec, 'array');

			foreach ($spec as $val) 
				$this->set_part('having', trim($val));
			
			return $this;
		}
		
		public function order($spec) 
		{
			if (is_null($spec)) 
				return $this;
				
			if (is_string($spec)) 
			{
				if ($spec == '') return $this;
				$spec = explode(',', $spec);
			} else
				settype($spec, 'array');

			foreach ($spec as $val) 
			{
				$asc = (strtoupper(substr($val, -4)) == ' ASC');
				$desc = (strtoupper(substr($val, -5)) == ' DESC');
				// if (! $asc && ! $desc)
				// 	$val .= ' ASC';
					
				$val = trim($val);
					
				//if ((strpos($val, '.') === false) && (strpos($val, '(') === false))
				//	$val = ':__table_name__.' . $val;

				$this->set_part('order', trim($val));
			}
			
			return $this;
		}
		
		public function reset_order()
		{
			$this->reset_part('order');
		}
		
		public function reset_joins()
		{
			$this->reset_part('join');
		}
		
		protected function has_order() 
		{
			return isset($this->parts['order']) && (count($this->parts['order']) != 0);
		}

		protected function has_group() 
		{
			return isset($this->parts['group']) && (count($this->parts['group']) != 0);
		}

		/**
		 * Sets a limit count and offset to the query.
		 *
		 * @param int $count The number of rows to return.
		 * @param int $offset Start returning after this many rows.
		 * @return void
		 */
		public function limit($count = null, $offset = null) 
		{
			if (is_null($count) && is_null($offset)) 
				return $this;
				
			$this->parts['limitCount'] = (int)$count;
			$this->parts['limitOffset'] = (int)$offset;

			return $this;
		}
		
		/**
		 * Sets the limit and count by page number.
		 *
		 * @param int $page Limit results to this page number.
		 * @param int $rowCount Use this many rows per page.
		 * @return void
		 */
		public function limitPage($page, $rowCount) 
		{
			if (is_null($page) && is_null($rowCount)) 
				return $this;
				
			$page = ($page > 0) ? $page : 1;
			
			$rowCount = ($rowCount > 0) ? $rowCount : 1;
			$this->parts['limitCount'] = (int)$rowCount;
			$this->parts['limitOffset'] = (int)$rowCount * ($page - 1);
			return $this;
		}
		
		public function use_calc_rows() 
		{
			$this->parts['calc_rows'] = true;
		}

		public function build_sql() 
		{
			$sql = array();

			// select only sepcified fields
			$sql[] = "SELECT";
			if ($this->parts['calc_rows'])
				$sql[] = 'SQL_CALC_FOUND_ROWS';

			// determine sql fields versus custom fields
			$fields = $this->parts['fields'];
			if (count($fields) == 0)
				$fields = array(':__table_name__.*');

			$sql[] = implode(', ', $fields);
			$sql[] = 'FROM :__table_name__';

			// joins
			if (isset($this->parts['join'])) 
			{
				$list = array();
				foreach ($this->parts['join'] as $join) 
				{
					$tmp = '';
					// add the type (LEFT, INNER, etc)
					if (!empty($join['type']))
						$tmp .= strtoupper($join['type']) . ' ';

					// add the table name and condition
					$tmp .= 'JOIN ' . $join['name'];
					$tmp .= ' ON ' . $join['cond'];
					// add to the list
					$list[] = $tmp . ' ';
				}
				
				// add the list of all joins
				$sql[] = implode("\n", $list) . "\n";
			}
			
			// add where
			$where = $this->build_where();
			if (trim($where) != '')
				$sql[] = "WHERE\n\t" . $where;
				
			// grouped by these columns
			if (isset($this->parts['group']) && count($this->parts['group']))
				$sql[] = "GROUP BY\n\t" . implode('', $this->parts['group']);

			// having these conditions
			if (isset($this->parts['having']) && count($this->parts['having']))
				$sql[] = "HAVING\n\t" . implode('', $this->parts['having']);

			// add order
			if (count($this->parts['order']))
				$sql[] = "ORDER BY\n\t" . implode(', ', $this->parts['order']);

			// add limit
			$count = !empty($this->parts['limitCount']) ? (int)$this->parts['limitCount'] : 0;
			$offset = !empty($this->parts['limitOffset']) ? (int)$this->parts['limitOffset'] : 0;
			
			if ($count > 0) 
			{
				$offset = ($offset > 0) ? $offset : 0;
				$sql[] = ' ' . $this->driver()->limit($offset, $count);
			}
			
			$sql = implode(' ', $sql);
			return $this->prepare_tablename($sql);
		}
		
		private function prepare_tablename($sql, $tablename = '') 
		{
			return str_replace(':__table_name__', ($tablename == '') ? $this->parts['from'][0] : $tablename, $sql);
		}

		/* Insert/Update/Delete */

		/**
		 * Inserts a table row with specified data.
		 *
		 * @param string $table The table to insert data into.
		 * @param array $bind Column-value pairs.
		 * @return int The number of affected rows.
		 */
		public function sql_insert($table, $values, $pairs = null) 
		{
			// col names come from the array keys
			if (is_null($pairs)) 
			{
				$cols = array_keys($values);
				// build the statement
				$sql = 'INSERT INTO ' . $table
					. '(' . implode(', ', $cols) . ') '
					. 'VALUES (:' . implode(', :', $cols) . ')';

				// execute the statement and return the number of affected rows
				$this->query($this->prepare_tablename($this->prepare($sql, $values), $table));
			} else 
			{
				$cols = $values;
				$values = array();
				foreach($pairs as $pair)
					$values[] = $this->prepare('(?, ?)', $pair[0], $pair[1]);

				// build the statement
				$sql = 'INSERT INTO ' . $table
					. '(' . implode(', ', $cols) . ') '
					. 'VALUES' . implode(',', $values);
				// execute the statement and return the number of affected rows
				$this->query($this->prepare_tablename($sql, $table));
			}

			return $this->row_count();
		}
		
		/**
		 * Updates table rows with specified data based on a WHERE clause.
		 *
		 * @param string $table The table to udpate.
		 * @param array $bind Column-value pairs.
		 * @param WhereBase|string $where UPDATE WHERE clause.
		 * @param string $order UPDATE ORDER BY clause.
		 * @return int The number of affected rows.
		 */
		public function sql_update($table, $bind, $where, $order = '') 
		{
			// is the $where a WhereBase object?
			if ($where instanceof WhereBase)
				$where = $where->build_where();

			if (is_array($bind)) 
			{
				// build "col = :col" pairs for the statement
				$set = array();
				foreach ($bind as $col => $val)
					$set[] = "$col = :$col";

				$record = implode(', ', $set);
			} else 
			if (is_string($bind))
				$record = $bind;
			else 
				return -1;
				
			$sql = 'UPDATE ' . $table
				. ' SET ' . $record
				. (($where) ? " WHERE $where" : '')
				. (($order != '') ? " ORDER BY $order" : '');
			// execute the statement and return the number of affected rows
			$this->query($this->prepare_tablename($this->prepare($sql, $bind), $table));
			return $this->row_count();
		}
		
		/**
		 * Deletes table rows based on a WHERE clause.
		 *
		 * @param string $table The table to udpate.
		 * @param WhereBase|string $where DELETE WHERE clause.
		 * @return int The number of affected rows.
		 */
		public function sql_delete($table, $where) 
		{
			// is the $where a WhereBase object?
			if ($where instanceof WhereBase)
				$where = $where->build_where();

			// build the statement
			$sql = 'DELETE FROM ' . $table . (($where) ? " WHERE $where" : '');
			// execute the statement and return the number of affected rows
			$this->query($this->prepare_tablename($sql, $table));
			return $this->row_count();
		}
		
		/* SQL execute */

		public function query($sql) 
		{
			return $this->execute($sql);
		}
		
		/* Utility routines */

		public function row_count() 
		{
			return $this->driver()->row_count();
		}
		
		/**
		 * Gets the last inserted ID.
		 *
		 * @param string $tableName table or sequence name needed for some PDO drivers
		 * @param string $primaryKey primary key in $tableName need for some PDO drivers
		 * @return integer
		 */
		public function last_insert_id($tableName = null, $primaryKey = null) 
		{
			return $this->driver()->last_insert_id($tableName, $primaryKey);
		}

		/**
		 * Returns the column descriptions for a table.
		 *
		 * @return array
		 */
		public function describe_table($table) 
		{
			return $this->driver()->describe_table($table);
		}
		
		/* Fetch methods */
		
		protected function _fetchAll($result, $col = null) 
		{
			$data = array();
			while ($row = $this->driver()->fetch($result, $col))
				$data[] = $row;
				
			return $data;
		}
		
		/**
		 * Fetches all SQL result rows as a sequential array.
		 *
		 * @param string $sql An SQL SELECT statement.
		 * @param array $bind Data to bind into SELECT placeholders.
		 * @return array
		 */
		public function fetchAll($sql, $bind = null) 
		{
			$result = Phpr::$events->fire_event(array('name' => 'db:onBeforeDatabaseFetch', 'type' => 'filter'), array(
				'sql' => $sql,
				'fetch' => null
			));
			
			extract($result);
			
			if(isset($fetch))
				return $fetch;
		
			$result = $this->query($this->prepare($sql, $bind));
			$fetch = $this->_fetchAll($result);

			Phpr::$events->fire_event('db:onAfterDatabaseFetch', $sql, $fetch);
					
			return $fetch;
		}
		
		/**
		 * Fetches the first column of all SQL result rows as an array.
		 *
		 * The first column in each row is used as the array key.
		 *
		 * @param string $sql An SQL SELECT statement.
		 * @param array $bind Data to bind into SELECT placeholders.
		 * @return array
		 */
		public function fetchCol($sql, $bind = null) 
		{
			$result = Phpr::$events->fire_event(array('name' => 'db:onBeforeDatabaseFetch', 'type' => 'filter'), array(
				'sql' => $sql,
				'fetch' => null
			));
			
			extract($result);
			
			if(isset($fetch))
				return $fetch;
		
			$result = $this->query($this->prepare($sql, $bind));
			$fetch = $this->_fetchAll($result, 0);

			Phpr::$events->fire_event('db:onAfterDatabaseFetch', $sql, $fetch);
					
			return $fetch;
		}
		
		/**
		 * Fetches the first column of the first row of the SQL result.
		 *
		 * @param string $sql An SQL SELECT statement.
		 * @param array $bind Data to bind into SELECT placeholders.
		 * @return string
		 */
		public function fetchOne($sql, $bind = null) 
		{
			$result = Phpr::$events->fire_event(array('name' => 'db:onBeforeDatabaseFetch', 'type' => 'filter'), array(
				'sql' => $sql,
				'fetch' => null
			));
			
			extract($result);
			
			if(isset($fetch))
				return $fetch;
		
			$result = $this->query($this->prepare($sql, $bind));
			$fetch = $this->driver()->fetch($result, 0);

			Phpr::$events->fire_event('db:onAfterDatabaseFetch', $sql, $fetch);
					
			return $fetch;
		}
		
		/**
		 * Fetches the first row of the SQL result.
		 *
		 * @param string $sql An SQL SELECT statement.
		 * @param array $bind Data to bind into SELECT placeholders.
		 * @return array
		 */
		public function fetchRow($sql, $bind = null) 
		{
			$result = Phpr::$events->fire_event(array('name' => 'db:onBeforeDatabaseFetch', 'type' => 'filter'), array(
				'sql' => $sql,
				'fetch' => null
			));
			
			extract($result);
			
			if(isset($fetch))
				return $fetch;
		
			$result = $this->query($this->prepare($sql, $bind));
			$fetch = $this->driver()->fetch($result);

			Phpr::$events->fire_event('db:onAfterDatabaseFetch', $sql, $fetch);
					
			return $fetch;
		}
		
		/* Common methods */

		public function execute($sql) 
		{
			if (Phpr::$traceLog)
				Phpr::$traceLog->write($sql, 'SQL');

			if (Phpr::$config && Phpr::$config->get('ENABLE_DEVELOPER_TOOLS') && Backend::$events)
				Backend::$events->fire_event('core:onBeforeDatabaseQuery', $sql);

			$result = $this->driver()->execute($sql);
			
			if (Phpr::$config && Phpr::$config->get('ENABLE_DEVELOPER_TOOLS') && Backend::$events)
				Backend::$events->fire_event('core:onAfterDatabaseQuery', $sql, $result);
			
			return $result;
		}

		/* Service routines */
		
		public function driver() 
		{
			if ($this->_driver === null) 
			{
				if (isset(Phpr::$config['driver']))
					$driver = Phpr::$config['driver'] . 'Driver';
				else
					$driver = 'Db_MySQLDriver';

				$this->_driver = new $driver();
			}

			return $this->_driver;
		}

		protected function set_part($name, $value) 
		{
			if (!isset($this->parts[$name]))
				$this->parts[$name] = array();
	
			$this->parts[$name][] = $value;
		}

		protected function reset_part($name) 
		{
			$this->parts[$name] = array();
		}
		
		protected function get_limit() 
		{
			return (isset($this->parts['limitCount']) ? $this->parts['limitCount'] : 0);
		}
	}
