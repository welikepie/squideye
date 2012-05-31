<div id="product-page">
	<? if ($product_unavailable): ?>
		<h2>We are sorry, product is unavailable.</h2>
	<? elseif(!$product): ?>
		<h2>Product not found.</h2>
	<? else: ?>
		<? $this->render_partial('shop:product_partial', array('product' => $product)) ?>
	<? endif ?>
</div>