<?= open_form() ?>
  <?= flash_message() ?>

  <ul class="form">
    <li class="field text left">
      <label for="first_name">First Name</label>
      <div><input name="first_name" id="first_name" type="text" class="text"/></div>
    </li>
    <li class="field text right">
      <label for="last_name">Last Name</label>
      <div><input name="last_name" id="last_name" type="text" class="text"/></div>
    </li>  
    <li class="field text">
      <label for="signup_email">Email</label>
      <div><input id="signup_email" type="text" name="email" value="<?= h(post('email')) ?>" class="text"/></div>
    </li>
  </ul>
  <p><a href="#" onclick="return $(this).getForm().sendRequest('shop:on_signup')"><img src="<?= theme_resource_url('images/btn_submit.gif')?>" alt="Submit"/></a></p>
  <input type="hidden" name="redirect" value="<?= root_url('/registration_thankyou') ?>"/>
</form>