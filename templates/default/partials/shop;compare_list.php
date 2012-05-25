<?= open_form() ?>
  <?
    $compare_product_list = Shop_ComparisonList::list_products();
    if ($compare_product_list->count):
  ?>
  <ul>
    <? foreach ($compare_product_list as $product): ?>
    <li>
      <a class="product_link" href="<?= $product->page_url('product') ?>"><?= h($product->name) ?></a>
      <a class="remove" href="#" title="Remove product" onclick="$(this).getForm().sendRequest('shop:on_removeFromCompare', {
    extraFields: {product_id: '<?= $product->id ?>'},
    update: {compare_list: 'shop:compare_list'}
  }); return false">Remove</a>
      <div class="clear"></div>
    </li>
    <? endforeach ?>
  </ul>
  
  <div class="compare_products_controls">
    <a class="button" href="<?= root_url('compare') ?>"><img src="<?= theme_resource_url('/images/btn_compare.gif') ?>" alt="Compare"/></a>
    <p>or <a href="#" onclick="return $(this).getForm().sendRequest('shop:on_clearCompareList', {
      confirm: 'Do you really want to remove all products from the compare list?',
      update: {compare_list: 'shop:compare_list'}
    });">clear list</a></p>
  </div>
  <? else: ?>
    <p>The product compare list is empty.</p>
  <? endif ?>
</form>
