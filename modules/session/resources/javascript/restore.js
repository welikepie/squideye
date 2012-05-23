function submit_restore()
{
	$('FormElement').sendPhpr('restore_onSend', {
		loadIndicator: {element: 'formContent', hideOnSuccess: true, show: true, injectInElement: true}, update: 'formContent'}
	);

	return false;
}

window.addEvent('domready', function(){ 
	if ($('login'))
	{
		$('login').focus();
		$('FormElement').bindKeys({enter: submit_restore});
	}
});
