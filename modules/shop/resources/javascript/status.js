function update_template_status()
{
	$('Shop_OrderStatus_customer_message_template_id').disabled = !$('Shop_OrderStatus_notify_customer').checked;
	$('Shop_OrderStatus_customer_message_template_id').select_update();
}

window.addEvent('domready', function(){
	$('Shop_OrderStatus_notify_customer').addEvent('click', update_template_status);
	update_template_status();
})