<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <title><?= h($this->page->title) ?> - My Store</title>
    <meta name="Description" content="<?= h($this->page->description) ?>"/>
    <meta name="Keywords" content="<?= h($this->page->keywords) ?>"/>
    
    <?= $this->js_combine(array(
      'mootools', 
      'ls_core_mootools', 
      '@javascript/mootools_more.js', 
      '@javascript/demo_effects.js')) ?>
 
    <?= $this->css_combine(array(
      'ls_styles',
      '@css/styles.css' )) ?>
    
    <!--[if IE 6]><link rel="stylesheet" type="text/css" href="<?= theme_resource_url('css/ie6.css') ?>" /><![endif]-->
    <!--[if IE]><link rel="stylesheet" type="text/css" href="<?= theme_resource_url('css/ie.css') ?>" /><![endif]-->

  </head>
  <body class="home">
    <div id="wrapper">
      <div class="header">
        <a href="<?= root_url('/') ?>" class="headerLogo"><img src="<?= theme_resource_url('images/lemonstand_logo.png') ?>" alt="LemonStand" /></a>  
        <? $this->render_partial('tabs') ?>
      </div>
      
      <div id="content">
        <div id="left_column">
          <? $this->render_partial('shop:search_form') ?>
          
          <? $this->render_page() ?>
        </div>
        <div id="right_column">
          <h2>Catalog</h2>
          <div class="categories">
            <? $this->render_partial('shop:categories') ?>    
          </div>
          
          <h2>Compare</h2>
          <div class="compare_products" id="compare_list">
            <? $this->render_partial('shop:compare_list') ?>    
          </div>
         </div>
        <div class="clear"></div>
      </div>
      
      <div id="footer">
        <ul>
          <li>Home</li>
          <li class="last"><a href="<?= root_url('/cart') ?>">Cart</a></li>
        </ul>
        <p>Copyright &copy; 2009 by <a href="#">Your Company</a> | Powered by <a href="http://lemonstandapp.com">LemonStand</a> a product by <a href="http://limewheel.com/?utm_source=lemonstand_demo&amp;utm_medium=ls_demo&amp;utm_campaign=LemonStand%2BDemo">Limewheel Creative</a></p>
        <div class="clear"></div>
      </div>
      <p class="copyright">LemonStand and the LemonStand logo are trademarks of Limewheel Creative Inc.</p>
    </div>
  </body>
</html>