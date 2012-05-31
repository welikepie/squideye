<nav>
	<a href="<?= root_url('/') ?>" rel="home">Home</a>
	<a href="<?= root_url('/store') ?>">Store</a>
	<a href="<?= root_url('/search') ?>" rel="search">Search</a>
	<a href="<?= root_url('/contact') ?>" rel="author">Contact</a>
	<a href="<?= root_url('/cart') ?>">Cart (<span id="mini-cart-count"><? $this->render_partial('shop:cart_count') ?></span>)</a>
</nav>