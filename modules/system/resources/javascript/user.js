var original_cb_states = new Hash();

function setOriginalPermissionsStates()
{
	original_cb_states.empty();
	$('form_pagesUsers_User').getElements('li.permission_field input.checkbox').each(function(element){
		original_cb_states.set(element.id, element.checked);
	});
}

function updatePermissionsState()
{
	var admin_cb = $('form_field_container_rightsUsers_User').getElement('input.checkbox');
	
	if (admin_cb.checked)
		setOriginalPermissionsStates();

	$('form_pagesUsers_User').getElements('li.permission_field input').each(function(element){
		element.cb_update_enabled_state(!admin_cb.checked);
		
		if (element.hasClass('checkbox'))
		{
			if (admin_cb.checked)
			{
				element.cb_check();
			}
			else
			{
				if (original_cb_states.has(element.id))
				{
					element.cb_update_state(original_cb_states.get(element.id));
				}
			}
		}
	});
}

window.addEvent('domready', function(){
	updatePermissionsState();
	$('form_field_container_rightsUsers_User').getElement('input.checkbox').addEvent('click', updatePermissionsState);
});