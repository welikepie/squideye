var shipping_info_changed = false;
var billing_info_changed = false;
var intialization = true;

var prev_item_quantity_value = null;
var item_price_request_timer = null;

function assign_billing_country_handler()
{
	$('Shop_Order_billing_country_id').addEvent('change', function(){
		billing_info_changed = true;
		$('Shop_Order_billing_country_id').getForm().sendPhpr(
			'onBillingCountryChange',
			{
				loadIndicator: {show: false}
			}
		)
	})
}

function assign_shipping_country_handler()
{
	$('Shop_Order_shipping_country_id').addEvent('change', function(){
		shipping_info_changed = true;
		billing_info_changed = true;

		$('Shop_Order_shipping_country_id').getForm().sendPhpr(
			'onShippingCountryChange',
			{
				loadIndicator: {show: false},
				onSuccess: function(){
					assign_shipping_state_handler.delay(500); 
					updateTotals();
				}
			}
		)
	})
	
	$('Shop_Order_shipping_addr_is_business').addEvent('click', function(){
		shipping_info_changed = true;
		updateTotals();
	})
}

function assign_shipping_state_handler()
{
	$('Shop_Order_shipping_state_id').addEvent('change', function(){
		billing_info_changed = true;
		shipping_info_changed = true;

		updateTotals();
	});
}

function assign_shipping_zip_handler()
{
	$('Shop_Order_shipping_zip').addEvent('change', function(){
		billing_info_changed = true;
		shipping_info_changed = true;

		updateTotals();
	});
}

function assign_shipping_city_handler()
{
	$('Shop_Order_shipping_city').addEvent('change', function(){
		billing_info_changed = true;
		shipping_info_changed = true;

		updateTotals();
	});
}

function updateItemList()
{
	cancelPopups();
	shipping_info_changed = true;
	billing_info_changed = true;

	$('item_list').getForm().sendPhpr(
		'onUpdateItemList',
		{
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				src: 'phproad/resources/images/form_load_30x30.gif',
				element: 'item_list'
			},
			onAfterUpdate: update_tooltips
		}
	)
}

function updateTotals()
{
	if (intialization)
		return;
	
	$('Shop_Order_shipping_country_id').getForm().sendPhpr('onUpdateTotals',{
		loadIndicator: {show: false},
		onBeforePost: LightLoadingIndicator.show.pass('Updating order totals...'), 
		onComplete: LightLoadingIndicator.hide
	});
}

function item_price_request()
{
	if (!$('Shop_OrderItem_auto_discount_price_eval').checked)
		return;
	
	var field_element = $('Shop_OrderItem_quantity');
	var test_value = field_element.value.trim();
	var customer_id = $('Shop_Order_customer_id') ? $('Shop_Order_customer_id').value : -1;

	if (test_value.test(/^[0-9]+$/))
	{
		field_element.getForm().sendPhpr('onUpdateItemPriceAndDiscount', {
			loadIndicator: {
				show: true,
				element: 'item_price_and_discount',
				injectInElement: true,
				src: 'phproad/resources/images/form_load_30x30.gif',
				hideOnSuccess: true
			},
			update: 'multi',
			extraFields: {'customer_id': customer_id}
		})
	}
}

function track_auto_price_eval(element)
{
	if (element.checked)
	{
		$('Shop_OrderItem_price').disabled = true;
		item_price_request();
	} else
	{
		$('Shop_OrderItem_price').disabled = false;
		$('Shop_OrderItem_price').focus();
	}
}

function track_auto_discount_eval(element)
{
	if (element.checked)
	{
		$('Shop_OrderItem_discount').disabled = false;
		$('Shop_OrderItem_discount').focus();
	}
	else
		$('Shop_OrderItem_discount').disabled = true;
}

function update_item_price(ev)
{
	var field_element = $('Shop_OrderItem_quantity');
	var ev = new Event(ev);

	if (prev_item_quantity_value == field_element.value.trim())
		return;
		
	prev_item_quantity_value = field_element.value.trim();

	$clear(item_price_request_timer);

	var test_value = prev_item_quantity_value;
	if (test_value.test(/^[0-9]+$/))
	{
		item_price_request_timer = item_price_request.delay(300);
	}
}

function track_itemp_price_change()
{
	var field_element = $('Shop_OrderItem_quantity');
	
	if (!field_element)
		return;
		
	prev_item_quantity_value = field_element.value;
	field_element.addEvent('keydown', update_item_price);
	field_element.addEvent('keyup', update_item_price);
	field_element.addEvent('keypress', update_item_price);
}

function track_discount_changes()
{
	$('Shop_Order_free_shipping').addEvent('click', updateTotals);
}

function track_shipping_override()
{
	var cb = $('Shop_Order_override_shipping_quote');

	if (cb.checked)
		$('form_field_manual_shipping_quoteShop_Order').removeClass('hidden');
	else
		$('form_field_manual_shipping_quoteShop_Order').addClass('hidden');
		
	if ($('Shop_Order_manual_shipping_quote').value)
		updateTotals();
}

function update_manual_shipping_quote(element)
{
	$('form_field_manual_shipping_quoteShop_Order').removeClass('error');
	updateTotals();
}

