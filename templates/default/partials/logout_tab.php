<ul class="tabs accountFunctions">
  <? if (!$this->customer): ?>
  <li class="cart right">| <a href="<?= root_url('/cart') ?>">Cart</a> <?= $this->render_partial('shop:mini_cart') ?></li>
  <li class="user right">Welcome, <strong>Guest</strong>&nbsp;</li>
  <? endif ?>
  <? if ($this->customer): ?>
  <li class="cart right"><a href="<?= root_url('/cart') ?>">Cart</a> <?= $this->render_partial('shop:mini_cart') ?></li>
  <li class="orders right"><a href="<?= root_url('/orders') ?>">My Orders</a> |&nbsp;</li>
  <li class="user right">Welcome, <a href="<?= root_url('/profile') ?>"><strong><?= $this->customer->name ?></strong></a> |&nbsp;</li>
  <? else: ?>
  <? endif ?>
</ul>