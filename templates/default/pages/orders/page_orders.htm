<h2>My Orders</h2>

<? if (!$orders): ?>
  <p>Orders not found</p>
<? else: ?>
  
  <p>Click an order for details.</p>
  
  <table class="simple_table heavy">
    <thead>
      <tr>
        <th class="order_status"></th>
        <th>#</th>
        <th>Date</th>
        <th>Status</th>
        <th class="right last">Total</th>
      </tr>
    </thead>
    <tbody>
      <? if (!$orders->count): ?>
      <tr class="nodata">
        <td colspan="5">No orders found</td>
      </tr>
      <? endif ?>
      <? foreach ($orders as $order): 
        $url = root_url('/order/'.$order->id);
      ?>
      <tr class="<?= zebra('order') ?>">
        <td class="order_status">
          <span title="<?= h($order->status->name) ?>" style="background-color: <?= $order->color ?>">&nbsp;</span>
        </td>
        <td><a href="<?= $url ?>"><?= $order->id ?></a></td>
        <td><a href="<?= $url ?>"><?= $order->order_datetime->format('%x') ?></a></td>
        <td><a href="<?= $url ?>"><strong><?= h($order->status->name) ?></strong> since <?= $order->status_update_datetime->format('%x') ?></a></td>
        <th class="right last"><a href="<?= $url ?>"><?= format_currency($order->total) ?></a></th>
      </tr>
      <? endforeach ?>
    </tbody>
  </table>
<? endif ?>