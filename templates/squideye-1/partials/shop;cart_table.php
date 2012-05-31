<?

	// Normalise parameters
	if (isset($table_class)) { $table_class = ' class="' . $table_class . '"'; } else { $table_class = ''; }
	if (!isset($items)) { $items = array(); }

?><table<?= $table_class ?> id="item-table">
	<thead>
		<tr>
			<th>Cart items</th>
			<th>Quantity</th>
			<th>Price</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<? if (!count($items)) { ?>
		<tr>
			<td colspan="4">Your cart is empty.</td>
		</tr>
	<? } else {
		foreach ($items as $item) {
		
			$image_url = $item->product->image_url(0, 60, 'auto');
		
		?>
		<tr<?= $image_url ? ' class="image"' : '' ?>>
			<td>
				<? if ($image_url) { ?><img class="product-image" src="<?= $image_url ?>" alt="<?= h($item->product->name) ?>"><? } ?>
				<div><p class="name"><?= h($item->product->name) ?></p></div>
			</td>
			<td><input type="text" class="short text" name="item_quantity[<?= $item->key ?>]" value="<?= $item->quantity ?>"></td>
			<td><?= format_currency($item->total_price()) ?></td>
			<td><a data-key="<?= $item->key ?>">×</a></td>
		</tr>
		<? }
	} ?>
	</tbody>
</table>
<script type="text/javascript">
	$('table#item-table a[data-key]').click(function (ev) {
	
		ev.preventDefault();
		$(this).getForm().sendRequest( 'shop:on_deleteCartItem', {
			'update': {'cart-page': 'shop:cart_partial', 'mini-cart-count': 'shop:cart_count'},
			'confirm': 'Do you really want to remove this tattoo from the cart?',
			'extraFields': {'key': this.getAttribute('data-key')}
		});
	
	});
</script>