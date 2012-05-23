<h3>Order Review</h3>

<?
  $bill_to_str = $billing_info->as_string();
  $items = Shop_Cart::list_active_items();
?>

<p><strong>Bill and ship to:</strong> <?= h($bill_to_str) ?></p>

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
              <?= format_currency($option->price) ?>
            <? endforeach ?>
          <? endif ?>
        </div>
      </td>
      <td class="right"><?= $item->quantity ?></td>
      <td class="right"><?= format_currency($item->single_price()) ?></td>
      <td class="right"><?= format_currency($item->discount()) ?></td>
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
  <tr>
    <td>Goods tax: </td>
    <th class="last"><?= format_currency($goods_tax) ?></th>
  </tr>
  <tr>
    <td>Shipping: </td>
    <th class="last"><?= format_currency($shipping_quote) ?></th>
  </tr>
  <? if ($shipping_tax): ?>
  <tr>
    <td>Shipping tax: </td>
    <th class="last"><?= format_currency($shipping_tax) ?></th>
  </tr>
  <? endif ?>
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

<?= open_form() ?>
  <p class="right">
    <a class="right_offset" href="<?= root_url('/one_step_checkout') ?>"><img src="<?= theme_resource_url('images/btn_back.gif') ?>" title="Return to the previous page"/></a>
    <input type="image" src="<?= theme_resource_url('images/btn_place_order.gif') ?>" alt="Place Order and Pay" onclick="return $(this).getForm().sendRequest('shop:on_checkoutPlaceOrder')"/>
  </p>
</form>