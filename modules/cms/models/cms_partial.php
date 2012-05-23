<?php

	class Cms_Partial extends Cms_Object
	{
		public $table_name = 'partials';
		
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		
		protected static $partial_cache = null;
		protected static $partial_file_cache = array();
		protected static $file_existence_cache = null;
		protected $api_added_columns = array();
		
		public $no_file_copy = false;

		public static function create()
		{
			return new self();
		}

		public function define_columns($context = null)
		{
			$this->define_column('name', 'Name')->order('asc')->validation()->fn('trim')->required("Please specify the partial name.")->
				regexp('/^[a-z_0-9:]*$/i', 'Partial name can only contain latin characters, numbers, colons and underscores.')->
				fn('strtolower')->unique('Name "%s" already used by another partial. Please use another name.', array($this, 'configure_unique_validator'));
			$this->define_column('description', 'Description')->validation()->fn('trim');
			$this->define_column('html_code', 'HTML Code')->invisible()->validation()->required();
			
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
				$this->define_column('file_name', 'File Name')->validation()->fn('trim')->required("Please specify the file name.")->
					regexp('/^[a-z_0-9-;]*$/i', 'File name can only contain latin characters, numbers, dashes, underscores and semi-colons.')->
					fn('strtolower')->unique('File name "%s" already used by another partial. Please use another file name.', array($this, 'configure_unique_validator'));
					
			$this->define_column('description', 'Description')->validation()->fn('trim');

			$this->defined_column_list = array();
			Backend::$events->fireEvent('cms:onExtendPartialModel', $this, $context);
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
			
			$this->add_form_field('description')->renderAs(frm_textarea)->size('tiny')->collapsable();
			
			$this->add_form_field('html_code')->size('giant')->cssClasses('code')->renderAs(frm_code_editor)->language('php')->saveCallback('save_code');
			
			Backend::$events->fireEvent('cms:onExtendPartialForm', $this, $context);
			
			foreach($this->api_added_columns as $column_name) {
				$form_field = $this->find_form_field($column_name);
				
				if($form_field)
					$form_field->optionsMethod('get_added_field_options');
			}
		}
		
		public function get_added_field_options($db_name, $current_key_value = -1) {
			$result = Backend::$events->fireEvent('cms:onGetPartialFieldOptions', $db_name, $current_key_value);
			
			foreach($result as $options) {
				if(is_array($options) || (strlen($options && $current_key_value != -1)))
					return $options;
			}
			
			return false;
		}
		
		public static function find_by_name($name)
		{
			if (self::$partial_cache == null)
			{
				self::$partial_cache = array();
				
				if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
					$partials = Db_DbHelper::objectArray("select * from partials where theme_id=:theme_id", array('theme_id'=>$theme->id));
				else
					$partials = Db_DbHelper::objectArray("select * from partials");

				foreach ($partials as $partial)
					self::$partial_cache[$partial->name] = $partial;
			}

			if (array_key_exists($name, self::$partial_cache))
				return self::$partial_cache[$name];

			$obj = self::auto_create_from_file($name);
			if ($obj)
				return self::$partial_cache[$name] = $obj;

			return null;
		}
		
		public function before_delete($id=null) 
		{
			Backend::$events->fireEvent('cms:onDeletePartial', $this);
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

		public function after_delete()
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates && $settings_manager->templates_directory_is_writable() && $this->file_name)
				$this->delete_file($this->file_name);
		}
		
		/**
		 * Converts partial DB name to a file name
		 */
		protected static function db_name_to_file_name($name)
		{
			return mb_strtolower(str_replace(':', ';', $name));
		}

		/**
		 * Converts partial file name to a DB name
		 */
		protected static function file_name_to_db_name($file_name)
		{
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);
			
			$result = str_replace(';', ':', $file_name);
			$result = preg_replace('/[^a-z_0-9:]/', '_', $result);
			
			return mb_strtolower($result);
		}

		/**
		 * Copies the partial to a file
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
				throw new Phpr_ApplicationException('Error saving partial '.$this->name.' to file. '.$ex->getMessage());
			}
			
			if (!$this->file_name)
				$this->save_file_name_to_db($file_name);
		}
		
		public function create_file_name($add_extension = true)
		{
			$templates_dir = Cms_SettingsManager::get()->get_templates_dir_path($this->get_theme());
			$result = $this->generate_unique_file_name(self::db_name_to_file_name($this->name), $templates_dir.'/partials/', self::get_content_extension());
			
			if ($add_extension)
				return $result;
			
			return pathinfo($result, PATHINFO_FILENAME);
		}
		
		protected function save_file_name_to_db($file_name)
		{
			$file_name = pathinfo($file_name, PATHINFO_FILENAME);
			Db_DbHelper::query('update partials set file_name=:file_name where id=:id', array('file_name'=>$file_name, 'id'=>$this->id));
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
			return $settings_manager->get_templates_dir_path($this->get_theme()).'/partials/'.$file_name.'.'.self::get_content_extension();
		}
		
		/**
		 * Returns the partial content string. This method uses the cached partial data.
		 */
		public static function get_content($name, &$content, $file_name)
		{
			try
			{
				$settings_manager = Cms_SettingsManager::get();
				
				if ($settings_manager->enable_filebased_templates)
				{
					if (!$file_name)
						throw new Phpr_ApplicationException('File is not specified');
						
					if (array_key_exists($file_name, self::$partial_file_cache))
						return self::$partial_file_cache[$file_name];
					
					$tmp = new self();
					if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
						$tmp->theme_id = $theme->id;

					$path = $tmp->get_file_path($file_name);

					if (!file_exists($path))
						throw new Phpr_ApplicationException('File not found '.$path);

					$content = self::$partial_file_cache[$file_name] = file_get_contents($path);
				}
				
				$result = Backend::$events->fire_event(array('name' => 'cms:onGetPartialContent', 'type' => 'filter'), array(
					'name' => $name, 
					'content' => $content,
					'file_name' => $file_name, 
					'file_based' => $settings_manager->enable_filebased_templates
				));
				
				return $result['content'];
			}
			catch (exception $ex)
			{
				throw new Phpr_ApplicationException('Error rendering partial '.$name.'. '.$ex->getMessage());
			}
		}
		
		/**
		 * Returns the partial content string.
		 */
		public function get_content_code()
		{
			$settings_manager = Cms_SettingsManager::get();
			if ($settings_manager->enable_filebased_templates)
			{
				if (!$this->file_name)
					throw new Phpr_ApplicationException('Partial file is not specified for the '.$this->name.' partial');
					
				$path = $this->get_file_path($this->file_name);
					
				if (!file_exists($path))
					throw new Phpr_ApplicationException('Partial file not found: '.$path);
				
				return file_get_contents($path);
			}

			return $this->html_code;
		}
		
		/**
		 * Loads partial content form the partial file into the model
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

		/**
		 * Tries to create a partial with a specific name from file.
		 */
		protected static function auto_create_from_file($name)
		{
			if (!Cms_SettingsManager::get()->enable_filebased_templates)
				return null;

			$theme_id = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_active_theme()))
				$theme_id = $theme->id;

			$file_name = self::db_name_to_file_name($name).'.'.self::get_content_extension();
			$tmp = new self();
			$tmp->theme_id = $theme_id;
			$path = $tmp->get_file_path($file_name);

			if (!file_exists($path))
				return null;

			$existing_files = Db_DbHelper::scalarArray('select file_name as name from partials');
			if (in_array($file_name, $existing_files))
				return null;

			try
			{
				$obj = self::create_from_file($path, $name);

				$result = array(
					'id'=>$obj->id,
					'name'=>$obj->name,
					'html_code'=>$obj->html_code,
					'file_name'=>$obj->file_name,
					'theme_id'=>$theme_id
				);

				return (object)$result;
			}
			catch (exception $ex) {}

			return null;
		}
		
		/**
		 * Creates a partial from file and assigns it a specific name.
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
		
		public static function auto_create_from_files()
		{
			if (!Cms_SettingsManager::get()->enable_filebased_templates)
				return null;

			$settings_manager = Cms_SettingsManager::get();
			
			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;
			
			$dir = $settings_manager->get_templates_dir_path($current_theme).'/partials';
			if (file_exists($dir) && is_dir($dir))
			{
				$theme_filter = $current_theme ? ' where theme_id='.$current_theme->id : null;
				
				$existing_partials = Db_DbHelper::objectArray('select file_name, lower(name) as name from partials'.$theme_filter);
				$existing_names = array();
				$existing_files = array();
				foreach ($existing_partials as $partial)
				{
					$existing_names[] = $partial->name;
					$existing_files[] = $partial->file_name.'.'.self::get_content_extension();
				}

				$files = scandir($dir);
				foreach ($files as $file)
				{
					if (!self::is_valid_file_name($file))
						continue;
					
					$partial_name = self::file_name_to_db_name($file);
					if (
						!in_array($file, $existing_files) &&
						!in_array($partial_name, $existing_names)
					)
					{
						try
						{
							self::create_from_file($dir.'/'.$file, $partial_name);
						}
						catch (exception $ex) {}
					}
				}
			}
		}

		/**
		 * Returns TRUE if the partial file cannot be found
		 */
		public function file_is_missing()
		{
			$settings_manager = Cms_SettingsManager::get();
			if (!$settings_manager->enable_filebased_templates)
				return false;

			self::init_existing_file_cache();

			return !array_key_exists($this->id, self::$file_existence_cache) || !self::$file_existence_cache[$this->id];
		}
		
		protected static function init_existing_file_cache()
		{
			$settings_manager = Cms_SettingsManager::get();

			if (self::$file_existence_cache !== null)
				return;

			self::$file_existence_cache = array();
			
			$current_theme = null;
			if (Cms_Theme::is_theming_enabled() && ($theme = Cms_Theme::get_edit_theme()))
				$current_theme = $theme;

			$dir = $settings_manager->get_templates_dir_path($current_theme).'/partials';
			if (file_exists($dir) && is_dir($dir))
			{
				$files = scandir($dir);
				$partials = Db_DbHelper::objectArray('select id, file_name from partials');
				foreach ($partials as $partial)
					self::$file_existence_cache[$partial->id] = in_array($partial->file_name.'.'.self::get_content_extension(), $files);
			}
		}
		
		/**
		 * Assigns file name to an existing partial
		 */
		public function assign_file_name($file_name)
		{
			$file_name = $this->validate_file_name($file_name);
				
			$in_use = Db_DbHelper::scalar(
				'select count(*) from partials where id <> :id and lower(file_name)=:file_name and ifnull(theme_id, 0)=ifnull(:theme_id, 0)', 
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
				Db_DbHelper::query('update partials set html_code=:content where id=:id', array(
					'content'=>file_get_contents($path),
					'id'=>$this->id
				));
			}
		}
		
		public function after_modify($operation, $deferred_session_key)
		{
			Cms_Module::update_cms_content_version();
		}
		
		public static function update_content_file_extension($templates_dir, $old, $new)
		{
			$partials_dir = $templates_dir.'/partials';
			self::change_dir_file_extensions($partials_dir, $old, $new);
		}
	}

?>
