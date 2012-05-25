<? $postponed = isset($postponed) ? $postponed : null ?>

<table class="simple_table">
  <thead>
    <tr>
      <th>Cart Items</th>
      <th class="right"><? if (!$postponed): ?>Postpone<? else: ?>Postponed<? endif ?></th>
      <th class="right">Quantity</th>
      <th class="right">Price</th>
      <th class="right">Discount</th>
      <th class="right last">Total</th>
    </tr>
  </thead>
  <tbody>
    <? if (!count($items)): ?>
    <tr class="nodata">
      <td colspan="5">Your cart is empty.</td>
    </tr>    
    <? else: ?>
      <? 
        foreach ($items as $item): 
        $image_url = $item->product->image_url(0, 60, 'auto');
        $options_str = $item->options_str();
      ?>
      <tr class="<?= $image_url ? 'image' : null ?>">
        <td>
          <? if ($image_url): ?>
          <img class="product_image" src="<?= $image_url ?>" alt="<?= h($item->product->name) ?>"/>
          <? endif ?>
          <div class="product_description">
            <strong><?= h($item->product->name) ?></strong>
            <? if (strlen($options_str)): ?>
              <br/><?= h($options_str) ?>.
            <? endif ?>
            <? if ($item->extra_options): ?>
              <? foreach ($item->extra_options as $option): ?>
                <br/>
                + <?= h($option->description) ?>:
                <?= format_currency($option->get_price($item->product)) ?>
              <? endforeach ?>
            <? endif ?>
          </div>
        </td>
        <td class="right">
          <input type="hidden" name="item_postponed[<?= $item->key ?>]" value="0"/>
          <input type="checkbox" class="checkbox" <?= checkbox_state($item->postponed) ?> name="item_postponed[<?= $item->key ?>]" value="1"/>
        </td>
        <td class="right">
          <? if (!$postponed): ?>
            <input type="text" class="short text" name="item_quantity[<?= $item->key ?>]" value="<?= $item->quantity ?>"/>
          <? else: ?>
            <?= $item->quantity ?>
          <? endif ?>
        </td>
        <td class="right"><?= format_currency($item->single_price()) ?></td>
        <td class="right"><?= format_currency($item->total_discount()) ?></td>
        <th class="right cart_control last">
          <div>
            <?= format_currency($item->total_price()) ?>
          
            <a onclick="return $(this).getForm().sendRequest(
              'shop:on_deleteCartItem', 
              {update: {'cart_page': 'cart_partial', 'mini_cart': 'shop:mini_cart'},
              confirm: 'Do you really want to remove this item from cart?',
              extraFields: {key: '<?= $item->key ?>'}})" title="Remove item" href="#"><img src="<?= theme_resource_url('images/cart_delete.gif') ?>" alt="Remove item"/></a>
          </div>
        </th>
      </tr>
      <? endforeach ?>
    <? endif ?>
  </tbody>
</table>