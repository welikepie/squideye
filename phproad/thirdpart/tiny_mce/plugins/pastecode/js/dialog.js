tinyMCEPopup.requireLangPack();

var PasteCodeDialog = {
	init : function() {
		this.resize();
	},

	insert : function() {
		// Insert the contents from the input into the document
		var el = document.getElementById('content');
		var value = el.value;
		if (!value.trim())
		{
			alert('Please paste code into the text area.');
			return false;
		}

		var lines = value.split(/\r?\n/);
		if (!lines.length)
		{
			alert('Please paste code into the text area.');
			return false;
		}
		
		var line_num = lines.length;
		var new_lines = [];
		for (var index=0; index < line_num; index++)
		{
			if (lines[index].trim().length > 0)
				new_lines.push(lines[index]);
		}
		
		if (!new_lines.length)
		{
			alert('Please paste code into the text area.');
			return false;
		}

		var spaces = new_lines[0].match(/^\s*/i);
		value = '';

		var re = RegExp('^'+spaces, 'i');
		tinymce.each(new_lines, function(row) {
			value += "\n" + row.replace(re, '');
		});

		value = value.replace(/&/g, '&amp;');
		value = value.replace(/\</g, '&lt;');
		value = value.replace(/\>/g, '&gt;');
		value = value.replace(/"/g, '&quot;');

		value = "<pre>"+value.trim()+"</pre>\r\n<p>&nbsp;<\p>";
		tinyMCEPopup.editor.execCommand('mceInsertClipboardContent', false, {'content' : value});
		
		tinyMCEPopup.close();
		return false;
	},
	
	resize : function() {
		var vp = tinyMCEPopup.dom.getViewPort(window), el;

		el = document.getElementById('content');

		el.style.width  = (vp.w - 20) + 'px';
		el.style.height = (vp.h - 70) + 'px';
	}
};

tinyMCEPopup.onInit.add(PasteCodeDialog.init, PasteCodeDialog);
