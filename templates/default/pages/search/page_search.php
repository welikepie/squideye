<h2>Product search</h2>

<? $this->render_partial('shop:search_form') ?>

<? if (strlen($query)): ?>
    <p>Products found: <?= $pagination->getRowCount() ?></p>

    <? $this->render_partial('shop:product_list', array('products'=>$products, 'paginate'=>false)) ?>

    <? $this->render_partial('pagination', array('pagination'=>$pagination, 'base_url'=>root_url('/search'), 'suffix'=>'?query='.urlencode($query).'&amp;records='.urlencode($records))) ?>
<? endif ?>