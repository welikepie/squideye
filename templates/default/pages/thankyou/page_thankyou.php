<h2>Thank you!</h2>

<p>Thank you for the order!</p>

<h3>Order Review</h3>

<div class="bottom_offset">
  <ul class="scoreboard">
    <li>
      <h3>Order</h3>
      <p># <?= $order->id ?></p>
    </li>
    <li class="last">
      <h3>Order Date</h3>
      <p><?= h($order->order_datetime->format('%x')) ?></p>
    </li>
  </ul>
  <div class="clear"></div>
</div>

<table class="simple_table">
  <thead>
    <tr>
      <th>Items</th>
      <th class="right">Price</th>
      <th class="right">Discount</th>
      <th class="right">Quantity</th>
      <th class="right last">Total</th>
    </tr>
  </thead>
  <tbody>
    <? 
      foreach ($items as $item): 
    ?>
    <tr>
      <td>
        <div class="product_description">
          <?= $item->output_product_name() ?>
        </div>
      </td>
      <td class="right"><?= format_currency($item->single_price) ?></td>
      <td class="right">-<?= format_currency($item->discount) ?></td>
      <td class="right"><?= $item->quantity ?></td>
      <th class="right last">
        <div>
          <?= format_currency($item->subtotal) ?>
        </div>
      </th>
    </tr>
    <? endforeach ?>
  </tbody>
</table>

<table class="simple_table totals">
  <tr>
    <td>Subtotal: </td>
    <th class="last"><?= format_currency($order->subtotal) ?></th>
  </tr>
  <tr>
    <td>Discount: </td>
    <th class="last">-<?= format_currency($order->discount) ?></th>
  </tr>
  <? foreach ($order->list_item_taxes() as $tax): ?>
    <tr>
      <td>Sales tax (<?= ($tax->name) ?>): </td>
      <th class="last"><?= format_currency($tax->total) ?></th>
    </tr>
  <? endforeach ?>  
  <tr>
    <td>Shipping: </td>
    <th class="last"><?= format_currency($order->shipping_quote) ?></th>
  </tr>
  <? foreach ($order->list_shipping_taxes() as $tax): ?>
    <tr>
      <td>Shipping tax (<?= ($tax->name) ?>): </td>
      <th class="last"><?= format_currency($tax->total) ?></th>
    </tr>
  <? endforeach ?>  
</table>
<div class="clear"></div>

<div class="float_right bottom_offset">
  <ul class="scoreboard right">
    <li class="last">
      <h3>Total</h3>
      <p><?= format_currency($order->total) ?></p>
    </li>
  </ul>
</div>

<div class="clear"></div>

<p class="no_print"><a href="<?= root_url('/') ?>"><img src="<?= theme_resource_url('images/btn_continue_shopping.gif') ?>" alt="Continue shopping"/></a></p>