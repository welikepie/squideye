<?

	class Db_Where extends Db_WhereBase
	{
		public static function create()
		{
			return new self();
		}

		public function __toString()
		{
			return $this->build_where();
		}
	}

?>