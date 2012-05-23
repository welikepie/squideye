<?

	class Db_DataFilter
	{
		public $model_class_name = null;
		public $model_filters = null;
		public $list_columns = array();

		public function prepareListData()
		{
			$className = $this->model_class_name;
			$result = new $className();

			if ($this->model_filters)
				$result->where($this->model_filters);
			
			return $result;
		}
		
		public function applyToModel($model, $keys, $context = null)
		{
			return $model;
		}
		
		protected function keysToStr($keys)
		{
			return "('".implode("','", $keys)."')";
		}
		
		public function asString($keys, $context = null)
		{
			return null;
		}
	}

?>