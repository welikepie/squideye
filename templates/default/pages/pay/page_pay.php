<h2>Pay</h2>

<? if (!$order): ?>
  <p>Order not found.</p>
<? else: ?>
  <? if ($order->payment_processed()): ?>
    <p>This order is already paid. Thank you!</p>
  <? else: ?>
    <div class="bottom_offset">
      <ul class="scoreboard">
        <li>
          <h3>Order</h3>
          <p># <?= $order->id ?></p>
        </li>
        <li>
          <h3>Total </h3>
          <p><?= format_currency($order->total) ?></p>
        </li>
        <li class="last">
          <h3>Payment method</h3>
          <p><?= h($payment_method->name) ?></p>
        </li>
      </ul>
      <div class="clear"></div>
    </div>
    
    <? if ($order->payment_method->has_payment_form()): ?>
      <div class="payment_form">
        <? $payment_method->render_payment_form($this) ?>
      </div>
    <? else: ?>
      <? if ($message = $payment_method->pay_offline_message()): ?>
        <p><?= h($message) ?></p>    
      <? else: ?>
        <p>Payment method "<?= h($payment_method->name) ?>" has no payment form. Please pay and notify us.</p>    
      <? endif ?>
      <p class="no_print"><a href="/"><img src="<?= theme_resource_url('images/btn_continue_shopping.gif') ?>" alt="Continue shopping"/></a></p>
    <? endif ?> 
  <? endif ?>
<? endif ?>