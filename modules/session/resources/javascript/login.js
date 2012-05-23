function submit_login()
{
	// $('FormElement').sendPhpr('create_onsubmit', {
	// 	loadIndicator: {element: 'formContent', hideOnSuccess: false, show: true, injectInElement: true}}
	// );
	
	$('FormElement').submit();

	return false;
}

window.addEvent('domready', function(){ 
	if ($('login'))
	{
		$('login').focus();
		// $('FormElement').bindKeys({enter: submit_login});

		if (window.location.hash == '#auto_login')
			submit_login();
	}
});
