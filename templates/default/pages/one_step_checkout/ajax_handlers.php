<?
function create_local_order($controller, $page, $params)
{
  if (!Shop_Cart::list_active_items())
    throw new Exception('Your cart is empty!');
 
  $controller->exec_action_handler('shop:on_checkoutSetBillingInfo');
  Shop_CheckoutData::copy_billing_to_shipping();
  Shop_CheckoutData::set_payment_method(Shop_PaymentMethod::find_by_api_code('credit_card')->id);
  Shop_CheckoutData::set_shipping_method(Shop_ShippingOption::find_by_api_code('post_service')->id);
   
  Phpr::$response->redirect(root_url('/checkout_order_preview'));
}
?>