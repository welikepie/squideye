<?php

	class Phpr_Files
	{
		public static $dir_copy_file_num = 0;
		
		/**
		 * Returns a number of bytes allowed for uploading through POST requests
		 */
		public static function maxUploadSize()
		{
			$maxFileSize = (int)ini_get('upload_max_filesize') * 1024000;
			$maxPostSize = (int)ini_get('post_max_size') * 1024000;

			return min($maxFileSize, $maxPostSize);
		}
		
		public static function determineCsvDelimeter($path)
		{
			$delimiters = array(';', ',', "\t");
			$maxCount = 0;
			$detected = null;

			foreach ($delimiters as $index=>$delimiter)
			{
				$handle = fopen($path, "r");
				$data = fgetcsv($handle, 3000, $delimiter);

				if ($data)
				{
					$count = count($data);
					if ($maxCount < $count)
					{
						$maxCount = $count;
						$detected = $delimiter;
					}
				}
					
				fclose($handle);
			}

			return $detected;
		}
		
		public static function outputCsvRow($Row, $separator = ';')
		{
			$str='';
			$quot = '"';
			$fd=$separator;

			foreach ( $Row as $cell )
			{
				$cell = str_replace( $quot, $quot.$quot, $cell );
//				if (strchr($cell, $fd) !== false || strchr($cell, $quot) !== false || strchr($cell, "\n") !== false) 
					$str .= $quot.$cell.$quot.$fd;
				// else 
				// 	$str .= $cell.$fd;
			}

			print substr($str, 0, -1)."\n";
		}
		
		public static function convertCsvEncoding(&$data)
		{
			$dataFound = false;
			foreach ($data as &$value)
			{
				$value = trim(mb_convert_encoding($value, 'UTF-8', Phpr::$config->get('FILESYSTEM_CODEPAGE')));
				if (strlen($value))
					$dataFound = true;
			}
			
			return $dataFound;
		}
		
		public static function getCsvField(&$row, $index, $default = null)
		{
			if (array_key_exists($index, $row))
				return $row[$index];

			return $default;
		}
		
		public static function csvRowIsEmpty(&$row)
		{
			foreach ($row as $column_data)
			{
				if (strlen(trim($column_data)))
					return false;
			}

			return true;
		}
		
		/**
		 * Returns a file size as string (203 Kb)
		 * @param int $size Specifies a size of a file in bytes
		 * @return string
		 */
		public static function fileSize($size)
		{
			if ( $size < 1024 )
				return $size.' byte(s)';
			
			if ( $size < 1024000 )
				return ceil($size/1024).' Kb';

			if ( $size < 1024000000 )
				return round($size/1024000, 1).' Mb';

			return round($size/1024000000, 1).' Gb';
		}
		
		public static function validateUploadedFile( $fileInfo )
		{
			switch ($fileInfo['error'])
			{
				case UPLOAD_ERR_INI_SIZE :
				{
					$MaxSizeAllowed = self::fileSize( self::maxUploadSize() );

					throw new Phpr_ApplicationException( sprintf("File size exceeds maximum allowed size (%s).", $MaxSizeAllowed) );
				}

				case UPLOAD_ERR_PARTIAL : 
					throw new Phpr_ApplicationException( "Error uploading file. Only a part of the file was uploaded." );

				case UPLOAD_ERR_NO_FILE :
					throw new Phpr_ApplicationException( "Error uploading file." );

				case UPLOAD_ERR_NO_TMP_DIR : 
					throw new Phpr_ApplicationException( "PHP temporary file directory does not exist." );

				case UPLOAD_ERR_CANT_WRITE : 
					throw new Phpr_ApplicationException( "Error writing file to disk." );
			}
		}
		
		public static function extract_mutli_file_info( $multi_file_info )
		{
			$result = array();
			if (!array_key_exists('name', $multi_file_info))
				return $result;
				
			$info_components = array_keys($multi_file_info);
				
			$file_count = count($multi_file_info['name']);
			for ($i=0; $i<$file_count; $i++)
			{
				$result[$i] = array();
				
				foreach ($info_components as $component_name)
					$result[$i][$component_name] = $multi_file_info[$component_name][$i];
			}
			
			return $result;
		}

		public static function copyDir($source, $target, &$options = array())
		{
			$files_to_ignore = array_key_exists('ignore', $options) ? $options['ignore'] : array();

			if ( is_dir($source) )
			{
				if (!file_exists($target))
					@mkdir($target);

				$d = dir($source);

				while ( false !== ($entry = $d->read()) ) 
				{
					if ( $entry == '.' || $entry == '..' )
						continue;

					if (in_array($entry, $files_to_ignore))
						continue;

					$dir_path = $source . '/' . $entry;
					if ( is_dir($dir_path) )
						self::copyDir( $dir_path, $target.'/'.$entry, $options );
					else
					{
						copy( $dir_path, $target.'/'.$entry );
						self::$dir_copy_file_num++;
					}
				}

				$d->close();
			} else 
			{
				copy( $source, $target );
				self::$dir_copy_file_num++;
			}
		}

		/**
		 * Removes files in a directory and the directory itself
		 */
		public static function removeDir($dir)
		{
		    if(!$dh = @opendir($dir))
				return;
		
		    while (false !== ($obj = readdir($dh)))
			{
		        if($obj=='.' || $obj=='..') 
					continue;
					
				@unlink($dir.'/'.$obj);
		    }

		    closedir($dh);
	        @rmdir($dir);
		}
		
		/**
		 * Removes a directory and its content
		 */
		public static function removeDirRecursive($sDir) 
		{
			if (is_dir($sDir)) 
			{
				$sDir = rtrim($sDir, '/');
				$oDir = dir($sDir);
				
				$count = 0;
				while (($sFile = $oDir->read()) !== false) 
				{
					if ($sFile != '.' && $sFile != '..')
					{
						$count++;
						if (!is_link("$sDir/$sFile") && is_dir("$sDir/$sFile"))
							self::removeDirRecursive("$sDir/$sFile");
						else
						{
							unlink("$sDir/$sFile");
							if($count > 100)
							{
								$oDir->rewind();
								$count = 0;
							}
						}
					}
				}
				$oDir->close();
				
				rmdir($sDir);
				return true;
			}

			return false;
		}

		/**
		 * Returns a list of subdirectories of a specified directory
		 */
		public static function listSubdirectories($dir)
		{
			$result = array();
			
			if ($dh = opendir($dir)) 
			{
				while (($file = readdir($dh)) !== false)
				{
					if (is_dir($dir.$file) && $file{0} != '.')
						$result[] = $dir.$file;
				}

				closedir($dh);
			}
			
			return $result;
		}
		
		/**
		 * Converts a file path to a path relative to the application root directory
		 */
		public static function rootRelative($path)
		{
			str_replace("\\", "/", $path);
			if (strpos($path, PATH_APP) === 0)
				return substr($path, strlen(PATH_APP));
				
			return $path;
		}
		
		/**
		 * Returns a folder permission mask specified in the config.php file
		 */
		public static function getFolderPermissions()
		{
			$permissions = Phpr::$config->get('FOLDER_PERMISSIONS');
			if ($permissions)
				return $permissions;
				
			$permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
			if ($permissions)
				return $permissions;
				
			return 0777;
		}
		
		/**
		 * Returns a file permission mask specified in the config.php file
		 */
		public static function getFilePermissions()
		{
			$permissions = Phpr::$config->get('FILE_PERMISSIONS');
			if ($permissions)
				return $permissions;
				
			$permissions = Phpr::$config->get('FILE_FOLDER_PERMISSIONS');
			if ($permissions)
				return $permissions;
				
			return 0777;
		}
		
		public static function readFile($filename)
		{
			$chunksize = 1*(1024*1024); // how many bytes per chunk
			$buffer = '';
			$handle = fopen($filename, 'rb');
			if ($handle === false)
			{
				return false;
			}
			while (!feof($handle))
			{
				$buffer = fread($handle, $chunksize);
				print $buffer;
			}
			return fclose($handle);
		}
	}

?>