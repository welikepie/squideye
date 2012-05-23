<?

	class Backend_SettingsRecord extends Db_ActiveRecord
	{
		public static function get($className = null)
		{
			$obj = new $className();
			$records = $obj->find_all();
			if (!$records->count)
			{
				$obj->init_record();
				return $obj->save();
			}

			return $records[0];
		}
		
		protected function init_record()
		{
		}
	}

?>