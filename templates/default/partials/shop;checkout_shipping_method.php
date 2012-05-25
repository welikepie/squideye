<h3>Shipping Method</h3>

<? if (count($shipping_options)): ?>
  <p>Please select shipping option.</p>
                
  <ul class="form">
    <? foreach ($shipping_options as $option): ?>
        <? if ($option->multi_option): ?>
            <h4 class="list_header"><?= h($option->name) ?></h4>
            <? if ($option->description): ?>
                <p><?= h($option->description) ?></p>
            <? endif ?>              
 
            <ul>
            <? foreach ($option->sub_options as $sub_option): ?>
                <li class="field checkbox">
                <div><input <?= radio_state($option->id == $shipping_method->id && $sub_option->id == $shipping_method->sub_option_id) ?>
                  id="<?= 'option'.$sub_option->id ?>" type="radio" name="shipping_option" value="<?= $sub_option->id ?>"/></div>
                <label for="<?= 'option'.$sub_option->id ?>">
                  <?= h($sub_option->name) ?> - <strong><?= !$sub_option->is_free ? format_currency($sub_option->quote) : 'free' ?></strong>
                </label>
                </li>
            <? endforeach ?>
            </ul>
        <? else: ?>
            <li class="field checkbox">
              <div><input <?= radio_state($option->id == $shipping_method->id) ?> id="<?= 'option'.$option->id ?>" type="radio" name="shipping_option" value="<?= $option->id ?>"/></div>
              <label for="<?= 'option'.$option->id ?>">
                <?= h($option->name) ?> - <strong><?= !$option->is_free ? format_currency($option->quote) : 'free' ?></strong>
                <? if ($option->description): ?>
                    <span class="comment"><?= h($option->description) ?></span>
                <? endif ?>
              </label>
            </li>
        <? endif ?>
    <? endforeach ?>
  </ul>
  
  <div class="clear"></div>
  <input type="hidden" name="checkout_step" value="<?= $checkout_step ?>"/>
  <input type="image" src="<?= theme_resource_url('images/btn_next.gif') ?>" alt="Next" onclick="return $(this).getForm().sendRequest('on_action', {update:{'checkout_page': 'checkout_partial'}})"/>
<? else: ?>
  <p>There are no shipping options available for your location. Please contact our sales department: <a href="mailto:sales@mystoredotcom">sales@mystoredotcom</a>.</p>
<? endif ?>