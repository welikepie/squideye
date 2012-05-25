<p>Please provide your credit card information.</p>

<p><strong>Important:</strong> DO NOT use real credit card numbers and names within the testing environment.<br/>
Please use the following credit card number: <strong>4111111111111111</strong></p>

<?= open_form() ?>
  <?= flash_message() ?>
  <ul class="form">
    <li class="field text left">
      <label for="FIRSTNAME">Cardholder First Name</label>
      <div><input name="FIRSTNAME" value="John" id="FIRSTNAME" type="text" class="text"/></div>
    </li>    
    
    <li class="field text right">
      <label for="LASTNAME">Cardholder Last Name</label>
      <div><input name="LASTNAME" value="Smith" id="LASTNAME" type="text" class="text"/></div>
    </li>    

    <li class="field text">
      <label for="ACCT">Credit Card Number</label>
      <div><input name="ACCT" value="4111111111111111" id="ACCT" type="text" class="text"/></div>
    </li>
    
    <li class="field select left">
      <label for="EXPDATE_MONTH">Expiration Date - Month</label>
      <select name="EXPDATE_MONTH" id="EXPDATE_MONTH">
        <? for ($month=1; $month <= 12; $month++): ?>
          <option <?= option_state($month, 12) ?> value="<?= $month ?>"><?= $month ?></option>
        <? endfor ?> 
      </select>
    </li>

    <li class="field text right">
      <label for="EXPDATE_YEAR">Expiration Date - Year</label>

      <select name="EXPDATE_YEAR" id="EXPDATE_YEAR">
        <?
          $startYear = Phpr_DateTime::now()->getYear();
          for ($year=$startYear; $year <= $startYear + 10; $year++): ?>
          <option <?= option_state($year, 2010) ?> value="<?= $year ?>"><?= $year ?></option>
        <? endfor ?> 
      </select>
    </li>

    <li class="field text">
      <label for="CVV2">
        CVV2
        <span class="comment">For MasterCard, Visa, and Discover, the CSC is the last three digits in the signature area on the back of your card. For American Express, it's the four digits on the front of the card.</span>
      </label>
      
      <div><input name="CVV2" value="123" id="CVV2" type="text" class="text"/></div>
    </li>    
  </ul>
  <div class="clear"></div>
  <a href="#" onclick="return $(this).getForm().sendRequest('shop:on_pay')"><img src="<?= theme_resource_url('images/btn_submit.gif') ?>" alt="Submit"/></a>
</form>