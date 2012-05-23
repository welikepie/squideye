var url_modified = false;

function update_url_title(field_element)
{
	if (!url_modified)
		$('Blog_Category_url_name').value =  convert_text_to_url(field_element.value);
}

window.addEvent('domready', function(){
	var title_field = $('Blog_Category_name');
	if (title_field && $('new_record_flag'))
	{
		title_field.addEvent('keyup', update_url_title.pass(title_field));
		title_field.addEvent('change', update_url_title.pass(title_field));
		title_field.addEvent('paste', update_url_title.pass(title_field));
	}
	
	if ($('new_record_flag'))
	{
		var url_element = $('Blog_Category_url_name');
		url_element.addEvent('change', function(){url_modified=true;});
	}
});