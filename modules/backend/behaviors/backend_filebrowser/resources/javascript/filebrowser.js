function filebrowser_insert_content(path, content_type, event, action)
{
	var e = new Event(event);
	
	if (!tinyMCE.activeEditor)
		return false;
		
	if (e.alt)
	{
		if (content_type == 'image')
			new PopupForm(action+'_onFileBrowserInsertImage', {ajaxFields: {image_path: path}}); 
		else
			new PopupForm(action+'_onFileBrowserInsertLink', {ajaxFields: {link_path: path}}); 

		return false;
	} else
	{
		path = $('file_browser_root_url').value + ls_root_url(path);
		
		if (content_type == 'image')
		{
			tinyMCE.activeEditor.execCommand('mceInsertContent', false, '<img src="'+path+'"/>');
			// var el = tinyMCE.activeEditor.dom.create('img', {src : path});
			// tinyMCE.activeEditor.selection.setNode(el);
		} else
		{
			tinyMCE.activeEditor.execCommand('mceInsertContent', false, '<a href="'+path+'">'+path+'</a>');
			
//			var el = tinyMCE.activeEditor.dom.create('a', {href : path}, path);
//			tinyMCE.activeEditor.selection.setNode(el);
		}
	}

	return false;
}

function filebrowser_insert_image()
{
	var img_path = $('image_file_result').get('text');
	var parts = img_path.split('^|^');
	var alt = '';
	if (parts.length > 1)
	{
		img_path = parts[0];
		alt = parts[1];
	}

	img_path = $('file_browser_root_url').value + img_path;
	
	// var el = tinyMCE.activeEditor.dom.create('img', {src : img_path});
	// tinyMCE.activeEditor.selection.setNode(el);
	tinyMCE.activeEditor.execCommand('mceInsertContent', false, '<img src="'+img_path+'" alt="'+alt+'"/>');
	
	cancelPopup();
}

function filebrowser_insert_link()
{
	var link_path = $('link_url').value;
	link_path = $('file_browser_root_url').value + link_path;

	// var el = tinyMCE.activeEditor.dom.create('a', link_path, $('link_text').value);
	// tinyMCE.activeEditor.selection.setNode(el);
	tinyMCE.activeEditor.execCommand('mceInsertContent', false, '<a href="'+link_path+'">'+$('link_text').value+'</a>');
	
	cancelPopup();
	return false;
}
