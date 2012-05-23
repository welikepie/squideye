<?

	class Db_Sql extends Db_SqlBase 
	{
		public static function create() 
		{
			return new self();
		}

		public function __toString() 
		{
			return $this->build_sql();
		}
	}

?>