<h2>Compare Products</h2>

<?= open_form() ?>
  <? if (!$products->count): ?>
    <p>The product compare list is empty</p>
  <? else: ?>
    <table class="product_compare_table">
      <thead>
        <tr>
          <td>&nbsp;</td>
          <? foreach ($products as $product): ?>
            <td class="product">
              <? if ($image_url = $product->image_url(0, 130, 130)): ?>
                <img src="<?= $image_url ?>" alt="<?= h($product->name) ?>"/>
              <? endif ?>
              
              <h3><a href="<?= $product->page_url('product') ?>"><?= h($product->name) ?></a></h3>
            </td>
          <? endforeach ?>
        </tr>
      </thead>
      <tbody>
        <tr class="<?= zebra('compare') ?>">
          <th>Price</th>
          <? foreach ($products as $product):
              $is_discounted = $product->is_discounted();
          ?>
            <td class="product">
              <strong class="<?= $is_discounted ? 'old_price' : null ?>">
                <?= format_currency($product->price()) ?>
              </strong>
              <? if ($is_discounted): ?>
                <strong class="sale_price">
                  <?= format_currency($product->get_discounted_price(1)) ?>
                </strong>
              <? endif ?>
            </td>
          <? endforeach ?>
        </tr>
        <tr class="<?= zebra('compare') ?>">
          <th>Description</th>
          <? foreach ($products as $product): ?>
           <td class="product"><?= $product->description ?></td>
          <? endforeach ?>
        </tr>
        <tr class="<?= zebra('compare') ?>">
          <th>Manufacturer</th>
          <? foreach ($products as $product): ?>
            <td class="product"><?= $product->manufacturer ? h($product->manufacturer->name) : null ?></td>
          <? endforeach ?>
        </tr>
        <? foreach ($attributes as $attribute): ?>
          <tr class="<?= zebra('compare') ?>">
            <th><?= h($attribute) ?></th>
            <? foreach ($products as $product): ?>
              <td class="product"><?= h($product->get_attribute($attribute)) ?></td>
            <? endforeach ?>
          </tr>
        <? endforeach ?>
        <tr>
          <td>&nbsp;</td>
          <? foreach ($products as $product): ?>
          <td class="product"><input onclick="return $(this).getForm().sendRequest('shop:on_addToCart', {
            extraFields: {product_id: '<?= $product->id ?>'}, 
            onSuccess: function(){alert('The product has been added to the cart')}, 
            update: {'mini_cart': 'shop:mini_cart'}})" 
            type="image" name="add_to_cart" class="add_to_cart" src="<?= theme_resource_url('images/btn_add_to_cart.gif') ?>" alt="Add to cart"/>
            </td>
          <? endforeach ?>
        </tr>
      </tbody>
    </table>
  <? endif ?>
</form>