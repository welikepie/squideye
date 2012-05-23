<?php

	class System_Backup_Archive extends Db_ActiveRecord 
	{
		protected static $loadedInstance = null;
		
		public $table_name = 'system_backup_archives';
		public $implement = 'Db_AutoFootprints';
		public $auto_footprints_visible = true;
		public $auto_footprints_created_at_name = 'Created';
		public $auto_footprints_created_user_name = 'Created By';
		public $auto_footprints_user_not_found_name = 'system';
		
		public $manual_mode = true;
		
		public $archive_name_prefix = null;
		
		public $belongs_to = array(
			'status'=>array('class_name'=>'System_Backup_Status')
		);

		public $custom_columns = array('archive_uploaded_dir'=>db_bool);
		public $archive_uploaded_dir = false;

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('id', '#')->order('desc');
			$this->define_relation_column('status', 'status', 'Status', db_varchar, '@name');
			$this->define_column('path', 'Path')->validation()->fn('trim');
			$this->define_column('comment', 'Comments')->validation()->fn('trim');
			$this->define_column('archive_uploaded_dir', 'Archive uploaded files')->invisible();
			$this->define_column('error_message', 'Error');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('created_at', 'left')->noForm();
			$this->add_form_field('status', 'right')->noForm()->previewNoRelation();
			
			if ($this->status_id == 3)
				$this->add_form_field('error_message')->noForm();
			
			if (strlen($this->comment) || $this->is_new_record())
				$this->add_form_field('comment')->size('small')->comment('Optionally you may comment the archive.', 'above')->previewComment('Archive comment');

			if ($this->is_new_record())
				$this->add_form_field('archive_uploaded_dir')->comment('Enable this option if you want to include uploaded files (website resource files - images, CSS, JavaScript files, product and category images, etc.) to the archive. Enabling this option can significantly increase the backup file size. When this option is disabled LemonStand archives only the database content.');

			if ($this->status_id == 2)
				$this->add_form_field('path')->noForm();
		}
		
		public function before_create($deferred_session_key = null)
		{
			if ($this->manual_mode)
			{
				System_Backup_Params::validateParams();

				$file_path = self::genArchivePath($this->archive_name_prefix);
				self::createDumpArchive($file_path, array('archive_uploaded_dir'=>$this->archive_uploaded_dir));
				$this->path = $file_path;
				$this->status_id = 2;
			} else
				$this->status_id = 1;
		}
		
		public function after_delete()
		{
			if (@file_exists($this->path))
				@unlink($this->path);
		}
		
		protected static function genArchivePath($archive_name_prefix = null)
		{
			$nowDate = Phpr_Date::userDate(Phpr_DateTime::now())->format('%Y-%m-%d-%H-%M-%S');
			$params = System_Backup_Params::get();

			$base_path = $params->backup_path.'/'.$archive_name_prefix.$nowDate;
			$file_path = $base_path.'-1.lar';
			$counter = 1;
			while (file_exists($file_path))
			{
				$file_path = $base_path.'-'.$counter.'.lar';
				$counter++;
			}
			
			return $file_path;
		}

		public static function backup($throwOnError = false, $force = false)
		{
			try
			{
				Backend::$events->fireEvent('core:onBeforeArchiveCreate');
				
				@set_time_limit(3600);
				
				if (!System_Backup_Params::isConfigured())
					return;
			
				$currentTime = Phpr_DateTime::now()->toSqlDateTime();

				$backupInProgress = Db_DbHelper::scalar(
					'select count(*) from system_backup_archives where date_add(created_at, interval 5 MINUTE) > :curentTime and status_id=1', 
					array('curentTime'=>$currentTime)
				);

				if ($backupInProgress)
					return;

				$params = System_Backup_Params::get();

				$lastRecord = self::create()->order('id desc')->where('status_id=2')->find();
				if ((!$lastRecord && $params->backup_on_login) || $force)
					self::createBackup($throwOnError);
				elseif ($params->backup_on_login)
				{
					$interval = Phpr_DateTime::now()->substractDateTime($lastRecord->created_at);
				
					$dayIntervals = array(1=>1, 2=>7, 3=>30);
					if (!array_key_exists($params->backup_interval, $dayIntervals))
						return;

					if ($dayIntervals[$params->backup_interval] <= $interval->getDaysTotal())
						self::createBackup($throwOnError);
				}
			}
			catch (Exception $ex) 
			{
				if ($throwOnError)
					throw $ex;
			}
		}
		
		protected static function deleteStaleFiles()
		{
			try
			{
				$params = System_Backup_Params::get();
				if ($params->num_files_to_keep == 0)
					return;

				$fileNumbers = array(1=>10, 2=>20, 3=>50, 12=>2, 14=>4, 16=>6, 15=>15);
				if (!array_key_exists($params->num_files_to_keep, $fileNumbers))
					return;

				$lastFileIndex = $fileNumbers[$params->num_files_to_keep]-1;

				$lastRecord = Db_DbHelper::object(
					"select * from system_backup_archives where status_id=2 order by id desc limit $lastFileIndex, 1"
				);
			
				if (!$lastRecord)
					return;

				$records = self::create()->order('id desc')->where('id < ?', $lastRecord->id)->find_all();
				foreach ($records as $record)
				{
					$record->delete();
				}
			} 
			catch (Exception $ex) {}
		}

		protected static function createBackup($throwOnError = false)
		{
			$obj = self::create();

			try
			{
				/*
				 * Create record
				 */
				$obj->manual_mode = false;
				$file_path = self::genArchivePath();
				$obj->path = $file_path;
				$obj->save();
				
				/*
				 * Create dump
				 */
				System_Backup_Params::validateParams();
				self::createDumpArchive($file_path);
				$obj->status_id = 2;
				$obj->save();
				
				/*
				 * Delete stale files
				 */
				self::deleteStaleFiles();
			}
			catch (Exception $ex)
			{
				$obj->status_id = 3;
				$obj->error_message = $ex->getMessage();
				$obj->save();
				
				$params = System_Backup_Params::get();
				if ($params && $params->notify_administrators)
				{
					$administrators = Users_User::listAdministrators();
					$viewData = array('error'=>$ex->getMessage());
					Core_Email::sendToList('system', 'backup_failed', $viewData, 'Error creating backup archive.', $administrators);
				}
				
				if ($throwOnError)
					throw $ex;
			}
		}
		
		protected static function createDumpArchive($archivePath, $options = array())
		{
			$tmpDirPath = null;

			try
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to  '.PATH_APP.'/temp.');

				$tmpDirPath = PATH_APP.'/temp/'.uniqid('lar');
				if (!@mkdir($tmpDirPath))
					throw new Phpr_SystemException('Unable to create directory '.$tmpDirPath);

				/*
				 * Create manifest file
				 */
				$manifest_data = '<?xml version="1.0"?><versions>';
				$versions = Db_UpdateManager::getVersions();
				foreach ($versions as $moduleId=>$version)
					$manifest_data .= '<module id="'.h($moduleId).'"><version>'.$version.'</version></module>';
				$manifest_data .= '</versions>';
				file_put_contents($tmpDirPath.'/manifest.xml', $manifest_data);

				/*
				 * Create database dump
				 */
				$sqlPath = $tmpDirPath.'/backup.sql';
				Db_DbHelper::createDbDump($sqlPath, array(
					'separator'=>'^;^'
				));
				
				
				$params = System_Backup_Params::get();
				
				$archive_files = false;
				if (array_key_exists('archive_uploaded_dir', $options))
					$archive_files = $options['archive_uploaded_dir'];
				else
					$archive_files = $params->archive_uploaded_dir;
				
				if ($archive_files)
				{
					/*
					 * Copy resources and uploaded files
					 */

					$options = array('ignore'=>array('.svn', '.DS_Store'));
					Phpr_Files::copyDir(PATH_APP.'/resources', $tmpDirPath.'/resources', $options);
					Phpr_Files::copyDir(PATH_APP.'/uploaded', $tmpDirPath.'/uploaded', $options);
				}

				/*
				 * Create archive
				 */
				Core_ZipHelper::zipDirectory($tmpDirPath, $archivePath);

				Phpr_Files::removeDirRecursive($tmpDirPath);
			}
			catch (Exception $ex)
			{
				if (strlen($tmpDirPath) && @file_exists($tmpDirPath))
					Phpr_Files::removeDirRecursive($tmpDirPath);

				throw $ex;
			}
		}

		public static function restoreData($archiveFile)
		{
			$tmpDirPath = null;
			$sqlPath = null;
			@set_time_limit(3600);
			
			/*
			 * Create archive
			 */

			Backend::$events->fireEvent('core:onBeforeArchiveRestore');

			try
			{
				$obj = new self();
				$obj->archive_name_prefix = 'before_restore_';
				$obj->save();
			} catch (Exception $ex)
			{
				throw new Phpr_ApplicationException('Error creating backup archive. '.$ex->getMessage());
			}
			
			/*
			 * Restore data
			 */

			try
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to '.PATH_APP.'/temp');
					
				$tmpDirPath = PATH_APP.'/temp/'.uniqid('lar');
				if (!@mkdir($tmpDirPath))
					throw new Phpr_SystemException('Unable to create directory '.$tmpDirPath);

				Core_ZipHelper::unzip($tmpDirPath, $archiveFile);
				
				/*
				 * Load manifest and check versions
				 */

				$manifestPath = $tmpDirPath.'/manifest.xml';
				if (!file_exists($manifestPath))
					throw new Phpr_ApplicationException('Archive file is corrupted - manifest file is not found.');

				$systemVersions = Db_UpdateManager::getVersions();

				$doc = new DOMDocument('1.0');
				$doc->loadXML(file_get_contents($manifestPath));
				$xPath = new DOMXPath($doc);
				
				$modules = $xPath->query('//versions/module');
				foreach ($modules as $module)
				{
					$version = $xPath->query('version', $module)->item(0)->nodeValue;
					if (!strlen($version))
						continue;
					
					$moduleId = $module->getAttribute('id');
					if (!strlen($moduleId))
						throw new Phpr_ApplicationException('Archive file is corrupted - module identifier is not found.');
						
					if (!array_key_exists($moduleId, $systemVersions))
						continue;
						
					if (!Db_UpdateManager::moduleVersionExists($moduleId, $version))
						throw new Phpr_ApplicationException('Error restoring data: the archive file was created in a  system newer than this one. Version of module "'.$moduleId.'" in the archive ('.$version.') is higher than existing version of the module installed in this system: '.$systemVersions[$moduleId].'. Operation aborted.');
				}

				/*
				 * Copy directories
				 */
				
				$directories = array('uploaded', 'resources');
				foreach ($directories as $directory)
				{
					$dirPath = $tmpDirPath.'/'.$directory;
					if (file_exists($dirPath))
						Phpr_Files::copyDir($dirPath, PATH_APP.'/'.$directory);
				}

				/*
				 * Restore database records
				 */
				$sqlPath = $tmpDirPath.'/backup.sql';
				if (!file_exists($sqlPath))
					throw new Phpr_ApplicationException('Archive file is corrupted - database dump is not found.');
					
				$tables = Db_DbHelper::listTables();
				foreach ($tables as $table)
				{
					try
					{
						Db_DbHelper::query('drop table `'.$table.'`');
					} catch (Exception $ex) {}
				}

				Db_DbHelper::executeSqlScript($sqlPath, '^;^');

				Phpr_Files::removeDirRecursive($tmpDirPath);
				
				Db_UpdateManager::resetCache();
				Db_UpdateManager::update();
			}
			catch (Exception $ex)
			{
				if (strlen($tmpDirPath) && @file_exists($tmpDirPath))
					Phpr_Files::removeDirRecursive($tmpDirPath);

				throw $ex;
			}
		}
		
		public static function restoreFromFile($fileInfo)
		{
			$filePath = null;

			try
			{
				if (!is_writable(PATH_APP.'/temp/'))
					throw new Phpr_SystemException('Insufficient writing permissions to '.PATH_APP.'/temp');
					
				$filePath = PATH_APP.'/temp/'.uniqid('lar');
				if (!move_uploaded_file($fileInfo['tmp_name'], $filePath))
					throw new Phpr_SystemException('Unable to copy uploaded file to '.$filePath);
					
				self::restoreData($filePath);

				@unlink($filePath);
			}
			catch (Exception $ex)
			{
				if (strlen($filePath) && @file_exists($filePath))
					@unlink($filePath);

				throw $ex;
			}
		}
	}
?>