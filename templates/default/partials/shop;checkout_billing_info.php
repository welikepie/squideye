<h3>Billing Information</h3>

<? if ($this->customer): ?>
  <p>Bill to: <strong><?= h($this->customer->name) ?>, <?= $this->customer->email ?></strong>.</p>
<? endif ?>

<ul class="form">
  <? if (!$this->customer): ?>
    <li class="field text left">
      <label for="first_name">First Name</label>
      <div><input autocomplete="off" name="first_name" value="<?= h($billing_info->first_name) ?>" id="first_name" type="text" class="text"/></div>
    </li>
    <li class="field text right">
      <label for="last_name">Last Name</label>
      <div><input autocomplete="off" name="last_name" value="<?= h($billing_info->last_name) ?>" id="last_name" type="text" class="text"/></div>
    </li>
    <li class="field text">
      <label for="email">Email</label>
      <div><input autocomplete="off" id="email" name="email" value="<?= h($billing_info->email) ?>" type="text" class="text"/></div>
    </li>
    
  <? endif ?>

  <li class="field text left">
    <label for="company">Company</label>
    <div><input autocomplete="off" id="company" type="text" value="<?= h($billing_info->company) ?>" name="company" class="text"/></div>
  </li>
  <li class="field text right">
    <label for="phone">Phone</label>
    <div><input autocomplete="off" id="phone" type="text" class="text" value="<?= h($billing_info->phone) ?>" name="phone"/></div>
  </li>

  <li class="field text">
    <label for="street_address">Street Address</label>
    <div><textarea rows="2" id="street_address" name="street_address"><?= h($billing_info->street_address) ?></textarea></div>
  </li>

  <li class="field text left">
    <label for="city">City</label>
    <div><input autocomplete="off" id="city" type="text" class="text" name="city" value="<?= h($billing_info->city) ?>"/></div>
  </li>
  <li class="field text right">
    <label for="zip">Zip/Postal Code</label>
    <div><input autocomplete="off" id="zip" type="text" class="text" name="zip" value="<?= h($billing_info->zip) ?>"/></div>
  </li>

  <li class="field select left">
    <label for="country">Country</label>
    <select autocomplete="off" id="country" name="country" onchange="return $('country').getForm().sendRequest('shop:on_updateStateList', {
        extraFields: {'country': $('country').get('value'), 'control_name': 'state', 'control_id': 'state', 'current_state': '<?= $billing_info->state ?>'},
        update: {'billing_states': 'shop:state_selector'}
      })">
      <? foreach ($countries as $country): ?>
      <option <?= option_state($billing_info->country, $country->id) ?> value="<?= h($country->id) ?>"><?= h($country->name) ?></option>
      <? endforeach ?>
    </select>
  </li>

  <li class="field select right">
    <label for="state">State</label>
    <div id="billing_states">
    <?= $this->render_partial('shop:state_selector', array('states'=>$states, 'control_id'=>'state', 'control_name'=>'state', 'current_state'=>$billing_info->state)) ?>
    </div>
  </li>
</ul>
<div class="clear"></div>
<input type="hidden" name="checkout_step" value="<?= $checkout_step ?>"/>
<input type="image" src="<?= theme_resource_url('images/btn_next.gif') ?>" alt="Next" onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}})"/>