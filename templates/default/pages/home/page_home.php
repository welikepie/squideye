<div class="introContent">
  <? content_block('intro', 'Intro Block') ?>
</div>
<div class="side_by_side_products">
  <h2>Featured products</h2>
          
  <? $this->render_partial('shop:custom_group', array('group_code'=>'featured_products')) ?>
</div>