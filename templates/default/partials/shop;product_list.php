<ul class="product_list">
<?= open_form() ?>
    <? 
      if (isset($paginate) && $paginate)
      {
        $page_index = isset($page_index) ? $page_index-1 : 0;
        $records_per_page = isset($records_per_page) ? $records_per_page : 3;
        $pagination = $products->paginate($page_index, $records_per_page);
      }
      else 
        $pagination = null;
    
      $products = $products instanceof Db_ActiveRecord ? $products->find_all() : $products;
      foreach ($products as $product): 
        $is_discounted = $product->is_discounted();
      ?>
      <li>
        <? 
          $image_url = $product->image_url(0, 130, 130);
          if ($image_url):
        ?>
          <div class="image"><a href="<?= $product->page_url('/product') ?>"><img src="<?= $image_url ?>" alt="<?= h($product->name) ?>"/></a></div>
        <? endif ?>
        
        <div class="info">
          <h4><a href="<?=  $product->page_url('/product') ?>"><?= h($product->name) ?></a></h4>
          <p>
            <?= h($product->short_description) ?>
            <br/>Price: <strong class="<?= $is_discounted ? 'old_price' : null ?>"><?= format_currency($product->price()) ?></strong>
            <? if ($is_discounted): ?>
            <br/>Sale Price: <strong class="sale_price"><?= format_currency($product->get_discounted_price(1)) ?></strong>
            <? endif ?>
          </p>
          <p>
            <a href="<?= $product->page_url('/product') ?>">Read more...</a><br/>
            <a href="#" onclick="return $(this).getForm().sendRequest('shop:on_addToCompare', {
              onSuccess: function(){alert('The product has been added to the compare list')},
              extraFields: {product_id: '<?= $product->id ?>'},
              update: {compare_list: 'shop:compare_list'}
            });">Add to compare</a>
          </p>
        </div>
        <? if ($product->on_sale || $is_discounted): ?>
          <div class="offer">Offer!</div>
        <? endif ?>
        <div class="clear"></div>
      </li>
    <? endforeach ?>
  </ul>
  
  <? 
    if ($pagination)
      $this->render_partial('pagination', array('pagination'=>$pagination, 'base_url'=>$pagination_base_url));
  ?>
</form>
