function archives_selected()
{
	return $('listSystem_Backup_index_list_body').getElements('tr td.checkbox input').some(function(element){return element.checked});
}

function delete_selected()
{
	if (!archives_selected())
	{
		alert('Please select archives to delete.');
		return false;
	}
	
	$('listSystem_Backup_index_list_body').getForm().sendPhpr(
		'index_onDeleteSelected',
		{
			confirm: 'Do you really want to delete selected archive(s)?',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: update_scrollable_toolbars,
			update: 'page_content'
		}
	);
	return false;
}