function set_manual_shipping_quote_error(element)
{
	$('form_field_manual_shipping_quoteShop_Order').addClass('error');
}

function assign_shipping_override_handler()
{
	var cb = $('Shop_Order_override_shipping_quote');
	if (cb)
	{
		cb.addEvent('click', track_shipping_override);

		var tracker = new InputChangeTracker($('Shop_Order_manual_shipping_quote'),  {regexp_mask: '^\s*[0-9]*?\.?[0-9]+\s*$'});
		tracker.addEvent('change', update_manual_shipping_quote);
		tracker.addEvent('invalid', set_manual_shipping_quote_error);
	}
}

function update_bundle_item_products()
{
	$('find_bundle_product_form').sendPhpr(
		'onUpdateBundleProductList',
		{
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				injectInElement: true
			},
			update: 'bundle_item_products'
		}
	)
}

function add_bundle_product(session_key)
{
	if (!$('bundle_item_product_id').get('value'))
	{
		alert('Plase select bundle product first.');
		return false;
	}
	
	new PopupForm('onAddProduct', 
	{
		ajaxFields: {
			'bundle_item_product_id': $('bundle_item_product_id').get('value'), 
			'bundle_master_order_item_id': $('bundle_master_order_item_id').get('value'),
			'bundle_master_bundle_item_id': $('bundle_item_id').get('value'), 
			'edit_session_key': session_key, 
			'customer_id': $('Shop_Order_customer_id') ? $('Shop_Order_customer_id').value : -1}
	});
		
	return false;
}

function record_selector_click(item)
{
	var selector = $(item).getParent();
	var selector_root = selector.getParent();
	var selected_input = $(item).getElement('input');
	selector.getElements('li.selectable').each(function(current_item){
		current_item.removeClass('current');
	});
	
	var master_input = selector_root.getElement('input.master');
	master_input.value = selected_input.value;

	$(item).addClass('current');
	window.fireEvent('phpr_recordselector_click', [selector_root]);
}

window.addEvent('domready', function(){
	if ($('phpr_lock_mode'))
		return;
		
	window.addEvent('phpr_recordselector_click', function(selector) {
		if (selector.id == 'shipping_option_selector')
			updateTotals();
	})
	
	if ($('Shop_Order_tax_exempt'))
	{
		$('Shop_Order_tax_exempt').addEvent('click', updateTotals);
	}
	
	window.addEvent('phpr_recordfinder_update', function(field_id){
		if (field_id == 'customer_id')
		{
			shipping_info_changed = true;
			billing_info_changed = true;
			$('Shop_Order_customer_id').getForm().sendPhpr(
				'create_onCustomerChanged',
				{
					loadIndicator: {show: true, element: $('Shop_Order_customer_id').getForm(), hideOnSuccess: true},
					onSuccess: function(){ 
						assign_billing_country_handler.delay(500); 
						assign_shipping_country_handler.delay(500); 
						assign_shipping_state_handler.delay(500); 
						assign_shipping_zip_handler.delay(500); 
						assign_shipping_city_handler.delay(500); 
					}
				}
			)
		}
	});
	if ($('Shop_Order_customer_id'))
	{
		$('Shop_Order_create_guest_customer').addEvent('click', function(){
			if ($('Shop_Order_create_guest_customer').checked)
				$('form_field_container_customer_idShop_Order').hide();
			else
				$('form_field_container_customer_idShop_Order').show();
		});
	}
	
	track_discount_changes();
	
	assign_billing_country_handler();
	assign_shipping_country_handler();
	assign_shipping_state_handler();
	assign_shipping_zip_handler(); 
	assign_shipping_city_handler(); 
	assign_shipping_override_handler();
	track_shipping_override();
	
	$('Shop_Order_shipping_zip').addEvent('change', function(){
		shipping_info_changed = true;
		billing_info_changed = true;
	});
	
	var shipping_tab = $('form_field_shipping_method_idShop_Order').getParent().findParent('li');

	shipping_tab.addEvent('onTabClick', function(){
		if (shipping_info_changed)
		{
			$('item_list').getForm().sendPhpr(
				'onUpdateShippingOptions',
				{
					loadIndicator: {
						show: true,
						hideOnSuccess: true
					},
					onSuccess: function(){
						shipping_info_changed = false;
					},
					onAfterUpdate: function(){
						assign_shipping_override_handler();
						track_shipping_override();
					}
				}
			)
		}
	});
	
	var billing_tab = $('form_field_payment_method_idShop_Order').getParent().findParent('li');
//	var discounts_tab = $('Shop_Order_discount').selectParent('li.form_page');
	
//	discounts_tab.addEvent('onTabClick', update_discount_tab);

	billing_tab.addEvent('onTabClick', function(){
		if (billing_info_changed)
		{
			$('item_list').getForm().sendPhpr(
				'onUpdateBillingOptions',
				{
					loadIndicator: {
						show: true,
						hideOnSuccess: true
					},
					onSuccess: function(){billing_info_changed = false;}
				}
			)
		}
	})
	
	intialization = false;
});