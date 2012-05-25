<ul class="scoreboard">
  <li class="last">
    <h3>Estimated Total</h3>
    <p><?= format_currency($estimated_total) ?></p>
    <p class="description">Cart total: <strong><?= format_currency($cart_total) ?></strong><br/>
      Discount: <strong><?= format_currency($discount) ?></strong><br/>
      Tax: <strong><?= format_currency($estimated_tax) ?></strong><br/>
    </p>
  </li>
</ul>
<div class="clear"></div>
  
<div class="compact top_offset">
  <? 
    $bill_info_str = $billing_info->as_string();
    $ship_info_str = $shipping_info->as_string();
    if ($bill_info_str):
  ?>
    <? if ($billing_info->equals($shipping_info)): ?>    
      <h4>Bill and ship to</h4>
      <p><?= h($bill_info_str) ?></p>
    <? else: ?>
      <h4>Bill to</h4>
      <p><?= h($bill_info_str) ?></p>
    <? endif ?>
  <? endif ?>  

  <? 
  if (!$billing_info->equals($shipping_info)):
  ?>
  <h4>Ship to</h4>
  <p><?= h($ship_info_str) ?></p>
  <? endif ?>
  
  <? if ($shipping_method->id): ?>
  <h4>Shipping method</h4>
  <p><?= h($shipping_method->name) ?> - <strong><?= format_currency($shipping_method->quote) ?></strong></p>
  <? endif ?>

  <? if ($payment_method->id): ?>
  <h4>Payment method</h4>
  <p><?= h($payment_method->name) ?></p>
  <? endif ?>  
</div>