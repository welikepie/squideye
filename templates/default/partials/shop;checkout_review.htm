<h3>Order Review</h3>

<?
  $bill_to_str = $billing_info->as_string();
  $ship_to_str = $shipping_info->as_string();
  $items = Shop_Cart::list_active_items();
?>

<table class="simple_table">
  <thead>
    <tr>
      <th>Cart Items</th>
      <th class="right">Quantity</th>
      <th class="right">Price</th>
      <th class="right">Discount</th>
      <th class="right last">Total</th>
    </tr>
  </thead>
  <tbody>
    <? 
      foreach ($items as $item): 
      $options_str = $item->options_str();
    ?>
    <tr>
      <td>
        <div class="product_description">
          <strong><?= h($item->product->name) ?></strong>
          <? if (strlen($options_str)): ?>
            <br/><?= h($options_str) ?>.
          <? endif ?>
          <? if ($item->extra_options): ?>
            <? foreach ($item->extra_options as $option): ?>
              <br/>
              + <?= h($option->description) ?>:
              <?= format_currency($option->get_price($item->product)) ?>
            <? endforeach ?>
          <? endif ?>
        </div>
      </td>
      <td class="right"><?= $item->quantity ?></td>
      <td class="right"><?= format_currency($item->single_price()) ?></td>
      <td class="right"><?= format_currency($item->total_discount()) ?></td>
      <th class="right last">
        <div>
          <?= format_currency($item->total_price()) ?>
        </div>
      </th>
    </tr>
    <? endforeach ?>
  </tbody>
</table>

<table class="simple_table totals">
  <tr>
    <td>Subtotal: </td>
    <th class="last"><?= format_currency($subtotal) ?></th>
  </tr>
  <tr>
    <td>Discount: </td>
    <th class="last"><?= format_currency($discount) ?></th>
  </tr>
  <? foreach ($product_taxes as $tax): ?>
    <tr>
      <td>Sales tax (<?= ($tax->name) ?>): </td>
      <th class="last"><?= format_currency($tax->total) ?></th>
    </tr>
  <? endforeach ?>
  <tr>
    <td>Shipping: </td>
    <th class="last"><?= format_currency($shipping_quote) ?></th>
  </tr>
  <? foreach ($shipping_taxes as $tax): ?>
    <tr>
      <td>Shipping tax (<?= ($tax->name) ?>): </td>
      <th class="last"><?= format_currency($tax->rate) ?></th>
    </tr>
  <? endforeach ?>
</table>
<div class="clear"></div>

<div class="float_right bottom_offset">
  <ul class="scoreboard right">
    <li class="last">
      <h3>Total</h3>
      <p><?= format_currency($total) ?></p>
    </li>
  </ul>
</div>

<div class="clear"></div>

<input type="hidden" name="checkout_step" value="<?= $checkout_step ?>"/>
<p class="right">
  <input type="image" src="<?= theme_resource_url('images/btn_place_order.gif') ?>" alt="Place Order and Pay" onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}})"/>
</p>