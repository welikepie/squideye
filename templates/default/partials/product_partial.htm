<?= open_form() ?>
  <h2><?= $product->name ?></h2>
  
  <p>Category: <a href="<?= $product->category_list[0]->page_url('/category') ?>"><?= h($product->category_list[0]->name) ?></a></p>

  <?= flash_message() ?>
  
  <div class="product_details">
      
    <? $this->render_partial('shop:image_slider', array('images'=>$product->images)) ?>
    
    <div class="info">
      <?= $product->description ?>
      
      <? $this->render_partial('shop:product_rating_info') ?>      
      <? $this->render_partial('shop:grouped_products') ?>
      <? $this->render_partial('shop:product_attributes') ?>
      <? $this->render_partial('shop:product_options') ?>
      
      <? if ($product->track_inventory && $product->in_stock > 0): ?>
          <p>Number of items in stock: <strong><?= $product->in_stock ?></strong></p>
      <? endif ?>
      
      <? if ($product->is_out_of_stock()): ?>
          <p>
            <strong>This product is temporarily unavailable</strong>
            <? if ($product->expected_availability_date): ?>  
                <br/>The expected availability date is <strong><?= $product->displayField('expected_availability_date') ?></strong>
            <? endif ?>
          </p>
      <? endif ?>
      
      <p>
        <a href="#" onclick="return $(this).getForm().sendRequest('shop:on_addToCompare', {
              onSuccess: function(){alert('The product has been added to the compare list')},
              extraFields: {product_id: '<?= $product->id ?>'},
              update: {compare_list: 'shop:compare_list'}
            });">Add to compare</a>
      </p>
      
      <p class="price">
        <?
            $is_discounted = $product->is_discounted();
        ?>
        
        Price: <span class="<?= $is_discounted ? 'old_price' : null ?>"><?= format_currency($product->price()) ?></span>
        <? if ($is_discounted): ?>
        <br/>Sale price: <span class="sale_price"><?= format_currency($product->get_discounted_price(1)) ?></span>
        <? endif ?>
      </p>
      
      <? $this->render_partial('shop:product_extra_options') ?>
      
      <? if (!$product->is_out_of_stock()): ?>
      <input onclick="return $(this).getForm().sendRequest('shop:on_addToCart', {update: {'mini_cart': 'shop:mini_cart', 'product_page': 'product_partial'}})" type="image" name="add_to_cart" class="add_to_cart" src="<?= theme_resource_url('images/btn_add_to_cart.gif') ?>" alt="Add to cart"/>
      
      <? endif ?>
    </div>
  </div>
  <div class="clear"></div>
  
  <? $this->render_partial('shop:product_reviews') ?>
  <? $this->render_partial('shop:add_review_form') ?>
</form>

<? $this->render_partial('shop:related_products') ?>
