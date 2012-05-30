<? if (!$category) { ?>
<p>The specified category does not exist.</p>
<? } else { ?>
<h1><?= h($category->name) ?></h1>
<? if ($category->description) { ?><p class="description"><?= $category->description ?></p><? }
   elseif ($category->short_description) { ?><p class="description"><?= h($category->short_description) ?></p><? }
} ?>

<? $this->render_partial('shop:product_list', array(
	'products' => $category->list_products(),
	'paginate' => true,
	'records_per_page' => 1,
	'pagination_base_url' => $category->page_url('category'),
	'page_index' => $this->request_param(-1, 0),
	'list_class' => 'main-listing'
));