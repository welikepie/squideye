<?

	class Db_DataFilterSwitcher
	{
		public function applyToModel($model, $enabled, $context = null)
		{
			return $model;
		}
		
		public function asString($keys, $context = null)
		{
			return null;
		}
	}

?>