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
  
    <p>Please deposit the correct amount of money for your order into the following bank account via direct bank transfer.</p>

    <p>Bank BIC Code (SWIFT Code): XXXXXXX<br/>
  Bank IBAN: XXXXXXX</p>

    <p>After depositing the funds into this account, please email us with the following details:</p>

     <p>- Receipt of your payment transaction<br/>
   - Your full name, delivery address, phone no, email address<br/>
   - Detail of the products you have ordered (quantity ordered of each item and price paid per item)</p>

     <p>Once we have received your payment, we will ship your product and email you notification that this has occurred.</p>
  <? endif ?>
<? endif ?>

<p><a href="<?= root_url('/') ?>"><img src="<?= root_url("/resources/images/btn_continue_shopping.gif") ?>" alt="Continue shopping"/></a></p>