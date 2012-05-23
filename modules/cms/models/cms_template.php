<?php

	class Cms_Template extends Cms_Object
	{
		public $table_name = 'templates';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		
		public $no_file_copy = false;
		protected $api_added_columns = array();
		protected static $templates_dir_name = null;
		
		public static function create()
		{
			return new self();
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the template name.");

			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
				$this->define_column('file_name', 'File Name')->validation()->fn('trim')->required("Please specify the file name.")->
					regexp('/^[a-z_0-9-;]*$/i', 'File name can only contain latin characters, numbers, dashes, underscores and semi-colons.')->
					fn('strtolower')->unique('File name "%s" already used by another template. Please use another file name.', array($this, 'configure_unique_validator'));
			
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('html_code', 'HTML Code')->invisible()->validation()->required('Please specify the template HTML code.');
			
			$this->defined_column_list = array();
			Backend::$events->fireEvent('cms:onExtendTemplateModel', $this, $context);
			$this->api_added_columns = array_keys($this->defined_column_list);
		}
		
		public function define_form_fields($context = null)
		{
			$settings_manager = Cms_SettingsManager::get();
			if (!$settings_manager->enable_filebased_templates)
				$this->add_form_field('name')->collapsable();
			else
			{
				$this->add_form_field('name', 'left')->collapsable();
				$this->add_form_field('file_name', 'right')->collapsable();
			}

			$this->add_form_field('description')->renderAs(frm_textarea)->size('small')->collapsable();
			$this->add_form_field('html_code')->size('giant')->cssClasses('code')->renderAs(frm_code_editor)->language('php')->saveCallback('save_code');
			
			Backend::$events->fireEvent('cms:onExtendTemplateForm', $this, $context);
			
			foreach($this->api_added_columns as $column_name) {
				$form_field = $this->find_form_field($column_name);
				
				if($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1) {
			$result = Backend::$events->fireEvent('cms:onGetTemplateFieldOptions', $db_name, $current_key_value);
			
			foreach($result as $options) {
				if(is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public function before_delete($id=null) 
		{
			$isInUse = Db_DbHelper::scalar(
				'select count(*) from pages where template_id=:id', 
				array('id'=>$this->id)
			);
			
			if ($isInUse)
				throw new Phpr_ApplicationException("Unable to delete template: there are pages ($isInUse) which use it.");
			
			Backend::$events->fireEvent('cms:onDeleteTemplate', $this);
		}
		
		public function after_delete()
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates && $settings_manager->templates_directory_is_writable() && $this->file_name)
				$this->delete_file($this->file_name);
		}
		
		/**
		 * Copies the template to a file
		 */
		public function copy_to_file($templates_dir = null)
		{
			if ($this->no_file_copy)
			{
				if ($this->file_name)
					$this->save_file_name_to_db($this->file_name);

				return;
			}
			
			$file_name = $this->file_name ? $this->file_name : $this->create_file_name();
			
			try
			{
				$this->save_to_file(
					$this->html_code, 
					$this->get_file_path($file_name)
				);
			} catch (exception $ex)
			{
				throw new Phpr_ApplicationException('Error saving template '.$this->name.' to file. '.$ex->getMessage());
			}
			
			if (!$this->file_name)
				$this->save_file_name_to_db($file_name);
		}
		
		protected function save_file_name_to_db($file_name)
		{
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);
			Db_DbHelper::query('update templates set file_name=:file_name where id=:id', array('file_name'=>$file_name, 'id'=>$this->id));
		}
		
		/**
		 * Returns an absolute path to the object file
		 */
		public function get_file_path($file_name)
		{
			if (!$file_name)
				return null;
				
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);

			$settings_manager = Cms_SettingsManager::get();
			return $settings_manager->get_templates_dir_path($this->get_theme()).'/'.self::get_templates_dir_name($this->get_theme()).'/'.$file_name.'.'.self::get_content_extension();
		}
		
		protected static function get_templates_dir_name($theme)
		{
			$theme_id = $theme ? $theme->id : -1;
			
			if (isset(self::$templates_dir_name[$theme_id]))
				return self::$templates_dir_name[$theme_id];

			$settings_manager = Cms_SettingsManager::get();
			if (file_exists($settings_manager->get_templates_dir_path($theme).'/templates'))
				return self::$templates_dir_name[$theme_id] = 'templates';
			
			return self::$templates_dir_name[$theme_id] = 'layouts';
		}
		
		public function create_file_name($add_extension = true)
		{
			$templates_dir = Cms_SettingsManager::get()->get_templates_dir_path($this->get_theme());
			$result = $this->generate_unique_file_name(self::db_name_to_file_name($this->name), $templates_dir.'/'.self::get_templates_dir_name($this->get_theme()).'/', self::get_content_extension());
			
			if ($add_extension)
				return $result;
			
			return pathinfo($result, PATHINFO_FILENAME);
		}
		
		/**
		 * Converts template DB name to a file name
		 */
		protected static function db_name_to_file_name($name)
		{
			$name = mb_strtolower($name);
			return preg_replace('/[^a-z_0-9\-]/i', '_', $name);
		}

		/**
		 * Converts template file name to a DB name
		 */
		protected static function file_name_to_db_name($file_name)
		{
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);
			return Phpr_Inflector::humanize($file_name);
		}
		
		/**
		 * Returns the template content string
		 */
		public function get_content()
		{
			try
			{
				$content = $this->html_code;
				$settings_manager = Cms_SettingsManager::get();
			
				if ($settings_manager->enable_filebased_templates)
				{
					if (!$this->file_name)
						throw new Phpr_ApplicationException('Template file is not specified');
						
					$path = $this->get_file_path($this->file_name);
						
					if (!file_exists($path))
						throw new Phpr_ApplicationException('Template file not found: '.$path);
					
					$content = file_get_contents($path);
				}
				
				$result = Backend::$events->fire_event(array('name' => 'cms:onGetTemplateContent', 'type' => 'filter'), array(
					'name' => $this->name, 
					'content' => $content,
					'file_name' => $this->file_name, 
					'file_based' => $settings_manager->enable_filebased_templates
				));
				
				return $result['content'];
			}
			catch (exception $ex)
			{
				throw new Phpr_ApplicationException('Error rendering template '.$this->name.'. '.$ex->getMessage());
			}
		}
		
		/**
		 * Loads template content form the template file into the model
		 */
		public function load_file_content()
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
			{
				$path = $this->get_file_path($this->file_name);
				if ($path && file_exists($path))
					$this->html_code = file_get_contents($path);
			}
		}
		
		public function after_save()
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
			{
				if (isset($this->fetched['file_name']) && $this->fetched['file_name'] != $this->file_name)
					$this->delete_file($this->fetched['file_name']);

				$this->copy_to_file();
			}
		}
		
		public static function auto_create_from_files()
		{
			if (!Cms_SettingsManager::get()->enable_filebased_templates)
				return null;

			$settings_manager = Cms_SettingsManager::get();
			
			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;
			
			$dir = $settings_manager->get_templates_dir_path($current_theme).'/'.self::get_templates_dir_name($current_theme);
			if (file_exists($dir) && is_dir($dir))
			{
				$theme_filter = $current_theme ? ' where theme_id='.$current_theme->id : null;
				
				$existing_templates = Db_DbHelper::objectArray('select file_name, lower(name) as name from templates'.$theme_filter);
				$existing_names = array();
				$existing_files = array();
				foreach ($existing_templates as $template)
				{
					$existing_names[] = $template->name;
					$existing_files[] = $template->file_name.'.'.self::get_content_extension();
				}

				$files = scandir($dir);
				foreach ($files as $file)
				{
					if (!self::is_valid_file_name($file))
						continue;

					$template_name = self::file_name_to_db_name($file);

					if (
						!in_array($file, $existing_files) &&
						!in_array($template_name, $existing_names)
					)
					{
						try
						{
							self::create_from_file($dir.'/'.$file, $template_name);
						}
						catch (exception $ex) {}
					}
				}
			}
		}
		
		/**
		 * Creates a template from file and assigns it a specific name.
		 */
		protected static function create_from_file($file_path, $name)
		{
			$current_theme_id = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme_id = $theme->id;
			
			$obj = self::create();
			$obj->name = $name;
			$obj->file_name = pathinfo($file_path, PATHINFO_FILENAME);
			$obj->no_file_copy = true;
			$obj->html_code = file_get_contents($file_path);
			$obj->theme_id = $current_theme_id;
			$obj->save();

			return $obj;
		}
		
		/**
		 * Assigns file name to an existing template
		 */
		public function assign_file_name($file_name)
		{
			$file_name = $this->validate_file_name($file_name);
			
			$in_use = Db_DbHelper::scalar(
				'select count(*) from templates where id <> :id and lower(file_name)=:file_name and ifnull(theme_id, 0)=ifnull(:theme_id, 0)', 
				array('id'=>$this->id, 'file_name'=>$file_name, 'theme_id'=>$this->theme_id));
				
			if ($in_use)
				throw new Phpr_ApplicationException('The file name is already in use.');

			parent::assign_file_name($file_name);
		}
		
		public function copy_from_file()
		{
			$path = $this->get_file_path($this->file_name);
			if ($path && file_exists($path))
			{
				Db_DbHelper::query('update templates set html_code=:content where id=:id', array(
					'content'=>file_get_contents($path),
					'id'=>$this->id
				));
			}
		}
		
		/**
		 * Returns TRUE if the template file cannot be found
		 */
		public function file_is_missing()
		{
			$settings_manager = Cms_SettingsManager::get();
			if (!$settings_manager->enable_filebased_templates)
				return false;

			$path = $this->get_file_path($this->file_name);
			return !file_exists($path);
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Cms_Module::update_cms_content_version();
		}
		
		public static function update_content_file_extension($templates_dir, $old, $new)
		{
			self::change_dir_file_extensions($templates_dir.'/templates', $old, $new);
			self::change_dir_file_extensions($templates_dir.'/layouts', $old, $new);
		}
	}

?>
