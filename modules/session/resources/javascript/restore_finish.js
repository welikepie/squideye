function submit_restore()
{
	$('FormElement').sendPhpr('restore_finish_onRestore', {
		loadIndicator: {element: 'formContent', hideOnSuccess: true, show: true, injectInElement: true}, update: 'formContent'}
	);

	return false;
}

window.addEvent('domready', function(){ 
	if ($('password'))
	{
		$('password').focus();
		$('FormElement').bindKeys({enter: submit_restore});
	}
});
