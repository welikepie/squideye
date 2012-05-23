<?= open_form() ?>
<p>Product sorting: 
    <?
      /*
       * Save the category product sorting mode if it has been changed.
       */
      if (post('sorting'))
        Cms_VisitorPreferences::set('cat_sorting_'.$category->id, post('sorting'));
  
      /*
       * Load the product sorting mode. The default mode is 'name'.
       */
      $sorting_preferences = Cms_VisitorPreferences::get('cat_sorting_'.$category->id, array('name'));
      
      $selected_option = $sorting_preferences[0];
    ?>      
    <select onchange="$(this).getForm().sendRequest('on_action', {update: {'category_products': 'shop:category'}})" name="sorting[]">
      <option <?= option_state('name', $selected_option) ?> value="name">Name</option>
      <option <?= option_state('price', $selected_option) ?> value="price">Price</option>
    </select>
  </p>
</form>

<? $this->render_partial('shop:product_list', array(
  'products'=>$category->list_products(array(
    'sorting'=>$sorting_preferences
  )),
  'records_per_page'=>6,
  'paginate'=>true, 
  'pagination_base_url'=>$category->page_url('category'),
  'page_index'=>$this->request_param(1, 0)
)) ?>