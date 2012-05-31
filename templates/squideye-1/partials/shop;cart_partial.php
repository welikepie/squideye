<? echo(flash_message());

	$items = Shop_Cart::list_active_items();
	if (!$items) {

?><p>You have no items in your cart.</p><?

	} else {
	
?><?= open_form() ?>
	<? $this->render_partial('shop:cart_table', array('items' => $items, 'table_class' => 'cart')); ?>
	
	<h3>Cart Total</h3>
	<p><?= format_currency($cart_total) ?></p>
	<h3>Discount</h3>
	<p><?= format_currency($discount) ?></p>
	<h3>Estimated Total</h3>
	<p><?= format_currency($estimated_total) ?></p>
	
	<p>* Shipping cost, taxes and discounts will be evaluated during the checkout process.</p>
	
	<div class="controls">
		<button type="button" name="apply_changes">Apply Changes</button>
		<button type="button" name="checkout">Checkout</button>
		<input type="hidden" name="redirect" value="<?= root_url('/checkout_start') ?>">
	</div>
</form>
<script type="text/javascript">

	$('div#cart-page button[name="apply_changes"]').click(function (ev) {
		ev.preventDefault();
		$(this).getForm().sendRequest('on_action', {
			'update': {'cart-page': 'shop:cart_partial', 'mini-cart-count': 'shop:cart_count'}
		});
	});
	$('div#cart-page button[name="checkout"]').click(function (ev) {
		ev.preventDefault();
		$(this).getForm().sendRequest('shop:on_setCouponCode');
	});

</script><?
	
	}

?>