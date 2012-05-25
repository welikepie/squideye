<div id="product_page">
	<? if ($product_unavailable): ?>
		<h2>We are sorry, product unavailable.</h2>
	<? elseif(!$product): ?>
		<h2>We are sorry, product not found.</h2>
	<? else: ?>
		<? $this->render_partial('product_partial') ?>
	<? endif ?>
</div>