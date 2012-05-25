<h2>Order</h2>

<? if (!$order): ?>
  <h3>Order not found</h3>
<? else: ?>
  <div class="bottom_offset">
    <ul class="scoreboard">
      <li>
        <h3>Order</h3>
        <p># <?= $order->id ?></p>
      </li>
      <li>
        <h3>Order Date</h3>
        <p><?= h($order->order_datetime->format('%x')) ?></p>
      </li>
      <li>
        <h3>Total </h3>
        <p><?= format_currency($order->total) ?></p>
      </li>
      <li class="last">
        <h3>Status</h3>
        <p><?= h($order->status->name) ?></p>
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
          $image_url = $item->product->image_url(0, 60, 'auto');
      ?>
      <tr class="<?= $image_url ? 'image' : null ?>">
        <td>
          <? if ($image_url): ?>
            <img class="product_image" src="<?= $image_url ?>" alt="Mac mini"/>
          <? endif ?>
          <div class="product_description">
            <?= $item->output_product_name() ?>
            
            <? if ($item->product->product_type->files && $order->is_paid() && $item->product->files->count): ?>
              <div class="product_files">
                Download:
                <ul class="file_list">
                <? foreach ($item->product->files as $file): ?>
                  <li><a href="<?= $file->download_url($order) ?>"><?= h($file->name) ?></a> (<?= $file->size_str ?>)</li>
                <? endforeach ?>
                </ul>
              </div>
            <? endif ?>
          </div>
        </td>
        <td class="right"><?= format_currency($item->single_price) ?></td>
        <td class="right"><?= format_currency($item->discount) ?></td>
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
      <th class="last"><?= format_currency($order->discount) ?></th>
    </tr>
    <tr>
      <td>Goods tax: </td>
      <th class="last"><?= format_currency($order->goods_tax) ?></th>
    </tr>
      <tr>
      <td>Shipping: </td>
      <th class="last"><?= format_currency($order->shipping_quote) ?></th>
    </tr>
    <? if ($order->shipping_tax): ?>
    <tr>
      <td>Shipping tax: </td>
      <th class="last"><?= format_currency($order->shipping_tax) ?></th>
    </tr>
    <? endif ?>
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
  
  <p>
    <a class="right_offset" href="<?= root_url('/orders') ?>"><img src="<?= theme_resource_url('images/btn_return_to_orders.gif') ?>" alt="Return to the order list"/></a>
    <? if($order->payment_method->has_payment_form() && !$order->payment_processed()): ?>
      <a class="right_offset" href="<?= root_url('/pay/'.$order->order_hash) ?>"><img src="<?= theme_resource_url('images/btn_pay.gif') ?>" alt="Pay"/></a>
    <? endif ?>
  </p>
<? endif ?>