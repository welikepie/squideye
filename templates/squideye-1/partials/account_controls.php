<div class="account-controls">
	<? if ($this->customer) { ?>
	<p>Greetins, <strong><?= $this->customer->name ?></strong>!</p>
	<p><a href="<?= root_url('/profile') ?>">Account info</a></p>
	<p><a href="<?= root_url('/logout') ?>">Log out</a></p>
	<? } else { ?>
	<p><a href="<?= root_url('/login') ?>">Log in or sign up</a></p>
	<? } ?>
</div>