window.addEvent('domready', function(){
	var switcher = $('System_Backup_Params_backup_on_login');
	if (switcher)
	{
		switcher.addEvent('toggle', function(state){
			if (state)
				$('form_field_backup_on_loginSystem_Backup_Params').addClass('separatedField');
			else
				$('form_field_backup_on_loginSystem_Backup_Params').removeClass('separatedField');

			var fields = $(switcher).getForm().getElements('li.auto_backup_field');
			fields.each(function(element){
				if (state) 
					$(element).show(); 
				else
					$(element).hide();
			})
		})
	}
})
