<?  
  if ( !isset($category_url_name) )
    $category_url_name = null;

  $categories = isset($parent_category) ? 
    $parent_category->list_children('front_end_sort_order') : 
    Shop_Category::create()->list_root_children('front_end_sort_order');

  if ($categories->count): ?>
  <ul>
<?    foreach ($categories as $category):  ?>
    <li <? if ($category_url_name == $category->url_name): ?>class="current"<? endif ?>>
      <a href="<?= $category->page_url('/category') ?>"><?= $category->name ?></a>
      <? $this->render_partial('shop:categories', array('parent_category'=>$category)) ?>
    </li>
<?     endforeach; ?>
  </ul>
<?  endif ?>