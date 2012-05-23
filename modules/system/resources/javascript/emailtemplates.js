var prevWidth = 0;
var subject_focused = false;
var subject_field = null;
var form_handler = null;
var focus_binder_interval = null;

function save_template()
{
	var options = {
		prepareFunction: function(){phprTriggerSave();},
		extraFields: {redirect: 1},
		loadIndicator: {show: false},
		onBeforePost: LightLoadingIndicator.show.pass('Saving...'),
		onComplete: LightLoadingIndicator.hide,
		onFailure: popupAjaxError,
		onAfterUpdate: false,
		onAfterError: $('FormElement').getElement('div.formFlash').innerHTML = '',
		update: $('FormElement').getElement('div.formFlash')
	}
	
	if(form_handler)
		$('FormElement').sendPhpr(form_handler, options);

	return false;
}

function setupVariablesSize()
{
	var formElement = $('FormElement').getElement('div.form');
	var formSize = formElement.getSize();
	var headerElement = $('variables_header');
	var headerSize = headerElement.getSize();

	$('variable_list').setStyle('height', (formSize.y-20-headerSize.y)+'px');
}

function insert_variable(variable)
{
	if (subject_focused)
		subject_field.insertTextAtCursor(variable);
	else
		tinyMCE.execCommand('mceInsertContent', false, variable); 

	return false;
}

function set_reply_to_satus(reply_to_address_field)
{
	reply_to_address_field.disabled = !$('System_EmailTemplate_reply_to_mode_4').checked;
}

window.addEvent('domready', function(){
	$(document.getElement('html')).bindKeys({
		'meta+s, ctrl+s': save_template
	});
	
	(function(){setupVariablesSize();}).delay(600)
	
	subject_field = $('System_EmailTemplate_subject');
	if (subject_field)
		subject_field.addEvent('focus', function(){ subject_focused = true; });
		
	var reply_to_address_field = $('System_EmailTemplate_reply_to_address');
	if (reply_to_address_field)
	{
		set_reply_to_satus(reply_to_address_field);
		$('System_EmailTemplate_reply_to_mode_4').addEvent('click', set_reply_to_satus.pass(reply_to_address_field));
		$('System_EmailTemplate_reply_to_mode_3').addEvent('click', set_reply_to_satus.pass(reply_to_address_field));
		$('System_EmailTemplate_reply_to_mode_2').addEvent('click', set_reply_to_satus.pass(reply_to_address_field));
		$('System_EmailTemplate_reply_to_mode').addEvent('click', set_reply_to_satus.pass(reply_to_address_field));
	}
});

function bind_editor_click(editor_id)
{
	var ed = tinymce.EditorManager.get(editor_id);
	if (ed)
	{
		$clear(focus_binder_interval);
		ed.onClick.add(function(e, oe) {
			subject_focused = false;
		});
	}
}

window.addEvent('phpreditoradded', function(editor_id){
	focus_binder_interval = bind_editor_click.periodical(100, null, [editor_id]);
})

window.addEvent('resize', function() {
	if (prevWidth == window.getSize().x)
		return;

	// $('FormElement').getElements('li.html textarea').each(function(ta_element){
	// 	phprTriggerSave();		
	// 	tinyMCE.execCommand('mceRemoveControl',false,ta_element.id);
	// 	tinyMCE.execCommand('mceAddEditor',false,ta_element.id);
	// })
	
	prevWidth = window.getSize().x;
});