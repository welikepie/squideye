<h1><?= h($product->name) ?></h1>
<?
	$temp = array();
	foreach ($product->category_list as $category) { $temp[] = '<a href="' . $category->page_url('/category') . '">' . h($category->name) . '</a>'; }
	if ($temp) {
		?><h2>Categories: <?= implode(' | ', $temp) ?></h2><?
	} unset($temp);
?>
<?= flash_message() ?>

<?= open_form() ?>
	<img src="<?= $product->images[0]->getThumbnailPath(300, 'auto') ?>" alt="">
	<div class="details">
		<div class="description"><?= ($product->description ? $product->description : ($product->short_description ? h($product->short_description) : "")) ?></div>
		<div class="sizing">[THIS BIT WILL DE COMPLETED ONCE IT'S BEEN DECIDED WHAT TO DO ABOUT SIZING]</div>
		<div class="pricing">
			<p>Price: <span><?= format_currency($product->price()) ?></span></p>
		</div>
		<button type="submit" name="add_to_cart">Add to Cart</button>
	</div>
</form>
<script type="text/javascript">
	$('div#product-page form').submit(function (ev) {
		ev.preventDefault();
		$(this).sendRequest('shop:on_addToCart', {update: {'mini-cart-count': 'shop:cart_count', 'product-page': 'shop:product_partial'}});
	});
</script>