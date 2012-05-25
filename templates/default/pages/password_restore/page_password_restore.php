<h2>Password Restore</h2>

<div class="column column_300">
  <p>Please specify your email address and click Submit. We will send you a message with new password.</p>

  <?= open_form() ?>
    <?= flash_message() ?>
    <ul class="form">
      <li class="field text">
        <label for="email">Email</label>
        <div><input id="email" type="text" name="email" value="<?= h(post('email')) ?>" class="text"/></div>
      </li>
    </ul>
    <p><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_passwordRestore')"><img src="<?= theme_resource_url('images/btn_submit.gif') ?>" alt="Submit"/></a></p>
    <input type="hidden" name="redirect" value="<?= root_url('/password_restore_success') ?>"/>
  </form>    
</div>
<div class="clear"></div>