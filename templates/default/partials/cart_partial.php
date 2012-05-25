<h2>Cart</h2>
<? 
  $active_items = Shop_Cart::list_active_items();
  $postponed_items = Shop_Cart::list_postponed_items();
?>

<?= flash_message() ?>

<? if ($active_items): ?>
  <?= open_form() ?>
    <? $this->render_partial('shop:cart_table', array('items'=>$active_items)) ?>

    <table class="simple_table totals">
      <tr>
        <td>Cart total: </td>
        <th class="last"><?= format_currency($cart_total) ?></th>
      </tr>
      <tr>
        <td>Discount: </td>
        <th class="last"><?= format_currency($discount) ?></th>
      </tr>
    </table>
    <div class="clear"></div>
    
    <div class="float_right">
      <ul class="scoreboard right">
        <li class="last">
          <h3>Estimated Total</h3>
          <p><?= format_currency($estimated_total) ?></p>
        </li>
      </ul>      
      
      <p class="clear">* Shipping cost and taxes will be evaluated during the checkout process.</p>
    </div>
    <div class="clear"></div>

    <div class="shipping_cost_estimator">
      <div id="estimator_link" class="controls">
        <h4><a href="#" onclick="$('estimator_link').hide(); $('estimator_controls').show(); return false;">Estimate shipping cost</a></h4>
     </div>
      
      <div class="controls hidden" id="estimator_controls">
        <h4>Estimate shipping cost</h4>
        
        <select id="country" name="country" onchange="return $('country').getForm().sendRequest(
        'shop:on_updateStateList', {      
          extraFields: {'country': $('country').get('value'), 
          'control_name': 'state', 
          'control_id': 'state', 
          'current_state': '<?= $shipping_info->state ?>'},
          update: {'shipping_states': 'shop:state_selector'}
        })">
          <? foreach ($countries as $country): ?>
          <option value="<?= $country->id ?>" <?= option_state($country->id, $shipping_info->country) ?>><?= h($country->name) ?></option>
          <? endforeach ?>
        </select>
        
        <span id="shipping_states">
          <?= $this->render_partial('shop:state_selector', array('states'=>$states, 'control_id'=>'state', 'control_name'=>'state', 'current_state'=>$shipping_info->state)) ?>
        </span>
       
        <label>Zip: <input type="text" class="zip" name="zip" value="<?= h($shipping_info->zip) ?>"/></label>
         
        <a href="#" class="submit" onclick="return $(this).getForm().sendRequest(
        'shop:on_evalShippingRate', {update: {'shipping_options': 'estimated_shipping_options'}
        })"><img alt="Submit" src="<?= theme_resource_url('/images/btn_submit.gif') ?>"/></a>
      </div>
        
      <div id="shipping_options"></div>
    </div>
    
    <div class="checkout_block">
      <label for="coupon_code">Do you have a coupon?</label> <input id="coupon_code" type="text" class="coupon_code" name="coupon" value="<?= $coupon_code ?>" onkeydown="if (new Event(event).key == 'enter') $(this).getForm().sendRequest('on_action', {update: {'cart_page': 'cart_partial', 'mini_cart': 'shop:mini_cart'}})"/>
      
      <input type="image" class="checkout_btn" src="<?= theme_resource_url('images/btn_checkout.gif') ?>" alt="Checkout" onclick="return $(this).getForm().sendRequest('shop:on_setCouponCode')"/>

      <input type="image" src="<?= theme_resource_url('images/btn_apply.gif') ?>" alt="Apply Changes" class="apply_btn" onclick="return $(this).getForm().sendRequest('on_action', {update: {'cart_page': 'cart_partial', 'mini_cart': 'shop:mini_cart'}})"/>      
      
      <input type="hidden" name="redirect" value="<?= root_url('/checkout_start') ?>"/>
      <div class="clear"></div>
    </div>
    <div class="clear"></div>
  </form>  
<? else: ?>
  <p>Your cart is empty.</p>
  <p><a href="<?= root_url('/') ?>"><img src="<?= theme_resource_url('images/btn_continue_shopping.gif') ?>" alt="Continue shopping"/></a></p>
<? endif ?>
  
<? if ($postponed_items): ?>
<?= open_form() ?>
  <h3>Postponed items</h3>
  <? $this->render_partial('shop:cart_table', array('items'=>$postponed_items, 'postponed'=>true)) ?>
  
  <p class="right float_right">
    <input type="image" src="<?= theme_resource_url('images/btn_apply.gif') ?>" alt="Apply Changes" onclick="return $(this).getForm().sendRequest('on_action', {update: {'cart_page': 'cart_partial', 'mini_cart': 'shop:mini_cart'}})"/>
  </p>
  <div class="clear"></div>  
</form>
<? endif ?>