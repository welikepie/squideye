<?php

	/*
	 * Sortable model extension
	 */
	
	/*
	 * Usage in model.
	 * Model table must have sort_order field.
	 * In the model class definition: public $implement = 'Db_Sortable';
	 * To set orders: $obj->set_item_orders($item_ids, $item_orders);
	 */
	
	class Db_Sortable extends Phpr_Extension
	{
		private $model;
		
		public function __construct($model)
		{
			$this->model = $model;
			$model->addEvent('onAfterCreate', $this, 'set_order_id');
		}
		
		public function set_order_id()
		{
			$new_id = mysql_insert_id();
			Db_DbHelper::query('update `'.$this->model->table_name.'` set sort_order=:new_id where id=:new_id', array(
				'new_id'=>$new_id
			));
		}
		
		public function set_item_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			if (count($item_ids) != count($item_orders))
				throw new Phpr_ApplicationException('Invalid set_item_orders call - count of item_ids does not match a count of item_orders');

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update `'.$this->model->table_name.'` set sort_order=:sort_order where id=:id', array(
					'sort_order'=>$order,
					'id'=>$id
				));
			}
		}
	}

?>