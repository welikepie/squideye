<?php

	/**
	 * Manages module updates
	 */
	class Db_UpdateManager
	{
		private static $_versions = null;
		private static $_updates = null;
		
		/**
		 * Updates all modules
		 */
		public static function update()
		{
			self::createMetadata();

			$db_updated = false;

			/*
			 * Update system modules
			 */
			$modulesPath = PATH_SYSTEM."/modules";
			$iterator = new DirectoryIterator( $modulesPath );
			
			foreach ( $iterator as $dir )
			{
				if ( $dir->isDir() && !$dir->isDot() )
				{
					$moduleId = $dir->getFilename();
					$module_updated = self::updateModule($moduleId, PATH_SYSTEM);
					$db_updated = $db_updated || $module_updated;
				}
			}

			/*
			 * Update application modules
			 */
			$modules = Core_ModuleManager::listModules(false);
			$module_ids = array();
			
			foreach ($modules as $module)
			{
				$id = mb_strtolower($module->getModuleInfo()->id);
				$module_ids[$id] = 1;
			}
			
			$sequence = array_flip(Phpr::$config->get('UPDATE_SEQUENCE', array()));
			if (count($sequence))
			{
				$updated_module_ids = $sequence;
				foreach ($module_ids as $module_id=>$value)
				{
					if (!array_key_exists($module_id, $sequence))
						$updated_module_ids[$module_id] = 1;
				}
				
				$module_ids = $updated_module_ids;
			}

			$module_ids = array_keys($module_ids);
			foreach ($module_ids as $module_id)
			{
				$module_updated = self::updateModule($module_id);
				$db_updated = $db_updated || $module_updated;
			}
				
			if ($db_updated)
				Db_ActiveRecord::clear_describe_cache();
		}
		
		public static function resetCache()
		{
			self::$_versions = null;
		}

		/**
		 * Returns versions of all modules installed in the system
		 */
		public static function getVersions()
		{
			$result = array();
			
			$modules = Core_ModuleManager::listModules(false);
			foreach ($modules as $module)
			{
				$moduleId = $module->getModuleInfo()->id;
				$version = self::getDbVersion($moduleId);
				$result[$moduleId] = $version;
			}
			
			return $result;
		}
		
		/**
		 * Checks whether a version does exist in the module update history.
		 * @param string $module_id Specifies a module identifier.
		 * @param string @version_str Specifies a version string.
		 * @return boolean Returns true if the version was found in the module update history. Returns false otherwise.
		 */
		public static function moduleVersionExists($module_id, $version_str)
		{
			return Db_DbHelper::scalar('select count(*) from core_update_history where moduleId=:module_id and version_str=:version_str', array(
				'module_id'=>$module_id, 
				'version_str'=>$version_str
			)) > 0;
		}

		/**
		 * Updates a module.
		 * @param string $module_id Module identifier.
		 * @param string $base_path Module base path. Defaults to the modules subdirectory in the application root directory.
		 */
		public static function updateModule($module_id, $base_path = null)
		{
			$base_path = $base_path === null ? PATH_APP : $base_path;
			
			$last_dat_version = null;
			$dat_versions = self::getDatVersions($module_id, $last_dat_version, $base_path);

			/*
			 * Apply new database/php updates
			 */

			$db_update_result = false;

			$current_db_version = self::getDbVersion($module_id);
			$last_db_version_index = self::getDbUpdateIndex($current_db_version, $dat_versions);

			foreach ($dat_versions as $index=>$update_info)
			{
				if ($update_info['type'] == 'update-reference')
					$db_update_result = self::applyDbUpdate($base_path, $module_id, $update_info['reference']) || $db_update_result;
				elseif ($update_info['type'] == 'version-update')
				{
					/*
					 * Apply updates from references specified in the version string
					 */
					foreach ($update_info['references'] as $reference)
						$db_update_result = self::applyDbUpdate($base_path, $module_id, $reference) || $db_update_result;
						
					/*
					 * Apply updates with names matching the version number (backward compatibility)
					 */

					if ($index > $last_db_version_index && $last_db_version_index !== -2)
					{
						if (strlen($update_info['build']))
							$db_update_result = self::applyDbUpdate($base_path, $module_id, $update_info['build']) || $db_update_result;
						else
							$db_update_result = self::applyDbUpdate($base_path, $module_id, $update_info['version']) || $db_update_result;
					}
				}
			}
			
			/*
			 * Increase the version number and add new version records to the version history table
			 */

			if ($current_db_version != $last_dat_version)
				self::setDbVersion($current_db_version, $last_dat_version, $dat_versions, $module_id);
			
			return $db_update_result;
		}
		
		/**
		 * Applies module update file(s).
		 * @param string $base_path Base module directory.
		 * @param string $module_id Module identifier.
		 * @param string $update_id Update identifier.
		 * @return boolean Returns true if any updates have been applied. Returns false otherwise.
		 */
		protected static function applyDbUpdate($base_path, $module_id, $update_id)
		{
			/*
			 * If the update has already been applied, return false
			 */

			if (in_array($update_id, self::getModuleAppliedUpdates($module_id)))
				return false;
			
			$result = false;

			/*
			 * Apply SQL update file
			 */
			
			$update_path =  $base_path.'/modules/'.$module_id.'/updates/'.$update_id.'.sql';
			if ( file_exists($update_path) )
			{
				$result = true;
				Db_DbHelper::executeSqlScript($update_path);
			}

			/*
			 * Apply PHP update file
			 */

			$update_path =  $base_path.'/modules/'.$module_id.'/updates/'.$update_id.'.php';
			if ( file_exists($update_path) )
			{
				$result = true;
				include $update_path;
			}
			
			/*
			 * Register the applied update in the database and in the internal cache
			 */

			if ($result)
				self::registerAppliedModuleUpdate($module_id, $update_id);
			
			return $result;
		}
		
		public static function createMetadata()
		{
			$tables = Db_DbHelper::listTables();
			if (!in_array('core_versions', $tables))
				Db_DbHelper::executeSqlScript(PATH_SYSTEM.'/modules/db/updates/bootstrap.sql');

			if (!in_array('core_applied_updates', $tables))
				Db_DbHelper::executeSqlScript(PATH_SYSTEM.'/modules/db/updates/applied_updates.sql');
		}

		/**
		 * Returns version of a module stored in the database.
		 * @param string $module_id Specifies the module identifier.
		 * @return string Returns the module version.
		 */
		public static function getDbVersion($module_id)
		{
			if (self::$_versions === null)
			{
				$versions = Db_DbHelper::queryArray('select moduleId, version_str as version from core_versions order by id');
				self::$_versions = array();

				foreach ($versions as $version_info)
					self::$_versions[$version_info['moduleId']] = $version_info['version'];
			}
			
			if (array_key_exists($module_id, self::$_versions))
				return self::$_versions[$module_id];

			Db_DbHelper::query(
				'insert into core_versions(moduleId, version, date) values (:moduleId, :version, :date)', 
				array('moduleId'=>$module_id, 'version'=>0, 'date'=>gmdate('Y-m-d h:i:s')));

			Db_DbHelper::query(
				'insert into core_install_history(date, moduleId) values(:date, :moduleId)',
				array('date'=>gmdate('Y-m-d h:i:s'), 'moduleId'=>$module_id)
			);

			return null;
		}

		/**
		 * Updates module version history and its version in the database.
		 * @param string $current_db_version Current module version number stored in the database.
		 * @param string $last_dat_version Latest module version specified in the module version.dat file.
		 * @param mixed $dat_versions Parsed versions information from the module version.dat file.
		 * @param string $module_id Module identifier.
		 */
		private static function setDbVersion($current_db_version, $last_dat_version, &$dat_versions, $module_id)
		{
			if (self::$_versions === null)
				self::$_versions = array();
			
			/*
			 * Update the module version number
			 */

			Db_DbHelper::query( '
				update 
					core_versions 
				set 
					`version`=null, 
					version_str=:version_str 
				where 
					moduleId=:moduleId', 
			array(
				'version_str'=>$last_dat_version, 
				'moduleId'=>$module_id)
			);
			
			self::$_versions[$module_id] = $last_dat_version;
			
			/*
			 * Add version history records
			 */

			$last_db_version_index = self::getDbUpdateIndex($current_db_version, $dat_versions);
			if ($last_db_version_index !== -2)
			{
				$last_version_index = count($dat_versions)-1;
				$start_index = $last_db_version_index+1;
				if ($start_index <= $last_version_index)
				{

					for ($index=$start_index; $index <= $last_version_index; $index++)
					{
						$version_info = $dat_versions[$index];

						if ($version_info['type'] != 'version-update')
							continue;

						Db_DbHelper::query( 
							'insert 
								into core_update_history(date, moduleId, version, description, version_str) 
								values(:date, :moduleId, :version, :description, :version_str)', 
							array(
								'date'=>gmdate('Y-m-d h:i:s'), 
								'moduleId'=>$module_id, 
								'version'=>$version_info['build'], 
								'description'=>$version_info['description'], 
								'version_str'=>$version_info['version']
							)
						);
					}
				}
			}
		}

		/**
		 * Returns index of a record in the version.dat file which corresponds to the latest version of the module stored in the database.
		 * @param string $current_db_version Current module version number stored in the database.
		 * @param mixed $dat_versions Parsed versions information from the module version.dat file.
		 * @return integer Returns the version record index. Returns -1 if a matching record was not found in the database.
		 */
		public static function getDbUpdateIndex($current_db_version, &$dat_versions)
		{
			foreach ($dat_versions as $index=>$version_info)
			{
				if ($version_info['type'] == 'version-update')
				{
					if ($version_info['version'] == $current_db_version)
						return $index;
				}
			}
			
			if ($current_db_version)
				return -2;
			
			return -1;
		}

		/**
		 * Returns full version information from a module's version.dat file.
		 * Returns a list of versions and update references in the following format:
		 * array(
		 *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
		 *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
		 * )
		 * @param string $moduleId Specifies the module identifier.
		 * @param string $lastVersion Reference to the latest version in the version.dat file.
		 * @param string $basePath Base module path, defaults to the application root directory.
		 * @return array Returns array of application versions and references to the database update files.
		 */
		public static function getDatVersions($moduleId, &$lastVersion, $basePath = null)
		{
			$basePath = $basePath === null ? PATH_APP : $basePath;
			$versionsPath = $basePath.'/modules/'.$moduleId.'/updates/version.dat';
			if (!file_exists($versionsPath))
				return array();
				
			return self::parseDatFile($versionsPath, $lastVersion);
		}
		
		/**
		 * Parses a .dat file and returns full version information it contains.
		 * Returns a list of versions and update references in the following format:
		 * array(
		 *   0=>array('type'=>'version-update', 'version'=>'1.1.1', 'build'=>111, 'description'=>'Version description', 'references'=>array('abc123de45', 'abc123de46')),
		 *   1=>array('type'=>'update-reference', 'reference'=>'abc123de47')
		 * )
		 * @param string $filePath Path to the file to parse.
		 * @param string $lastVersion Reference to the latest version in the version.dat file.
		 * @return array Returns array of application versions and references to the database update files.
		 */ 
		public static function parseDatFile($filePath, &$lastVersion)
		{
			$lastVersion = null;

			if (!file_exists($filePath))
				return array();
				
			$contents = file_get_contents($filePath);
			
			/*
			 * Normalize line-endings and split the file content
			 */
			
			$contents = str_replace("\r\n", "\n", $contents);
			$update_list = preg_split("/^\s*(#)|^\s*(@)/m", $contents, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
			
			/*
			 * Analyze each update and extract its type and description
			 */

			$length = count($update_list);
			$result = array();

			for ($index=0; $index < $length;)
			{
				$update_type = $update_list[$index];
				$update_content = $update_list[$index+1];
				
				if ($update_type == '@') 
				{
					/*
					 * Parse update references
					 */
					
					$references = preg_split('/\|\s*@/', $update_content);
					foreach ($references as $reference)
						$result[] = array('type'=>'update-reference', 'reference'=>trim($reference));
				} elseif ($update_type == '#')
				{
					/*
					 * Parse version strings
					 */
				
					$pos = mb_strpos($update_content, ' ');
					
					if ($pos === false)
						throw new Phpr_ApplicationException('Error parsing version file ('.$filePath.'). Version string should have a description: '.$update_content);

					$version_info = trim(mb_substr($update_content, 0, $pos));
					$description = trim(mb_substr($update_content, $pos+1));
					
					/*
					 * Expected version/update notations: 
					 * 2
					 * 2|0.0.2
					 * 2|@abc123de46
					 * 0.0.2|@abc123de45|@abc123de46
					 */

					$version_info_parts = explode('|@', $version_info);

					$version_number = self::extractVersionNumber($version_info_parts[0]);
					$build_number = self::extractBuildNumber($version_info_parts[0]);
					$references = array();

					if (($cnt = count($version_info_parts)) > 1)
					{
						for ($ref_index = 1; $ref_index < $cnt; $ref_index++)
							$references[] = $version_info_parts[$ref_index];
					}
					
					$lastVersion = $version_number;
					
					$result[] = array(
						'type'=>'version-update',
						'version'=>$version_number, 
						'build'=>$build_number, 
						'description'=>$description, 
						'references'=>$references
					);
				}
				
				$index += 2;
			}
			
			return $result;
		}
		
		/**
		 * Extracts version number from a version string, which can also contain a build number.
		 * Returns "1.0.2" for 2|1.0.2.
		 * @param string $versionString Version string.
		 * @return string Returns the version string.
		 */
		public static function extractVersionNumber($version_string)
		{
			$parts = explode('|', $version_string);
			if (count($parts) == 2)
				return trim($parts[1]);

			if (strpos($parts[0], '.') === false)
				return '1.0.'.trim($parts[0]);

			return trim($parts[0]);
		}
		
		/**
		 * Extracts build number from a version string (backward compatibility).
		 * Returns "2" for 2|1.0.2. Returns null for 1.0.2.
		 * @param string $versionString Version string.
		 * @return string Returns the build number.
		 */
		public static function extractBuildNumber($version_string)
		{
			$parts = explode('|', $version_string);
			if (count($parts) == 2)
				return trim($parts[0]);

			if (strpos($parts[0], '.') !== false)
				return null;

			return trim($parts[0]);
		}
		
		/**
		 * Returns a list of update identifiers which have been applied to a specified module.
		 * @param string $module_id Specified the module identifier.
		 * @return array Returns a list of applied update identifiers.
		 */
		public static function getModuleAppliedUpdates($module_id)
		{
			if (self::$_updates === null)
			{
				self::$_updates = array();

				$update_list = Db_DbHelper::queryArray('select * from core_applied_updates');
				foreach ($update_list as $update_info)
				{
					if (!array_key_exists($update_info['module_id'], self::$_updates))
						self::$_updates[$update_info['module_id']] = array();

					self::$_updates[$update_info['module_id']][] = $update_info['update_id'];
				}
			}
			
			if (!array_key_exists($module_id, self::$_updates))
				return array();
				
			return self::$_updates[$module_id];
		}
		
		/**
		 * Adds update to the list of applied module updates.
		 * @param string $module_id Specified the module identifier.
		 * @param string $update_id Specified the update identifier.
		 */
		protected static function registerAppliedModuleUpdate($module_id, $update_id)
		{
			if (self::$_updates === null)
				self::$_updates = array();

			if (!array_key_exists($module_id, self::$_updates))
				self::$_updates[$module_id] = array();

			self::$_updates[$module_id][] = $update_id;
			Db_DbHelper::query(
				'insert into core_applied_updates(module_id, update_id, created_at) values(:module_id, :update_id, :created_at)', array(
					'module_id'=>$module_id, 
					'update_id'=>$update_id, 
					'created_at'=>gmdate('Y-m-d h:i:s')
				)
			);
		}
		
		/**
		 * To delete
		 */
		private static function _splitVersionNum($version)
		{
			$result = array();
			
			$parts = explode('|', $version);
			if (count($parts) == 2)
			{
//				$result['build'] = $parts[0];
				$result['version'] = $parts[1];
			}
			else
			{
				if (strpos($parts[0], '.') === false)
				{
//					$result['build'] = $parts[0];
					$result['version'] = '1.0.'.$parts[0];
				} else {
					$result['version'] = $parts[0];
					
					$version_parts = explode('.', trim($parts[0]));
					if (count($version_parts) != 3)
						throw new Phpr_SystemException('Invalid version number specifier: '.$parts[0]);
					
//					$result['build'] = $version_parts[0]*10000000 + $version_parts[1]*1000 + $version_parts[2];
				}
			}
			
			return (object)$result;
		}
	}
?>