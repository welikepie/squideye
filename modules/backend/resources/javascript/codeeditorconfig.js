window.addEvent('domready', function(){
	if ($('code-example'))
	{
		init_code_editor('code-example', 'css', editor_settings);
		var left_form_elements = document.body.getElement('.left-panel ul.formElements');
		$('example-code-wrapper').setStyle('height', (left_form_elements.getSize().y-2) + 'px');

		var 
			editor = find_code_editor('code-example'),
			session = editor.getSession(),
			renderer = editor.renderer;
			
		editor.resize();
		
		$('Backend_CodeEditorConfiguration_font_size').addEvent('change', function(){
			editor.wrapper.setFontSize(this.get('value'));
		})
		
		$('Backend_CodeEditorConfiguration_soft_wrap').addEvent('change', function(){
			var value = this.get('value');
			
			switch (value) {
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
		});
		
		$('Backend_CodeEditorConfiguration_code_folding').addEvent('change', function(){
		    session.setFoldStyle(this.get('value'));
		});
		
		$('Backend_CodeEditorConfiguration_tab_size').addEvent('change', function(){
		    session.setTabSize(this.get('value'));
		});
		
		$('Backend_CodeEditorConfiguration_highlight_active_line').addEvent('click', function(){
			editor.setHighlightActiveLine(this.checked);
		});
		
		$('Backend_CodeEditorConfiguration_show_invisibles').addEvent('click', function(){
			editor.setShowInvisibles(this.checked);
		});
		
		$('Backend_CodeEditorConfiguration_show_gutter').addEvent('click', function(){
			renderer.setShowGutter(this.checked);
		});
		
		$('Backend_CodeEditorConfiguration_soft_tabs').addEvent('click', function(){
			session.setUseSoftTabs(this.checked);
		});
		
		$('Backend_CodeEditorConfiguration_show_print_margin').addEvent('click', function(){
			renderer.setShowPrintMargin(this.checked);
		});
		
		$('Backend_CodeEditorConfiguration_color_theme').addEvent('change', function(){
			var value = this.get('value');
			
			Asset.javascript(ls_root_url('phproad/thirdpart/ace/theme-'+value+'.js'), {onload: function() {
				editor.setTheme('ace/theme/'+value);
			}.bind(this)});
		})
	}
});