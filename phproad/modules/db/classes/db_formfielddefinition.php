<?php

	define('frm_text', 'text');
	define('frm_password', 'password');
	define('frm_dropdown', 'dropdown');
	define('frm_autocomplete', 'autocomplete');
	define('frm_radio', 'radio');
	define('frm_checkbox', 'checkbox');
	define('frm_checkboxlist', 'checkboxlist');

	define('frm_textarea', 'textarea');
	define('frm_html', 'html');
	define('frm_code_editor', 'code_editor');
	define('frm_grid', 'grid');

	define('frm_datetime', 'datetime');
	define('frm_date', 'date');
	define('frm_time', 'time');
	
	define('frm_onoffswitcher', 'on_off_switcher');
	define('frm_record_finder', 'recordfinder');

	define('frm_file_attachments', 'file_attachments');

	class Db_FormFieldDefinition extends Db_FormElement
	{
		public $dbName;
		public $formSide;
		public $renderMode = null;
		public $comment;
		public $commentPosition;
		public $commentHTML = false;
		public $previewComment = null;
		public $size;
		public $emptyOption = null;
		public $noOptions = null;
		public $optionsMethod = null;
		public $optionStateMethod = null;
		public $referenceFilter = null;
		public $referenceSort = null;
		public $referenceDescriptionField = null;
		public $checkboxOnState = 1;
		public $addAttachmentLabel = 'Add file';
		public $noAttachmentsLabel = 'No files';
		public $imageThumbSize = 100;
		public $previewNoRelation = false;
		public $relationPreviewNoOptions = 'No options were assigned';
		public $optionsHtmlEncode = true;
		public $disabled = false;
		public $textareaServices = null;
		public $cssClasses = null;
		public $cssClassName = null;
		public $renderFilesAs = 'file_list';
		public $language = 'html';
		public $gridColumns = array();
		public $gridSettings = array();
		public $noLabel = false;
		public $hideContent = false;
		public $fileDownloadBaseUrl = null;
		public $htmlPlugins = "paste,searchreplace,advlink,inlinepopups";
		public $htmlButtons1 = "cut,copy,paste,pastetext,pasteword,separator,undo,redo,separator,link,unlink,separator,image,separator,bold,italic,underline,separator,formatselect,separator,bullist,numlist,separator,code";
		public $htmlButtons2 = null;
		public $htmlButtons3 = null;
		public $htmlContentCss = '/phproad/resources/css/htmlcontent.css';
		public $htmlBlockFormats = 'p,address,pre,h1,h2,h3,h4,h5,h6';
		public $htmlCustomStyles = null;
		public $htmlFontSizes = null;
		public $htmlFontColors = null;
		public $htmlBackgroundColors = null;
		public $htmlAllowMoreColors = true;
		public $htmlValidElements = null;
		public $htmlValidChildElements = null;
		public $nl2br = false;
		public $titlePartial = null;
		public $formElementPartial = null;
		public $previewHelp = null;
		public $commentTooltip = null;
		public $htmlFullWidth = false;
		public $renderOptions = array();
		public $previewLink = null;
		
		public $saveCallback = null;
		
		private $_model;
		private $_columnDefinition;
		
		public function __construct($model, $dbName, $side)
		{
			$modelClass = get_class($model);
			
			$column_definitions = $model->get_column_definitions();
			if (!array_key_exists($dbName, $column_definitions))
				throw new Phpr_SystemException("Column {$modelClass}.{$dbName} cannot be added to a form because it is not defined with define_column method call.");

			$this->_columnDefinition = $column_definitions[$dbName];

			if ($this->_columnDefinition->isReference && !in_array($this->_columnDefinition->referenceType, array('belongs_to', 'has_many', 'has_and_belongs_to_many')))
				throw new Phpr_SystemException( "Error adding form field $dbName. Form fields can only be defined for the belongs_to, has_and_belongs_to_many and has_many relations. {$this->_columnDefinition->referenceType} associations are not supported.");

			$this->dbName = $dbName;
			$this->formSide = $side;
			$this->_model = $model;
		}

		/**
		 * Sets a side of the field on a form.
		 * @param $side Specifies a side. Possible values: left, right, full
		 */
		public function side($side = 'full')
		{
			$this->formSide = $side;
			return $this;
		}

		/**
		 * Specifies a field control rendering mode. Supported modes are:
		 * - frm_text - creates a text field. Default for varchar column types.
		 * - frm_textarea - creates a textarea control. Default for text column types.
		 * - frm_html - creates an HTML WYSIWYG control.
		 * - frm_dropdown - creates a drop-down list. Default for reference-based columns.
		 * - frm_autocomplete - creates an autocomplete field.
		 * - frm_radio - creates a set of radio buttons.
		 * - frm_checkbox - creates a single checkbox.
		 * @param string $renderMode Specifies a render mode as described above
		 * @param array $options A list of render mode specific options.
		 */
		public function renderAs($renderMode, $options = array())
		{
			$this->renderMode = $renderMode;
			$this->renderOptions = $options;
			return $this;
		}

		/**
		 * Specifies a language for code editor fields syntax highlighting. 
		 * @param string $language Specifies a language name. Examples: html, css, php, perl, ruby, sql, xlml
		 */
		public function language($language)
		{
			$this->language = $language;
			return $this;
		}
		
		/**
		 * Specifies a callback function name to be called when user clicks Save button on the text editor toolbar
		 * @param string $callbacl A JavaScript function name
		 */
		public function saveCallback($callback)
		{
			$this->saveCallback = $callback;
			return $this;
		}

		/**
		 * Adds a text comment above or below the field.
		 * @param string $text Specifies a comment text.
		 * @param string $position Specifies a comment position. 
		 * @param bool $commentHTML Set to true if you use HTML formatting in the comment
		 * Supported values are 'below' and 'above'
		 */
		public function comment($text, $position = 'below', $commentHTML = false)
		{
			$this->comment = $text;
			$this->commentPosition = $position;
			$this->commentHTML = $commentHTML;

			return $this;
		}

		/**
		 * Alternative comment text for the preview mode
		 */
		public function previewComment($text)
		{
			$this->previewComment = $text;
			return $this;
		}

		/**
		 * Sets a vertical size for textareas
		 * @param string $size Specifies a size selector. Supported values are 'tiny', 'small', 'large'.
		 */
		public function size($size)
		{
			$this->size = $size;
			return $this;
		}
		
		/**
		 * Sets a textarea text services. Currently supports 'auto_close_brackets'
		 * @param string $services Specifies a list of services, separated with comma
		 */
		public function textServices($services)
		{
			$services = explode(',', $services);
			foreach ($services as &$service)
				$service = "'".trim($service)."'";

			$this->textareaServices = implode(',', $services);
			
			return $this;
		}
		
		/**
		 * Specifies CSS classes to apply to the field container element
		 */
		public function cssClasses($classes)
		{
			$this->cssClasses = $classes;
			return $this;
		}

		/**
		 * Specifies CSS class name to apply to the field LI element
		 */
		public function cssClassName($className)
		{
			$this->cssClassName = $className;
			return $this;
		}
		
		/**
		 * Specifies a select element option text to display before other options. 
		 * Use this method for options like "<please select color>"
		 */
		public function emptyOption($text)
		{
			$this->emptyOption = $text;
			return $this;
		}
		
		/**
		 * Specifies a text to display in multi-relation fields if there are no options available
		 */
		public function noOptions($text)
		{
			$this->noOptions = $text;
			return $this;
		}
		
		/*
		 * Specifies a method name in the model class, responsible for returning 
		 * a list of options for drop-down and radio fields. The method should be defined like this:
		 * public method method_name($db_name, $current_key_value = -1); 
		 * The parameter passed to the method is a database field name
		 * The method must return an array of record values: array(33=>'Red', 34=>'Blue')
		 */
		public function optionsMethod($name)
		{
			$this->optionsMethod = $name;
			return $this;
		}
		
		/*
		 * Specifies a method name in the model class, responsible for returning 
		 * a state of a checkbox in the checknox list. The method should be defined like this:
		 * public method method_name($db_name, $current_key_value = -1); 
		 * The method must return a boolean value
		 */
		public function optionStateMethod($name)
		{
			$this->optionStateMethod = $name;
			return $this;
		}

		/**
		 * Adds a filter SQL expression for reference-type fields.
		 * @param string $expr Specifies an SQL expression. Example: 'status is not null and status = 1'
		 */
		public function referenceFilter($expr)
		{
			$this->referenceFilter = $expr;
			return $this;
		}
		
		/**
		 * Adds an SQL expression to evaluate option descriptions for reference-type fields. 
		 * Option descriptions are supported by the radio button fields.
		 * @param string $expr Specifies an SQL expression. Example 'concat(login_name, ' (', first_name, ' ', last_name, ')')'
		 */
		public function referenceDescriptionField($expr)
		{
			$this->referenceDescriptionField = $expr;
			return $this;
		}
		
		/**
		 * Hides the relation preview button for relation fields.
		 */
		public function previewNoRelation()
		{
			$this->previewNoRelation = true;
			return $this;
		}

		/**
		 * Adds link to a preview field
		 */
		public function previewLink($url)
		{
			$this->previewLink = $url;
			return $this;
		}
		
		/*
		 * Disables a control
		 */
		public function disabled()
		{
			$this->disabled = true;
		}
		
		/**
		 * Sets a text to output on form previews for many-to-many relation 
		 * fields in case if no options were assigned.
		 */
		public function previewNoOptionsMessage($str)
		{
			$this->relationPreviewNoOptions = $str;
			return $this;
		}

		/**
		 * Sets an "on" value for checkbox fields. Default value is 1.
		 */
		public function checkboxOnState($value)
		{
			$this->checkboxOnState = $value;
			return $this;
		}

		/**
		 * Sets a label for the "Add document" link. This method work only with file attachment fields.
		 */
		public function addDocumentLabel($label)
		{
			$this->addAttachmentLabel = $label;
			return $this;
		}
		
		/**
		 * Sets a text to output if there is no files attached. This method work only with file attachment fields.
		 */
		public function noAttachmentsLabel($label)
		{
			$this->noAttachmentsLabel = $label;
			return $this;
		}

		/**
		 * Sets width and height value for image file attachments
		 */
		public function imageThumbSize($size)
		{
			$this->imageThumbSize = $size;
			return $this;
		}

		/**
		 * Adds a sorting expression for reference-type fields.
		 * @param string $expr Specifies an SQL sorting expression. Example: 'name desc'
		 * Notice, that the first model column corresponds the 
		 * reference display value field, so you may use expressions like '1 desc'
		 */
		public function referenceSort($expr)
		{
			$this->referenceSort = $expr;
			return $this;
		}
		
		/**
		 * Determines whether the drop-down option display values should be html-encoded before output.
		 */
		public function optionsHtmlEncode($htmlEncode)
		{
			$this->optionsHtmlEncode = $htmlEncode;
			return $this;
		}
		
		/**
		 * Sets the file attachments field rendering mode
		 * @param string $renderMode Specifies a render mode value. Possible values: 'file_list', 'image_list'
		 */
		public function renderFilesAs($renderMode)
		{
			$this->renderFilesAs = $renderMode;
			return $this;
		}

		/**
		 * Specifies a list of plugins to be loaded into the HTML filed. 
		 * Please refer TinyMCE documentation for details about plugins.
		 * @param string $plugins A list of plugins to load.
		 */
		public function htmlPlugins($plugins)
		{
			if (substr($plugins, 0, 1) != ',')
				$plugins = ', '.$plugins;

			$this->htmlPlugins .= $plugins;
			return $this;
		}
		
		/**
		 * Specifies a list of buttons to be displayed in the 1st row of HTML field toolbar.
		 * Please refer TinyMCE documentation for details about buttons.
		 * @param string $buttons A list of buttons to display.
		 */
		public function htmlButtons1($buttons)
		{
			$this->htmlButtons1 = $buttons;
			return $this;
		}

		/**
		 * Specifies a list of buttons to be displayed in the 2nd row of HTML field toolbar.
		 * Please refer TinyMCE documentation for details about buttons.
		 * @param string $buttons A list of buttons to display.
		 */
		public function htmlButtons2($buttons)
		{
			$this->htmlButtons2 = $buttons;
			return $this;
		}
		
		/**
		 * Specifies a list of buttons to be displayed in the 3rd row of HTML field toolbar.
		 * Please refer TinyMCE documentation for details about buttons.
		 * @param string $buttons A list of buttons to display.
		 */
		public function htmlButtons3($buttons)
		{
			$this->htmlButtons3 = $buttons;
			return $this;
		}
		
		/**
		 * Specifies a custom CSS file to use within the HTML editor (the editable area)
		 * @param string $url Specifies an URL of CSS file
		 */
		public function htmlContentCss($url)
		{
			$this->htmlContentCss = $url;
			return $this;
		}
		
		/**
		 * Specifies a list of block formats to use in the HTML editor formats drop-down menu
		 * @param string $formats Specifies a comma-separated list of formats
		 */
		public function htmlBlockFormats($formats)
		{
			$this->htmlBlockFormats = $formats;
			return $this;
		}
		
		/**
		 * Specifies a list of custom styles to use in the HTML editor styles drop-down menu
		 * @param string $styles Specifies a semicolon-separated list of styles
		 */
		public function htmlCustomStyles($styles)
		{
			$this->htmlCustomStyles = $styles;
			return $this;
		}
		
		/**
		 * Specifies a list of font sizes to use in the HTML editor font sizes drop-down menu
		 * @param string $sizes Specifies a comma-separated list of sizes
		 */
		public function htmlFontSizes($sizes)
		{
			$this->htmlFontSizes = $sizes;
			return $this;
		}
		
		/**
		 * Specifies a list of font colors to use in the HTML editor font color palette
		 * @param string $colors Specifies a comma-separated list of colors: "FF00FF,FFFF00,000000"
		 */
		public function htmlFontColors($colors)
		{
			$this->htmlFontColors = $colors;
			return $this;
		}

		/**
		 * Specifies a list of background colors to use in the HTML editor color palette
		 * @param string $colors Specifies a comma-separated list of colors: "FF00FF,FFFF00,000000"
		 */
		public function htmlBackgroundColors($colors)
		{
			$this->htmlBackgroundColors = $colors;
			return $this;
		}

		/**
		 * This option enables you to disable the "more colors" link in the HTML editor
		 * for the text and background color menus.
		 * @param string $allow Indicates whether the more colors link should be enabled
		 */
		public function htmlAllowMoreColors($allow)
		{
			$this->htmlAllowMoreColors = $allow;
			return $this;
		}
		
		/**
		 * The htmlValidElements option defines which elements will
		 * remain in the edited text when the editor saves.
		 * @param string $value A list of valid elements, as text
		 */
		public function htmlValidElements($value)
		{
			$this->htmlValidElements = $value;
			return $this;
		}
		
		/**
		 * The htmlValidChildElements This option gives you the ability to specify what elements
		 * are valid inside different parent elements.
		 * @param string $value A list of valid child elements, as text
		 */
		public function htmlValidChildElements($value)
		{
			$this->htmlValidChildElements = $value;
			return $this;
		}
		
		/**
		 * Sets file download url for file attachment fields.
		 * @param string $url Specifies an URL
		 */
		public function fileDownloadBaseUrl($url)
		{
			$this->fileDownloadBaseUrl = $url;
			return $this;
		}

		public function getColDefinition()
		{
			return $this->_columnDefinition;
		}
		
		/**
		 * Hides field label
		 */
		public function noLabel()
		{
			$this->noLabel = true;
			return $this;
		}
		
		/**
		 * Suppresses the field value output
		 */
		public function hideContent()
		{
			$this->hideContent = true;
			return $this;
		}
		
		/**
		 * Sets columns for grid fields
		 * @param array $columns Specifies a list of column descriptions as array:
		 * array(
		 *	'country'=>array('title'=>'Country Code', 'align'=>'left', 'autocomplete'=>array('type'=>'local', 'tokens'=>$this->get_country_list())), 
		 *	'state'=>array('title'=>'State Code', 'align'=>'left', 'width'=>'100', 'autocomplete'=>array('type'=>'local', 'depends_on'=>'country', 'tokens'=>$this->get_state_list())), 
		 *	'zip'=>array('title'=>'ZIP', 'align'=>'left', 'width'=>'60', 'autocomplete'=>array('type'=>'local', 'tokens'=>array('* - Any ZIP||*'), 'autowidth'=>true)) 
		 *	'read_only_col'=>array('title'=>'Read only', 'read_only'=>true)
		 * )
		 */
		public function gridColumns($columns)
		{
			$this->gridColumns = $columns;
			return $this;
		}
		
		/**
		 * Manages the grid control settings
		 * @param array $settings Specifies the grid settings. Example:
		 * array('no_toolbar'=>true, 'allow_adding_rows'=>false, 'allow_deleting_rows'=>false, 'no_sorting'=>false, 'data_index_is_key')
		 */
		public function gridSettings($settings)
		{
			$this->gridSettings = $settings;
			return $this;
		}
		
		/**
		 * Convert new lines to <br/> in the preview mode.
		 * This method works only with text areas
		 */
		public function nl2br($value)
		{
			$this->nl2br = $value;
			return $this;
		}
		
		/**
		 * Sets the field position on the form. For fields without any position 
		 * specified, the position is calculated automatically, basing on the 
		 * add_form_field() method call order. For the first field the sort order
		 * value is 10, for the second field it is 20 and so on.
		 * @param int $value Specifies a form position.
		 */
		public function sortOrder($value)
		{
			$this->sortOrder = $value;
			return $this;
		}
		
		/**
		 * Allows to render a specific partial below the form label
		 */
		public function titlePartial($partial_name)
		{
			$this->titlePartial = $partial_name;
			return $this;
		}

		/**
		 * Allows to render a specific form element partial instead of the standard field type specific input field.
		 */
		public function formElementPartial($partial_name)
		{
			$this->formElementPartial = $partial_name;
			return $this;
		}
		
		/**
		 * Adds a help message to the field preview section
		 */
		public function previewHelp($string)
		{
			$this->previewHelp = $string;
			return $this;
		}
		
		/**
		 * Adds a help message to the field comment
		 */
		public function commentTooltip($string)
		{
			$this->commentTooltip = $string;
			return $this;
		}
		
		/**
		 * Makes HTML fields full-width
		 */
		public function htmlFullWidht($value)
		{
			$this->htmlFullWidht = $value;
			return $this;
		}
		
		/**
		 * Returns column validation object
		 */
		public function validation()
		{
			return $this->_columnDefinition->validation();
		}
	}

?>