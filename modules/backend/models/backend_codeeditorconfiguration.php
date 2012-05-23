<?

	class Backend_CodeEditorConfiguration extends Core_Configuration_Model
	{
		public $record_code = 'code_editor_configuration';
		public $is_personal = true;
		
		public static function create()
		{
			$configObj = new Backend_CodeEditorConfiguration();
			return $configObj->load();
		}
		
		protected function build_form()
		{
			$this->add_field('font_size', 'Font size', 'left', db_number)->renderAs(frm_dropdown);
			$this->add_field('soft_wrap', 'Soft word wrap', 'right', db_varchar)->renderAs(frm_dropdown);
			$this->add_field('code_folding', 'Code folding', 'left', db_varchar)->renderAs(frm_dropdown);
			$this->add_field('tab_size', 'Tab size', 'right', db_number)->renderAs(frm_dropdown);
			$this->add_field('color_theme', 'Color theme', 'full', db_varchar)->renderAs(frm_dropdown);
			$this->add_field('highlight_active_line', 'Highlight active line', 'full', db_bool);
			$this->add_field('show_invisibles', 'Show invisibles', 'full', db_bool);
			$this->add_field('show_gutter', 'Show gutter', 'full', db_bool);
			$this->add_field('soft_tabs', 'Soft tabs', 'full', db_bool);
			$this->add_field('show_print_margin', 'Show print margin', 'full', db_bool);
		}
		
		public function render_settings()
		{
			$result = array(
				'showInvisibles'=>$this->show_invisibles == 1,
				'highlightActiveLine'=>$this->highlight_active_line == 1,
				'showGutter'=>$this->show_gutter == 1,
				'showPrintMargin'=>$this->show_print_margin == 1,
				'useSoftTabs'=>$this->soft_tabs == 1,
				'tabSize'=>(int)$this->tab_size,
				'fontSize'=>(int)$this->font_size,
				'theme'=>$this->color_theme,
				'folding'=>$this->code_folding,
				'wrapMode'=>$this->soft_wrap
			);
			
			return json_encode($result);
		}
		
		public function get_font_size_options()
		{
			return array(
				11 => '11px',
				12 => '12px',
				13 => '13px',
				14 => '14px'
			);
		}

		public function get_soft_wrap_options()
		{
			return array(
				'off' => 'Off',
				'40' => '40 chars',
				'80' => '80 chars',
				'free' => 'Free'
			);
		}
		
		public function get_tab_size_options()
		{
			return array(
				2=>2,
				3=>3,
				4=>4,
				5=>5,
				6=>6,
				7=>7,
				8=>8
			);
		}
		
		public function get_code_folding_options()
		{
			return array(
				'manual' => 'Off',
				'markbegin' => 'Mark begin',
				'markbeginend' => 'Mark begin and end'
			);
		}
		
		public function get_color_theme_options()
		{
			$options = array(
				'clouds'=>'Clouds',
				'clouds_midnight'=>'Clouds midnight',
				'cobalt'=>'Cobalt',
				'crimson_editor'=>'Crimson editor',
				'dawn'=>'Dawn',
				'eclipse'=>'Eclipse',
				'idle_fingers'=>'Idle fingers',
				'kr_theme'=>'Kr theme',
				'merbivore'=>'Merbivore',
				'merbivore_soft'=>'Merbivore soft',
				'mono_industrial'=>'Mono industrial',
				'monokai'=>'Monokai',
				'pastel_on_dark'=>'Pastel on dark',
				'solarized_dark'=>'Solarized dark',
				'solarized_light'=>'Solarized light',
				'textmate'=>'Textmate',
				'twilight'=>'Twilight',
				'vibrant_ink'=>'Vibrant ink',
				'chrome'=>'Chrome',
				'dreamweaver'=>'Dreamweaver',
				'tomorrow'=>'Tomorrow',
				'tomorrow_night'=>'Tomorrow night',
				'tomorrow_night_blue'=>'Tomorrow night blue',
				'tomorrow_night_bright'=>'Tomorrow night bright',
				'tomorrow_night_eighties'=>'Tomorrow night eighties',
			);
			
			asort($options);
			
			return $options;
		}
		
		protected function init_config_data()
		{
			$this->font_size = 12;
			$this->color_theme = 'textmate';
			$this->highlight_active_line = true;
			$this->show_invisibles = true;
			$this->show_gutter = true;
			$this->tab_size = 4;
			$this->show_print_margin = true;
			$this->code_folding = 'manual';
		}
	}

?>