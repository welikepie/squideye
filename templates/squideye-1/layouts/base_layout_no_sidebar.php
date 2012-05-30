<!DOCTYPE html>
<html>
	<head>
		<title><?= ($this->page->title ? h($this->page->title) . ' :: ' : '') ?>Squideye</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge;chrome=1">
		<meta name="Description" content="<?= h($this->page->description) ?>">
		<meta name="Keywords" content="<?= h($this->page->keywords) ?>">
		
		<!-- Stylesheets -->
		<?= $this->css_combine(array(
			'ls_styles',
			'@css/reset.css',
			'@css/styles.css',
		)) ?>
		
		<!-- Scripts -->
		<?= $this->js_combine(array(
			'@scripts/jquery-1.7.2.min.js',
			'ls_core_jquery',
		)) ?>
		
		<!-- Additional <head> declarations -->
		<? $this->render_head(); ?>
	</head>
	<body class="full-width">
		<!--
			Page Header
		    -------------------------
			The logo of the page (also a link to homepage), the basic navigation,
			as well as links to both the shopping cart and the logout (or login, if no one's logged in).
		-->
		<header>
			<!-- Site logo -->
			<a href="<?= root_url('/') ?>" rel="home"><img src="<?= theme_resource_url('images/squideye_logo.png') ?>" width="300" height="120" alt="Squideye"></a>
			
			<!-- Account controls -->
			<? $this->render_partial('account_controls'); ?>
		</header>
		
		<!-- Navigation bar -->
		<? $this->render_partial('page:navigation'); ?>
		
		<!--
			Main Content
			-------------------------
			This area is going to hold main content of the site, i.e. single pages,
			catalogues, product descriptions and so on.
		-->
		<section id="main"><? $this->render_page(); ?></section>
		
		<!--
			Sidebar
			-------------------------
			Sidebar can be used to store additional elements related to the current page,
			but not tight enough to be put into the main content area. Additional data,
			related products etc. goes here.
		-->
		<section id="sidebar"></section>
		
		<!--
			Page Footer
			-------------------------
			This area is used to provide copyright info, license links and similar details
			that should be put somewhere on the page, but are rarely of the interest to an
			average visitor.
		-->
		<? $this->render_partial('page:footer'); ?>
	</body>
</html>