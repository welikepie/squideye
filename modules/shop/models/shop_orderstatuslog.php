<?php

	class Shop_OrderStatusLog extends Db_ActiveRecord
	{
		public $table_name = 'shop_order_status_log_records';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = false;
		
		public $status_ids = array();

		public $calculated_columns = array(
			'status_name'=>array(
				'sql'=>'shop_order_statuses.name', 
				'join'=>array('shop_order_statuses'=>'shop_order_statuses.id=status_id'), 'type'=>db_text),
			'status_color'=>array('sql'=>'shop_order_statuses.color')
		);

		public $belongs_to = array(
			'status'=>array('class_name'=>'Shop_OrderStatus', 'foreign_key'=>'status_id')
		);
		
		public $custom_columns = array('send_notifications'=>db_bool);
		
		public $role_id = null;
		public $send_notifications = true;

		public static function create()
		{
			return new self();
		}
		
		public static function create_record($status_id, $order, $comment = null, $send_notifications = true)
		{
			if ($status_id == $order->status_id)
				return false;
				
			$prev_status = $order->status_id;
			$return = Backend::$events->fireEvent('shop:onOrderBeforeStatusChanged', $order, $status_id, $prev_status, $comment, $send_notifications);
			foreach ($return as $result)
			{
				if($result === false)
					return false;
			}
			
			$log_record = self::create();
			$log_record->status_id = $status_id;
			$log_record->order_id = $order->id;
			$log_record->comment = $comment;
			$log_record->save();

			Db_DbHelper::query('update shop_orders set status_id=:status_id, status_update_datetime=:datetime where id=:id', array(
				'status_id'=>$status_id,
				'datetime'=>Phpr_Date::userDate(Phpr_DateTime::now()),
				'id'=>$order->id
			));
			
			$paid_status = Shop_OrderStatus::get_status_paid();
			
			if ($status_id == $paid_status->id)
			{
				Db_DbHelper::query('update shop_orders set payment_processed=:payment_processed where id=:id', array(
					'payment_processed'=>Phpr_DateTime::now(),
					'id'=>$order->id
				));

				$change_processed = Backend::$events->fireEvent('shop:onOrderStockChange', $order, $paid_status);
				$stock_change_cancelled = false;
				foreach ($change_processed as $value) 
				{
					if ($value)
					{
						$stock_change_cancelled = true;
						break;
					}
				}

				if (!$stock_change_cancelled)
					$order->update_stock_values();

				Core_Metrics::log_order($order);
			}
			
			/*
			 * Send email message to the status recipients and customer
			 */
			$status = Shop_OrderStatus::create()->find($status_id);
			if ($status)
			{
				Backend::$events->fireEvent('shop:onOrderStatusChanged', $order, $status, $prev_status);

				if ($send_notifications)
				{
					$status->send_notifications($order, $comment);
				}
			}
				
			return true;
		}
		
		public function get_status_options()
		{
			$statuses = Shop_OrderStatus::create();
			
			if (!count($this->status_ids))
			{
				$statuses->join('shop_status_transitions', 'shop_status_transitions.to_state_id=shop_order_statuses.id'); 
				$statuses->where('shop_status_transitions.from_state_id=?', $this->status_id);
				$statuses->where('shop_status_transitions.role_id=?', $this->role_id);
			} else
			{
				$end_transitions = Shop_StatusTransition::listAvailableTransitionsMulti($this->role_id, $this->status_ids);
				$end_status_ids = array();
				foreach ($end_transitions as $transition)
					$end_status_ids[$transition->to_state_id] = 1;
					
				$end_status_ids = array_keys($end_status_ids);

				$statuses->where('shop_order_statuses.id in (?)', array($end_status_ids));
			}

			return $statuses->order('name')->find_all()->as_array('name', 'id');
		}

		public function define_columns($context = null)
		{
			$this->define_relation_column('status', 'status', 'Status ', db_varchar, '@name')->validation()->required('Please select new order status');
			$this->define_column('comment', 'Comment')->validation()->fn('trim');
			$this->define_column('send_notifications', 'Send email notifications');
		}

		public function define_form_fields($context = null)
		{
			$field = $this->add_form_field('status')->referenceSort('name')->emptyOption('<please select status>');
			if ($context != 'multiorder')
				$field->tab('Status');

			$field = $this->add_form_field('comment')->comment('If configured, the comment may appear in customer email notifications.', 'above', true)->commentTooltip('Use the {order_status_comment} variable in email templates<br/>if you want to add order status comments to customer notifications.');
			if ($context != 'multiorder')
				$field->tab('Status');

			$field = $this->add_form_field('send_notifications')->comment('Send notifications to customer(s) and LemonStand users in accordance with the order route settings.', 'above');
			if ($context != 'multiorder')
				$field->tab('Status');
		}
		
		public static function get_latest_transition_to($order_id, $status_id)
		{
			$obj = self::create();
			$obj->where('order_id=?', $order_id);
			$obj->where('status_id=?', $status_id);
			$obj->order('id desc');
			return $obj->find();
		}
		
		public function set_default_email_notify_checkbox() 
		{
			$this->send_notifications = Db_UserParameters::get('orders_email_on_status_change', null, '1');
		}
	}

?>