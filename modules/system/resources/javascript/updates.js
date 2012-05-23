var allow_onload = true;

window.addEvent('domready', function(){ 
	if (window.location.hash == '#check')
	{
		new PopupForm('index_onUpdateForm', {closeByEsc: false});
	}
	
	if ($('module_tabs'))
	{
		new TabManager('module_list_tabs', 
		  	'module_list_pages', 
		  	{trackTab: false});
	}
});

function handle_unload()
{
	if (allow_onload)
		return;

	return 'The update process is in progress. Please do not leave the page until it is complete.';
}

window.onbeforeunload = handle_unload;
