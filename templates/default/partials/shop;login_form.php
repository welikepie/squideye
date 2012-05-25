<?= open_form() ?>
  <?= flash_message() ?>

  <ul class="form">
    <li class="field text">
      <label for="login_email">Email</label>
      <div><input id="login_email" type="text" name="email" value="<?= h(post('email')) ?>" class="text"/></div>
    </li>
    <li class="field text">
      <label for="login_password">Password</label>
      <div><input id="login_password" type="password" name="password" class="text"/></div>
    </li>
  </ul>
  <p><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_login')"><img src="<?= theme_resource_url('images/btn_signin.gif') ?>" alt="Sign In"/></a></p>
  <p><a href="<?= root_url('/password_restore') ?>">Forgot your password?</a></p>
  <input type="hidden" name="redirect" value="<?= $redirect ?>"/>
  
  <script type="text/javascript">window.addEvent('domready', function(){$('login_email').focus()})</script>
</form>