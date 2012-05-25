<?
	$steps = array(
		'billing_info' => 'Billing Information',
		'shipping_info' => 'Shipping Information',
		'shipping_method' => 'Shipping Method',
		'payment_method' => 'Payment Method',
		'review' => 'Order Review',
		'pay' => 'Pay',
	);
?>

<ul class="progress">
<?
	$current_found = false;
	foreach ($steps as $step=>$name):
		$is_current = $checkout_step == $step;
		$current_found = $current_found || $is_current;
?>
	<li <? if ($is_current): ?>class="current"<? endif ?>>
		<? if ($current_found): ?>
			<span><?= h($name) ?></span>
		<? else: ?>
			<span><a href="#" onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}, extraFields: {'move_to': '<?= $step ?>'}})"><?= h($name) ?></a></span>
		<? endif ?>
	</li>
<?
	endforeach
?>
</ul>