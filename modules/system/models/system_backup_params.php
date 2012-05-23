<?php
	class System_Backup_Params extends Db_ActiveRecord 
	{
		protected static $loadedInstance = null;
		
		public $table_name = 'system_backup_settings';
		
		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}
		
		public static function isConfigured()
		{
			$obj = self::get();
			if (!$obj)
				return false;
				
			return strlen($obj->backup_path);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('backup_path', 'Path to save backup files')->validation()->fn('trim')->required()->method('validatePath');
			$this->define_column('backup_on_login', 'Backup on Login');
			$this->define_column('backup_interval', 'Backup Interval');
			$this->define_column('num_files_to_keep', 'Archives To Keep');
			$this->define_column('archive_uploaded_dir', 'Archive uploaded files');
			$this->define_column('notify_administrators', 'Notify On Errors');
		}
		
		public function define_form_fields($context = null)
		{
			$this->add_form_field('backup_path')->comment('Please specify an absolute path to the backup directory. The path should start from the disk root, for example: /home/user/backups.<br/><br/>Your LemonStand installation directory is '.PATH_APP, 'above', true);

			$this->add_form_field('archive_uploaded_dir')->comment('Enable this option if you want to include uploaded files (website resource files - images, CSS, JavaScript files, product and category images, etc.) to backup archives. Enabling this option can significantly increase the backup file size and the server load during the archiving procedure. When this option is disabled LemonStand archives only the database content.');

			$extraFieldClass = $this->backup_on_login ? 'separatedField' : null;
			$this->add_form_field('backup_on_login')->renderAs(frm_onoffswitcher)->comment('Enable this option if you want LemonStand to create backup archives automatically when users log into the Administration Area.', 'above')->cssClassName($extraFieldClass);

			$extraFieldClass = 'auto_backup_field';
			if (!$this->backup_on_login)
				$extraFieldClass .= ' hidden';

			$this->add_form_field('backup_interval', 'left')->renderAs(frm_dropdown)->cssClassName($extraFieldClass);
			$this->add_form_field('num_files_to_keep', 'right')->renderAs(frm_dropdown)->cssClassName($extraFieldClass);
			
			$this->add_form_field('notify_administrators')->comment('Send email message to system administrators in case of fails in regular archiving.')->cssClassName($extraFieldClass);
		}
		
		public function get_backup_interval_options($keyValue=-1)
		{
			$result = array();
			$result[1] = 'Daily';
			$result[2] = 'Weekly';
			$result[3] = 'Monthly';

			return $result;
		}
		
		public function get_num_files_to_keep_options($keyValue=-1)
		{
			$result = array();
			$result[0] = 'Never delete';
			
			$result[12] = 'Keep last 2';
			$result[14] = 'Keep last 4';
			$result[16] = 'Keep last 6';
			$result[1] = 'Keep last 10';
			$result[15] = 'Keep last 15';
			$result[2] = 'Keep last 20';
			$result[3] = 'Keep last 50';

			return $result;
		}

		protected function validatePath($name, $value)
		{
			$value = trim($value);
			if (substr($value, 0, 1) != '/' && !preg_match('|^[a-z]:/|i', $value))
				$this->validation->setError("Please specify an absolute path. On Unix based systems absolute paths should begin with a slash. For example: /home/user/backups.", $name, true);
			
			if (!file_exists($value))
				$this->validation->setError("Path '$value' does not exist. Please create corresponding directories.", $name, true);
			
			if (!is_dir($value))
				$this->validation->setError("Path '$value' points to a file. Please specify a path to a directory.", $name, true);
			
			if (!is_writable($value))
				$this->validation->setError("Path '$value' is not writable. Please provide writing permissions for the Apache user for this directory.", $name, true);

			return true;
		}
		
		public static function validateParams()
		{
			if (!self::isConfigured())
				throw new Phpr_ApplicationException('Backup system is not configured.');

			$obj = self::get();
			$value = $obj->backup_path;

			if (!file_exists($value))
				throw new Phpr_ApplicationException("Path for saving backup archives '$value' does not exist.");
			
			if (!is_dir($value))
				throw new Phpr_ApplicationException("Path for saving backup archives '$value' points to a file. Please specify a path to a directory.");
			
			if (!is_writable($value))
				throw new Phpr_ApplicationException("Path for saving backup archives '$value' is not writable. Please provide write permissions for Apache user for this directory.");
		}
	}
?>