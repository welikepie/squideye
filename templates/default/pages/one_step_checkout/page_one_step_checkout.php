<h2>One-Step Check Out</h2>

<p>This is an example of the one-step checkout implementation. This implementation is suitable for local stores, when customers country and state are known. Also the implementation does not offer to choose payment and shipping methods and automatically assigns specific methods to all new orders</p>

<div class="scoreboard">
  <ul>
    <li>
      <h3>Cart total</h3>
      <p><?= format_currency($cart_total) ?></p>
    </li>
  
    <li>
      <h3>Discount</h3>
      <p><?= format_currency($discount) ?></p>
    </li>
    
    <li>
      <h3>Tax</h3>
      <p><?= format_currency($estimated_tax) ?></p>
    </li>
    <li class="last">
      <h3>Estimated Total</h3>
      <p><?= format_currency($estimated_total) ?></p>
    </li>
  </ul>
  <div class="clear"></div>
</div>

<? if (Shop_Cart::get_item_total_num() != 0): ?>
  <div class="checkout_columns">
    <?= open_form() ?>
      <? if ($this->customer): ?>
        <p>Bill to: <strong><?= h($this->customer->name) ?>, <?= $this->customer->email ?></strong>.</p>
      <? endif ?>
      
      <ul class="form">
        <? if (!$this->customer): ?>
          <li class="field text left">
            <label for="first_name">First Name</label>
            <div><input name="first_name" value="<?= h($billing_info->first_name) ?>" id="first_name" type="text" class="text"/></div>
          </li>
          <li class="field text right">
            <label for="last_name">Last Name</label>
            <div><input name="last_name" value="<?= h($billing_info->last_name) ?>" id="last_name" type="text" class="text"/></div>
          </li>
          <li class="field text">
            <label for="email">Email</label>
            <div><input id="email" name="email" value="<?= h($billing_info->email) ?>" type="text" class="text"/></div>
          </li>
        <? endif ?>
      
        <li class="field text left">
          <label for="company">Company</label>
          <div><input id="company" type="text" value="<?= h($billing_info->company) ?>" name="company" class="text"/></div>
        </li>
        <li class="field text right">
          <label for="phone">Phone</label>
          <div><input id="phone" type="text" class="text" value="<?= h($billing_info->phone) ?>" name="phone"/></div>
        </li>
      
        <li class="field text">
          <label for="street_address">Street Address</label>
          <div><input id="street_address" name="street_address" type="text" class="text" value="<?= h($billing_info->street_address) ?>"/></div>
        </li>
      
        <li class="field text left">
          <label for="city">City</label>
          <div><input id="city" type="text" class="text" name="city" value="<?= h($billing_info->city) ?>"/></div>
        </li>
        <li class="field text right">
          <label for="zip">Zip/Postal Code</label>
          <div><input id="zip" type="text" class="text" name="zip" value="<?= h($billing_info->zip) ?>"/></div>
        </li>
      </ul>
      
      <input type="hidden" name="country" value="<?= Shop_Country::create()->find_by_code('US')->id ?>"/>
      <input type="hidden" name="state" value="<?= Shop_CountryState::create()->find_by_code('CA')->id ?>"/>
      
      <div class="clear"></div>
      <input type="image" src="<?= theme_resource_url('images/btn_order_review.gif') ?>" alt="Next" onclick="return $(this).getForm().sendRequest('create_local_order')"/>
    </form>
  </div>
  <div class="clear"></div>
<? else: ?>
  <p>Your shopping cart is empty.</p>
  <p><a href="<?= root_url('/') ?>"><img src="<?= theme_resource_url('images/btn_continue_shopping.gif') ?>" alt="Continue shopping"/></a></p>
<? endif ?>