var UnloadManagerClass = new Class({
	data_changed_flag: false,
	unload_message: 'Form data was changed.',
	verbose: false,
	
	initialize: function()
	{
		window.addEvent('domready', this.bind_inputs.bind(this));
		window.addEvent('phpr_codeeditor_changed', this.data_changed.bind(this, 'editarea'));

		window.addEvent('phpreditoradded', this.bind_html_editors.bind(this));
		window.addEvent('phpreditorreloaded', this.bind_html_editors.bind(this));
		
		window.onbeforeunload = this.handle_unload;

		if (Browser.Engine.trident)
			$(document).addEvent('keypress', this.handle_keys.bindWithEvent(this, this) );
		else
			$(window).addEvent('keypress', this.handle_keys.bindWithEvent(this, this) );
	},
	
	handle_keys: function(event)
	{
		var ev = new Event(event);

		if (
			!(((ev.code >= 65 && ev.code <= 90) ||
			(ev.code >= 48 && ev.code <= 57)) 
			&& !event.control
			&& !event.meta 
			&& !event.alt)
		)
			return true;

		if (ev.target)
		{
			if (ev.target.tagName != 'TEXTAREA' && ev.target.tagName != 'INPUT' && ev.target.tagName != 'SELECT')
				return true;
		}

		if (this.verbose)
		{
			console.log('Key pressed...' + ev.code);
			console.log(ev.target.tagName);
		}

		this.data_changed();
		
		return true;
	},
	
	bind_inputs: function()
	{
		$(document.body).getElements('input').each(function(input){
			if (input.type == 'radio' || input.type == 'checkbox')
				input.addEvent('click', this.data_changed.bind(this));
		}, this);

		$(document.body).getElements('select').each(function(input){
			input.addEvent('change', this.data_changed.bind(this));
		}, this);
	},
	
	bind_html_editors: function(editor_id)
	{
		var editor = tinyMCE.get(editor_id);
		if (editor)
			editor.onChange.add(this.data_changed.bind(this));
		else
		{
			tinyMCE.onAddEditor.add(function(mgr,ed) 
			{
				if (ed.id == editor_id)
				{
					ed.onChange.add(UnloadManager.data_changed.bind(UnloadManager));
				}
			});
		}
	},
	
	data_changed: function(src)
	{
		if (this.verbose)
			console.log('Something changed...');

		this.data_changed_flag = true;
	},
	
	handle_unload: function()
	{
		if ($('phpr_lock_mode'))
			return;
		
		try
		{
			if (tinymce && tinymce.EditorManager.activeEditor)
				tinymce.EditorManager.activeEditor.execCommand('mceEndTyping', false, null);
		} catch(e){}
		
		if (UnloadManager.data_changed_flag)
			return UnloadManager.unload_message;
	},
	
	reset_changes: function()
	{
		try
		{
			if (tinymce && tinymce.EditorManager.activeEditor)
				tinymce.EditorManager.activeEditor.execCommand('mceEndTyping', false, null);
		} catch(e){}

		if (this.verbose)
			console.log('Changes cancelled...');

		this.data_changed_flag = false;
	}
});

var UnloadManager = new UnloadManagerClass();