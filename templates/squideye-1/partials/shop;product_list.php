<? 

// Normalise partial paramters
if (isset($list_class)) { $list_class = ' class="product-list ' . $list_class . '"'; } else { $list_class = ' class="product-list"'; }
if (isset($item_class)) { $item_class = ' class="' . $item_class . '"'; } else { $item_class = ''; }

// Pagination setup
if (isset($paginate) && $paginate) {
	$page_index = isset($page_index) && ($page_index >= 1) ? $page_index - 1 : 0;
	$records_per_page = isset($records_per_page) && ($records_per_page > 0) ? $records_per_page : 20;
	$pagination = $products->paginate($page_index, $records_per_page);
} else {
	$pagination = null;
}
$products = $products instanceof Db_ActiveRecord ? $products->find_all() : $products;

if (!$products->count) { ?>
	<p>There are no products in this category.</p>
<? } else { ?>
	<ul<?= $list_class ?>>
	<? foreach ($products as $product) { 
	
		$image_url = $product->image_url(0, 160, 'auto');
		if ($image_url) { $image_url = 'src="' . $image_url . '"'; } else { $image_url = ''; }
	?>
		<li<?= $item_class ?>>
			<a href="<?= $product->page_url('/product') ?>"><img <?= $image_url ?> alt="" class="thumbnail"></a>
			<div class="details">
				<h2><?= h($product->name) ?></h2>
				<p><?= h($product->short_description) ?></p>
				<a href="<?= $product->page_url('/product') ?>">See more</a>
			</div>
		</li>
	<? } ?>
	</ul>
<? }

if ($pagination) {
	$this->render_partial('page:pagination', array('pagination' => $pagination, 'base_url' => $pagination_base_url));
} ?>