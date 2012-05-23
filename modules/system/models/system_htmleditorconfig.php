<?

	class System_HtmlEditorConfig extends Db_ActiveRecord
	{
		public $table_name = 'system_htmleditor_config';

		public $controls = array(
			'bold'=>array('name'=>'Bold'),
			'italic'=>array('name'=>'Italic'),
			'underline'=>array('name'=>'Underline'),
			'strikethrough'=>array('name'=>'Strikethrough'),
			'justifyleft'=>array('name'=>'Align Left'),
			'justifycenter'=>array('name'=>'Align Center'),
			'justifyright'=>array('name'=>'Align Right'),
			'justifyfull'=>array('name'=>'Align Full'),
			'bullist'=>array('name'=>'Unordered List'),
			'numlist'=>array('name'=>'Ordered List'),
			'outdent'=>array('name'=>'Outdent'),
			'indent'=>array('name'=>'Indent'),
			'cut'=>array('name'=>'Cut'),
			'copy'=>array('name'=>'Copy'),
			'paste'=>array('name'=>'Paste'),
			'pastetext'=>array('name'=>'Paste Plain Text', 'plugin'=>'paste'),
			'pasteword'=>array('name'=>'Paste from Word', 'plugin'=>'paste'),
			'undo'=>array('name'=>'Undo'),
			'redo'=>array('name'=>'Redo'),
			'link'=>array('name'=>'Link'),
			'unlink'=>array('name'=>'Unlink'),
			'image'=>array('name'=>'Insert Image'),
			'cleanup'=>array('name'=>'Cleanup Code'),
			'code'=>array('name'=>'Edit HTML Source'),
			'hr'=>array('name'=>'Horizontal Rule'),
			'removeformat'=>array('name'=>'Remove Formatting'),
			'formatselect'=>array('name'=>'Format Selector'),
			'fontselect'=>array('name'=>'Font Selector'),
			'fontsizeselect'=>array('name'=>'Font Size Selector'),
			'styleselect'=>array('name'=>'Style Selector'),
			'sub'=>array('name'=>'Subscript'),
			'sup'=>array('name'=>'Superscript'),
			'forecolor'=>array('name'=>'Text Color'),
			'backcolor'=>array('name'=>'Background Color'),
			'charmap'=>array('name'=>'Character Map'),
			'anchor'=>array('name'=>'Anchor'),
			'blockquote'=>array('name'=>'Blockquote'),
			'search'=>array('name'=>'Search', 'plugin'=>'searchreplace'),
			'replace'=>array('name'=>'Replace', 'plugin'=>'searchreplace'),
			'ltr'=>array('name'=>'Left to Right', 'plugin'=>'directionality'),
			'rtl'=>array('name'=>'Right to Left', 'plugin'=>'directionality'),
			'cite'=>array('name'=>'Citation', 'plugin'=>'xhtmlxtras'),
			'abbr'=>array('name'=>'Abbreviation', 'plugin'=>'xhtmlxtras'),
			'acronym'=>array('name'=>'Acronym', 'plugin'=>'xhtmlxtras'),
			'ins'=>array('name'=>'Insertion', 'plugin'=>'xhtmlxtras'),
			'del'=>array('name'=>'Deletion', 'plugin'=>'xhtmlxtras'),
			'attribs'=>array('name'=>'Edit Attributes', 'plugin'=>'xhtmlxtras'),
			'tablecontrols'=>array('name'=>'Table Controls', 'plugin'=>'table')
		);
		
		protected $api_added_columns = array();

		public static function create()
		{
			return new self();
		}
		
		public static function get($module, $code)
		{
			$obj = System_HtmlEditorConfig::create()->where('code=?', $code)->where('module=?', $module)->find();
			if (!$obj)
				self::find_init_configs();
			else 
				return $obj;
				
			$obj = System_HtmlEditorConfig::create()->where('code=?', $code)->where('module=?', $module)->find();
			if (!$obj)
				throw new Phpr_ApplicationException('HTML editor configuration '.$code.' not found.');
				
			return $obj;
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('description', 'HTML Editor');

			$this->define_column('controls_row_1', 'First Row')->invisible();
			$this->define_column('controls_row_2', 'Second Row')->invisible();
			$this->define_column('controls_row_3', 'Third Row')->invisible();
			
			$this->define_column('content_css', 'Content CSS')->invisible();
			$this->define_column('block_formats', 'Formats')->invisible();

			$this->define_column('custom_styles', 'Custom Styles')->invisible();
			$this->define_column('font_sizes', 'Font Sizes')->invisible();
			$this->define_column('font_colors', 'Font Colors')->invisible();
			$this->define_column('background_colors', 'Background Colors')->invisible();
			$this->define_column('allow_more_colors', 'Allow More Colors')->invisible();
			$this->define_column('valid_elements', 'Valid Elements')->invisible()->validation()->fn('trim');
			$this->define_column('valid_child_elements', 'Valid Child Elements')->invisible()->validation()->fn('trim');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('system:onExtendHtmlEditorConfigModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('controls_row_1')->tab('Toolbar');
			$this->add_form_field('controls_row_2')->tab('Toolbar');
			$this->add_form_field('controls_row_3')->tab('Toolbar');

			$this->add_form_field('content_css')->renderAs(frm_text)->tab('Customize')->comment('Specify an URL of a custom CSS file to use inside the editable area. By default the following file used:<br/> /modules/cms/resources/css/htmlcontent.css', 'above', true);
			$this->add_form_field('block_formats')->renderAs(frm_text)->tab('Customize')->comment('A comma separated list of formats that will be available in the Format Selector list. The default value of this option is:<br/>p, address, pre, h1, h2, h3, h4, h5, h6', 'above', true);

			$this->add_form_field('custom_styles', 'left')->tab('Customize')->comment('A list of class titles and class names separated by =, one style per line. The titles will be presented to the user in the Style Selector list and the class names will be inserted. If this option is not defined, the editor imports the classes from the Content CSS file. Example: My style=my_css_class', 'above')->size('small');
			$this->add_form_field('font_sizes', 'right')->tab('Customize')->comment('A list of font sizes, one size per line. The list of font sizes will be shown in the Font Size Selector list. You can specify sizes in CSS size values, or with CSS classes. Example of usage:<br/>Big text=30px<br/>My Text Size=.mytextsize', 'above', true)->size('small');
			$this->add_form_field('font_colors', 'left')->tab('Customize')->comment('A comma-separated list of colors shown in the palette of colors displayed by the text color button. Example:<br/> FF00FF, FFFF00, 000000', 'above', true);
			$this->add_form_field('background_colors', 'right')->tab('Customize')->comment('A comma-separated list of colors shown in the palette of colors displayed by the background color button. Example:<br/> FF00FF, FFFF00, 000000', 'above', true);
			$this->add_form_field('allow_more_colors')->tab('Customize')->comment('This option enables you to disable the "more colors" link for the text and background color menus.', 'above');
			$this->add_form_field('valid_elements')->tab('Clean Up')->comment('The valid_elements option defines which elements will remain in the edited text when the editor saves. You can find more information about this option in <a href="http://wiki.moxiecode.com/index.php/TinyMCE:Configuration/valid_elements" target="_blank">TinyMCE documentation</a>', 'above', true)->size('large');
			$this->add_form_field('valid_child_elements')->tab('Clean Up')->comment('This option gives you the ability to specify what elements are valid inside different parent elements. You can find more information about this option in <a href="http://wiki.moxiecode.com/index.php/TinyMCE:Configuration/valid_child_elements" target="_blank">TinyMCE documentation</a>', 'above', true)->size('large');
			
			Backend::$events->fireEvent('system:onExtendHtmlEditorConfigForm', $this, $context);
			foreach ($this->api_added_columns as $column_name)
			{
				$form_field = $this->find_form_field($column_name);
				if ($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}

		public function get_added_field_options($db_name, $current_key_value = -1)
		{
			$result = Backend::$events->fireEvent('system:onExtendHtmlEditorConfigFieldOptions', $db_name, $current_key_value);
			foreach ($result as $options)
			{
				if (is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}

			return false;
		}

		public function list_row_controls($field)
		{
			$result = array();
			$items = explode(',', $this->$field);
			foreach ($items as $item)
			{
				$item = trim($item);
				if (!strlen($item))
					continue;
					
				$result[] = $item;
			}
			
			return $result;
		}
		
		protected function init_config_data()
		{
			$this->controls_row_1 = "cut,copy,paste,pastetext,pasteword,separator,undo,redo,separator,link,unlink,separator,image,separator,bold,italic,underline,separator,formatselect,separator,bullist,numlist,separator,code";
			$this->content_css = '/modules/cms/resources/css/htmlcontent.css';
			$this->block_formats = 'p, address, pre, h1, h2, h3, h4, h5, h6';
			$this->allow_more_colors = true;
		}
		
		public function before_save($deferred_session_key = null) 
		{
			$this->block_formats = $this->cleanup_comma_list($this->block_formats);
			$this->font_colors = $this->cleanup_comma_list($this->font_colors, false);
			$this->background_colors = $this->cleanup_comma_list($this->background_colors, false);
		}
		
		protected function cleanup_comma_list($list, $lower = true)
		{
			$items = explode(',', trim($list));
			$result = array();
			foreach ($items as $item)
			{
				$item = trim($item);
				if ($lower)
					$item = strtolower($item);
				
				if (!strlen($item))
					continue;
					
				$result[$item] = 1;
			}

			return implode(', ', array_keys($result));
		}
		
		public function list_custom_styles()
		{
			$styles = $this->custom_styles;
			$styles = str_replace("\r\n", "\n", $styles);
			$styles = explode("\n", $styles);
			
			$result = array();
			foreach ($styles as $style)
			{
				$style = trim($style);
				if (!strlen($style))
					continue;
					
				$result[] = $style;
			}
			
			return implode(";", $result);
		}
		
		public function list_plugins()
		{
			$plugins = array();
			
			$all_row_buttons = array_merge(explode(',', $this->controls_row_1),
				explode(',', $this->controls_row_2),
				explode(',', $this->controls_row_3));

			foreach ($all_row_buttons as $button)
			{
				if (!strlen($button) || !array_key_exists($button, $this->controls))
					continue;
					
				$button_data = $this->controls[$button];
				if (array_key_exists('plugin', $button_data))
					$plugins[$button_data['plugin']] = 1;
			}
			
			return array_keys($plugins);
		}
		
		public function list_font_sizes()
		{
			$sizes = explode("\n", str_replace("\r\n", "\n", $this->font_sizes));
			
			$result = array();
			foreach ($sizes as $size)
			{
				$size = trim($size);
				if (!strlen($size))
					continue;
					
				$result[] = $size;
			}
			
			return implode(",", $result);
		}
		
		public function apply_to_form_field($field)
		{
			$plugins = implode(',', $this->list_plugins());
			$custom_styles = $this->list_custom_styles();
			
			$field->htmlPlugins($plugins);

			$field->htmlButtons1($this->controls_row_1);

			if ($this->controls_row_2)
				$field->htmlButtons2($this->controls_row_2);
				
			if ($this->controls_row_3)
				$field->htmlButtons3($this->controls_row_3);
				
			$field->htmlContentCss($this->content_css);
			$field->htmlBlockFormats($this->block_formats);
			$field->htmlCustomStyles($custom_styles);
			$field->htmlFontSizes($this->list_font_sizes());
			
			$field->htmlFontColors(str_replace(' ', '', $this->font_colors));
			$field->htmlBackgroundColors(str_replace(' ', '', $this->background_colors));
			
			$field->htmlAllowMoreColors($this->allow_more_colors);
			
			if ($this->valid_elements)
				$field->htmlValidElements($this->valid_elements);
				
			if ($this->valid_child_elements)
				$field->htmlValidChildElements($this->valid_child_elements);
		}
		
		public function output_editor_config()
		{
			$result = array();
			$result['plugins'] = '"paste,searchreplace,inlinepopups,'.implode(',', $this->list_plugins()).'"';
			$result['theme_advanced_buttons1'] = '"'.$this->controls_row_1.'"';
			
			$result['theme_advanced_buttons2'] = '"'.$this->controls_row_2.'"';
			$result['theme_advanced_buttons3'] = '"'.$this->controls_row_3.'"';
			
			$result['theme_advanced_blockformats'] = '"'.Core_String::js_encode($this->block_formats).'"';
			$result['theme_advanced_styles'] = '"'.Core_String::js_encode($this->custom_styles).'"';

			$custom_styles = $this->list_custom_styles();
			$result['theme_advanced_font_sizes'] = '"'.Core_String::js_encode($custom_styles).'"';
			$result['theme_advanced_font_sizes'] = '"'.Core_String::js_encode($this->list_font_sizes()).'"';

			if ($this->font_colors)
				$result['theme_advanced_text_colors'] = '"'.Core_String::js_encode(str_replace(' ', '', $this->font_colors)).'"';

			if ($this->background_colors)
				$result['theme_advanced_background_colors'] = '"'.Core_String::js_encode(str_replace(' ', '', $this->background_colors)).'"';

			$result['theme_advanced_more_colors'] = $this->allow_more_colors ? 'true' : 'false';

			if ($this->valid_elements)
				$result['valid_elements'] = '"'.Core_String::js_encode($this->valid_elements).'"';

			if ($this->valid_child_elements)
				$result['valid_child_elements'] = '"'.Core_String::js_encode($this->valid_child_elements).'"';
				
			if ($this->content_css)
				$result['content_css'] = '"'.$this->content_css.'"';
				
			$result_str = array();
			foreach ($result as $name=>$value)
				$result_str[] = $name.': '.$value;
				
			return implode(",\n", $result_str).",\n";
		}

		public function init_config($code, $module, $description)
		{
			$this->init_config_data();
			$this->code = $code;
			$this->module = $module;
			$this->description = $description;
			$this->save();
		}
		
		public static function find_init_configs()
		{
			$configurations = Core_ModuleManager::listHtmlEditorConfigs();

			foreach ($configurations as $module=>$configs)
			{
				foreach ($configs as $code=>$description)
				{
					$obj = System_HtmlEditorConfig::create()->where('code=?', $code)->where('module=?', $module)->find();
					if (!$obj)
						System_HtmlEditorConfig::create()->init_config($code, $module, $description);
				}
			}
		}
	}

?>