var AceWrapper = new Class({
	Implements: [Options, Events],
	
	options: {
		showInvisibles: true,
		highlightActiveLine: true,
		showGutter: true,
		showPrintMargin: true,
		highlightSelectedWord: false,
		hScrollBarAlwaysVisible: false,
		useSoftTabs: true,
		tabSize: 4,
		fontSize: 12,
		wrapMode: 'off',
		readOnly: false,
		theme: 'textmate',
		folding: 'manual'
	},
	
	fullscreen_title_off: 'Enter fullscreen mode: <strong>ctrl+alt+f</strong>',
	fullscreen_title_on: 'Exit fullscreen mode: <strong>ctrl+alt+f</strong> or <strong>esc</strong>',
	
	initialize: function(textarea, language, options)
	{
		this.setOptions(options);
		this.textarea = $(textarea);
		this.textarea.hide();
		this.bound_resize = this._update_size_fullscreen.bind(this);
		this.bound_keydown = this._keydown.bind(this);

		this.code_wrapper = this.textarea.getParent();
		this.field_container = this.code_wrapper.getParent();
		var 
			ui_wrapper = this.ui_wrapper = new Element('div', {'class': 'code_editor_wrapper'}).inject(this.field_container, 'bottom'),
			height = Cookie.read(this.field_container.get('id')+'editor_size');

		this.code_wrapper.inject(this.ui_wrapper);

		this.pre = new Element('pre', {
			'styles': {
				'fontSize': this.options.fontSize + 'px'
			}, 
			'class': 'form-ace-editor', 
			'id': this.textarea.get('id') + 'pre'
		}).inject(this.textarea.getParent(), 'bottom');
		this.editor = ace.edit(this.pre.get('id'));

		Asset.javascript(ls_root_url('phproad/thirdpart/ace/theme-'+this.options.theme+'.js'), {onload: function() {
			this.editor.setTheme('ace/theme/'+this.options.theme);
		}.bind(this)});
		
		this.editor.getSession().setValue(this.textarea.get('value'));

		if (language.length)
			Asset.javascript(ls_root_url('phproad/thirdpart/ace/mode-'+language+'.js'), {onload: this._language_loaded.bind(this, language)});
			
		var 
			textarea_id = this.textarea.get('id'),
			self = this;

		this.editor.getSession().on('change', function(){
			window.fireEvent('phpr_codeeditor_changed', textarea_id);
		});
		this.editor.on('focus', function(){
			ui_wrapper.addClass('focused');
			phpr_active_code_editor = textarea_id;
		});
		this.editor.on('blur', function(){ui_wrapper.removeClass('focused');});
		
		phpr_code_editors.push({'id': textarea_id, 'editor': this.editor});

		window.fireEvent('phpr_codeeditor_initialized', [textarea_id, this.editor])
		window.addEvent('phpr_layout_updated', function(){self.update();});
		
		
		/*
		 * Configure
		 */
		
		this.editor.wrapper = this;

		this.editor.setShowInvisibles(this.options.showInvisibles);
		this.editor.setHighlightActiveLine(this.options.highlightActiveLine);
		this.editor.renderer.setShowGutter(this.options.showGutter);
		this.editor.renderer.setShowPrintMargin(this.options.showPrintMargin);
		this.editor.setHighlightSelectedWord(this.options.highlightSelectedWord);
		this.editor.renderer.setHScrollBarAlwaysVisible(this.options.hScrollBarAlwaysVisible);
		this.editor.getSession().setUseSoftTabs(this.options.useSoftTabs);
		this.editor.getSession().setTabSize(this.options.tabSize);
		this.editor.setReadOnly(this.options.readOnly);
	    this.editor.getSession().setFoldStyle(this.options.folding);
		
		var 
			session = this.editor.getSession(),
			renderer = this.editor.renderer;
		
		switch (this.options.wrapMode) {
			case "off":
				session.setUseWrapMode(false);
				renderer.setPrintMarginColumn(80);
			break;
			case "40":
				session.setUseWrapMode(true);
				session.setWrapLimitRange(40, 40);
				renderer.setPrintMarginColumn(40);
			break;
			case "80":
				session.setUseWrapMode(true);
				session.setWrapLimitRange(80, 80);
				renderer.setPrintMarginColumn(80);
			break;
			case "free":
				session.setUseWrapMode(true);
				session.setWrapLimitRange(null, null);
				renderer.setPrintMarginColumn(80);
			break;
		}		
		
		window.addEvent('phprformsave', this._save.bind(this));
		
		this.code_wrapper.bindKeys({'ctrl+alt+f': function(event) {
			self._fullscreen_mode();
		}});
		
		/*
		 * Create the footer 
		 */

		var footer = new Element('div', {'class': 'code_editor_footer'}).inject(this.ui_wrapper, 'bottom');
		this.resize_handle = new Element('div', {'class': 'resize_handle'}).inject(footer, 'bottom');
		
		new Drag(this.code_wrapper, {
			'handle': this.resize_handle,
			'modifiers': {'x': '', 'y': 'height'},
			'limit': {'y': [100, 3000]},
			onDrag: function(){ 
				this.fireEvent('resize', this);
				this.editor.resize();
			}.bind(this),
			onComplete: function(){
				this.editor.resize();
				window.fireEvent('phpr_editor_resized');
				jQuery(window).trigger('phpr_layout_updated');
				Cookie.write(this.field_container.get('id')+'editor_size', this.code_wrapper.getSize().y, {duration: 365, path: '/'});
			}.bind(this)
		});
		
		/*
		 * Create the toolbar
		 */

		this.toolbar = new Element('div', {'class': 'code_editor_toolbar hidden'}).inject(this.code_wrapper, 'top');
		this.toolbar.set('morph', {duration: 'short', transition: Fx.Transitions.Sine.easeOut, onComplete: this._toolbar_effect_complete.bind(this)});
		this.displaying_toolbar = false;
		this.ui_wrapper.addEvent('mousemove', this._display_toolbar.bind(this));

		/*
		 * Create buttons
		 */

		var list = new Element('ul').inject(this.toolbar);

		this.fullscreen_btn = new Element('li', {'class': 'fullscreen tooltip', 'title': this.fullscreen_title_off}).inject(list);
		var fullscreen_btn_link = new Element('a', {'href': 'javascript:;'}).inject(this.fullscreen_btn);
		fullscreen_btn_link.addEvent('click', this._fullscreen_mode.bind(this));

		new Element('div', {'class': 'clear'}).inject(this.toolbar, 'bottom');
		update_tooltips();
		
		/*
		 * Update height from cookies
		 */
		
		if (height !== null) {
			this.code_wrapper.setStyle('height', height+'px');
			window.fireEvent('phpr_editor_resized');
		}
		
		jQuery(window).trigger('phpr_layout_updated');
	},
	
	setFontSize: function(size) {
		this.pre.setStyle('fontSize', size + 'px');
	},
	
	update: function() {
		this.editor.resize();
	},
	
	_language_loaded: function(language) {
		var mode = require('ace/mode/'+language).Mode;
		this.editor.getSession().setMode(new mode());
	},
	
	_save: function() {
		this.textarea.set('value', this.editor.getSession().getValue());
	},
	
	_fullscreen_mode: function() {
		if (!this.field_container.hasClass('fullscreen'))
		{
			this.normal_scroll = window.getScroll();
			this.original_container_parent = this.field_container.getParent();

			this.field_container.addClass('fullscreen');
			document.body.setStyle('overflow', 'hidden');
			window.scrollTo(0, 0);

			// Fix for Chrome
			this.field_container.style.display= "none";
			var redrawFix = this.field_container.offsetHeight;
			this.field_container.style.display="block";

			this._update_size_fullscreen();
			window.addEvent('resize', this.bound_resize);
			
			document.addEvent('keydown', this.bound_keydown);
			this.fullscreen_btn.set('title', this.fullscreen_title_on);
		} else {
			document.removeEvent('keydown', this.bound_keydown);

			this.field_container.removeClass('fullscreen')
			document.body.setStyle('overflow', 'visible');
			window.removeEvent('resize', this.bound_resize);
			this.pre.setStyle('height', 'auto');
			window.scrollTo(this.normal_scroll.x, this.normal_scroll.y);
			this.editor.resize();
			this.editor.focus();
			this.fullscreen_btn.set('title', this.fullscreen_title_off);
		}
	},
	
	_keydown: function(event) {
		if (event.key == 'esc') {
			this._fullscreen_mode();
			event.stop();
			return false;
		}
	},
	
	_update_size_fullscreen: function() {
		var window_size = window.getSize();
		
		this.pre.setStyle('height', window_size.y + 'px');
		this.editor.resize();
	},
	
	_display_toolbar: function() {
		if (this.displaying_toolbar) {
			if (this.hide_toolbar_timer !== undefined)
				$clear(this.hide_toolbar_timer);

			this.hide_toolbar_timer = this._hide_toolbar.delay(2000, this);
			return;
		}

		this.displaying_toolbar = true;
		this.hiding_toolbar = false;
		this.toolbar.show();

		this.toolbar.morph({'opacity': [0, 1]});
	},
	
	_hide_toolbar: function() {
		this.hiding_toolbar = true;
		this.toolbar.morph({'opacity': [1, 0]});
	},
	
	_toolbar_effect_complete: function() {
		if (this.hiding_toolbar) {
			this.toolbar.hide();
			this.displaying_toolbar = false;
		}
	}
})