<?php

	class Db_DbHelper
	{
		public static function listTables()
		{
			return Db_Sql::create()->fetchCol('show tables');
		}
		
		public static function tableExists($tableName)
		{
			$tables = self::listTables();
			return in_array($tableName, $tables);
		}
		
		public static function executeSqlScript($filePath, $separator = ';')
		{
			$fileContents = file_get_contents($filePath);
			$fileContents = str_replace( "\r\n", "\n", $fileContents );
			$statements = explode( $separator."\n", $fileContents );

			$sql = Db_Sql::create();

			foreach ( $statements as $statement )
			{
				if ( strlen(trim($statement)) )
					$sql->execute($statement);
			}
		}
		
		public static function scalar($sql, $bind = array())
		{
			return Db_Sql::create()->fetchOne($sql, $bind); 
		}
		
		public static function scalarArray($sql, $bind = array())
		{
			$values = self::queryArray($sql, $bind);

			$result = array();
			foreach ($values as $value)
			{
				$keys = array_keys($value);
				if ($keys)
					$result[] = $value[$keys[0]];
			}
				
			return $result;
		}
		
		public static function query($sql, $bind = array())
		{
			$obj = Db_Sql::create();
			return $obj->query($obj->prepare($sql, $bind));
		}
		
		public static function queryArray($sql, $bind = array())
		{
			return Db_Sql::create()->fetchAll($sql, $bind);
		}
		
		public static function objectArray($sql, $bind = array())
		{
			$recordSet = self::queryArray($sql, $bind);
			
			$result = array();
			foreach ($recordSet as $record)
				$result[] = (object)$record;
				
			return $result;
		}

		public static function object($sql, $bind = array())
		{
			$result = self::objectArray($sql, $bind);
			if (!count($result))
				return null;
				
			return $result[0];
		}

		public static function getTableStruct( $tableName )
		{
			$sql = Db_Sql::create();
			$result = $sql->query($sql->prepare("SHOW CREATE TABLE `$tableName`"));
			return $sql->driver()->fetch($result, 1);
		}

		public static function getTableDump( $tableName, $fp = null, $separator = ';' )
		{
			$sql = Db_Sql::create();
			$qr = $sql->query("SELECT * FROM `$tableName`");
			
			$result = null;
			$columnNames = null;
			while ($row = $sql->driver()->fetch($qr))
			{
				if ( $columnNames === null )
					$columnNames = '`'.implode( '`,`', array_keys($row) ).'`';

				if (!$fp)
				{
					$result .= "INSERT INTO `$tableName`(".$columnNames.") VALUES (";
					$result .= $sql->quote( array_values($row) );
					$result .= ")".$separator."\n";
				} else
				{
					fwrite($fp, "INSERT INTO `$tableName`(".$columnNames.") VALUES (");
					fwrite($fp, $sql->quote( array_values($row) ));
					fwrite($fp, ")".$separator."\n");
				}
			}

			return $result;
		}
		
		public static function createDbDump($path, $options = array())
		{
			@set_time_limit(600);
			
			$tables_to_ignore = array_key_exists('ignore', $options) ? $options['ignore'] : array();
			$separator = array_key_exists('separator', $options) ? $options['separator'] : ';';
			
			$fp = @fopen($path, "w");
			if (!$fp)
				throw new Phpr_SystemException('Error opening file for writing: '.$path);
			
			$sql = Db_Sql::create();

			try
			{
				fwrite($fp, "SET NAMES utf8".$separator."\n\n");
				$tables = self::listTables();

				foreach ($tables as $index=>$table)
				{
					if (in_array($table, $tables_to_ignore))
						continue;
					
					fwrite($fp, '# TABLE '.$table."\n#\n");
					fwrite($fp, 'DROP TABLE IF EXISTS `'.$table."`".$separator."\n");
					fwrite($fp, self::getTableStruct($table).$separator."\n\n" );
					self::getTableDump($table, $fp, $separator);
					$sql->driver()->reconnect();
				}
			
				@fclose($fp);
				@chmod($path, Phpr_Files::getFilePermissions());
			}
			catch (Exception $ex)
			{
				@fclose($fp);
				throw $ex;
			}
		}
		
		/**
		 * Generates an unique column value
		 * @param Db_ActiveRecord $model A model to generate value for
		 * @param string $column_name A name of a column
		 * @param string $base_value A base value of the column. The unique value will be generated
		 * by appending the 'copy_1', 'copy_N' string to the base value.
		 * @param bool $case_sensitive Specifies whether function should perform a case-sensitive search 
		 * @return string
		 */
		public static function getUniqueColumnValue($model, $column_name, $base_value, $case_sensitive = false)
		{
			$base_value = trim($base_value);
			$base_value = preg_replace('/_copy_[0-9]+$/', '', $base_value);

			$column_value = $base_value;
			$counter = 1;
			$table_name = $model->table_name;
			
			$query = $case_sensitive ? 
				"select count(*) from $table_name where $column_name=:test_value" :
				"select count(*) from $table_name where lower($column_name)=lower(:test_value)";

			while (self::scalar("select count(*) from $table_name where $column_name=:test_value", array(
					'test_value'=>$column_value
				)))
			{
				$column_value = $base_value.'_copy_'.$counter;
				$counter++;
			}
			
			return $column_value;
		}
		
		/**
		 * Creates a SQL query string for searching specified fields for specified words or phrases
		 * @param string $query Search query
		 * @param array|array $fields A list of fields to search in. A single field can be specified as a string
		 * @param int $min_word_length Allows to ignore words with length less than the specified
		 * @return string Returns a string
		 */
		public static function formatSearchQuery($query, $fields, $min_word_length = null)
		{
			if (!is_array($fields))
				$fields = array($fields);
			
			$words = Core_String::split_to_words($query);

			$word_queries = array();
			foreach ($words as $word)
			{
				if (!strlen($word))
					continue;
					
				if ($min_word_length && mb_strlen($word) < $min_word_length)
					continue;

				$word = trim(mb_strtolower($word));
				$word_queries[] = '%1$s like \'%2$s'.mysql_real_escape_string($word).'%2$s\'';
			}

			$field_queries = array();
			foreach ($fields as $field)
			{
				if ($word_queries)
					$field_queries[] = '('.sprintf(implode(' and ', $word_queries), $field, '%').')';
			}

			if (!$field_queries)
				return '1=1';
				
			return '('.implode(' or ', $field_queries).')';
		}
	}

?>