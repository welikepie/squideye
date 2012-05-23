<?

	class Shop_CustomerBillingStateFilter extends Db_DataFilter
	{
		public $model_class_name = 'Shop_CountryState';
		public $list_columns = array('name');

		public function applyToModel($model, $keys, $context = null)
		{
			$model->where('billing_state_id in (?)', array($keys));
		}
	}

?>