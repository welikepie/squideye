<h3>Shipping Information</h3>

<p>Copy <a href="#" onclick="return $(this).getForm().sendRequest('shop:on_copyBillingInfo', {update:{'checkout_page': 'checkout_partial'}})">billing information</a>.<p>

<ul class="form">
  <li class="field text left">
    <label for="first_name">First Name</label>
    <div><input autocomplete="off" id="first_name" name="first_name" type="text" class="text" value="<?= h($shipping_info->first_name) ?>"/></div>
  </li>
  <li class="field text right">
    <label for="last_name">Last Name</label>
    <div><input autocomplete="off" id="last_name" name="last_name" type="text" class="text" value="<?= h($shipping_info->last_name) ?>"/></div>
  </li>
    
  <li class="field text left">
    <label for="company">Company</label>
    <div><input autocomplete="off" id="company" type="text" value="<?= h($shipping_info->company) ?>" class="text" name="company"/></div>
  </li>
  <li class="field text right">
    <label for="phone">Phone</label>
    <div><input autocomplete="off" id="phone" type="text" class="text" value="<?= h($shipping_info->phone) ?>" name="phone"/></div>
  </li>

  <li class="field text">
    <label for="street_address">Street Address</label>
    <div><textarea rows="2" id="street_address" name="street_address"><?= h($shipping_info->street_address) ?></textarea></div>
  </li>

  <li class="field text left">
    <label for="city">City</label>
    <div><input autocomplete="off" id="city" type="text" class="text" name="city" value="<?= h($shipping_info->city) ?>"/></div>
  </li>
  <li class="field text right">
    <label for="zip">Zip/Postal Code</label>
    <div><input autocomplete="off" id="zip" type="text" class="text" name="zip" value="<?= h($shipping_info->zip) ?>"/></div>
  </li>

  <li class="field select left">
    <label for="country">Country</label>
    <select autocomplete="off" id="country" name="country" onchange="return $('country').getForm().sendRequest('shop:on_updateStateList', {
        extraFields: {'country': $('country').get('value'), 'control_name': 'state', 'control_id': 'state', 'current_state': '<?= $shipping_info->state ?>'},
        update: {'shipping_states': 'shop:state_selector'}
      })">
      <? foreach ($countries as $country): ?>
      <option <?= option_state($shipping_info->country, $country->id) ?> value="<?= h($country->id) ?>"><?= h($country->name) ?></option>
      <? endforeach ?>
    </select>
  </li>

  <li class="field select right">
    <label for="state">State</label>
    <div id="shipping_states">
    <?= $this->render_partial('shop:state_selector', array('states'=>$states, 'control_id'=>'state', 'control_name'=>'state', 'current_state'=>$shipping_info->state)) ?>
    </div>
  </li>
</ul>
<div class="clear"></div>
<input type="hidden" name="checkout_step" value="<?= $checkout_step ?>"/>
<input type="image" src="<?= theme_resource_url('images/btn_next.gif') ?>" alt="Next" onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}})"/>