<?php

	class Db_File extends Db_ActiveRecord 
	{
		public $table_name = 'db_files';
		public $simpleCaching = true;

		public $implement = 'Db_AutoFootprints';
		
		protected $autoMimeTypes = 
			array('docx'=>'application/msword', 'xlsx'=>'application/excel', 'gif'=>'image/gif', 'png'=>'image/png', 'jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg',
					'jpe'=>'image/jpeg'
			);
			
		public static $image_extensions = array(
			'jpg', 'jpeg', 'png', 'gif'
		);
		
		public $calculated_columns = array( 
			'user_name'=>array('sql'=>'concat(lastName, " ", firstName)', 
				'type'=>db_text, 'join'=>array('users'=>'users.id=db_files.created_user_id'))
		);
		
		public function __construct($values = null, $options = array())
		{
			$front_end = Db_ActiveRecord::$execution_context == 'front-end';
			if ($front_end)
				unset($this->calculated_columns['user_name']);

			parent::__construct($values, $options);
		}

		public static function create($values = null) 
		{
			return new self($values);
		}

		public function fromPost($fileInfo)
		{
			Phpr_Files::validateUploadedFile($fileInfo);
			
			$this->mime_type = $this->evalMimeType($fileInfo);
			$this->size = $fileInfo['size'];
			$this->name = $fileInfo['name'];
			$this->disk_name = $this->getDiskFileName($fileInfo);

			$destPath = $this->getFileSavePath($this->disk_name);

			if ( !@move_uploaded_file($fileInfo["tmp_name"], $destPath) )
				throw new Phpr_SystemException( "Error copying file to $destPath." );

			return $this;
		}
		
		public function fromFile($file_path)
		{
			$fileInfo = array();
			$fileInfo['name'] = basename($file_path);
			$fileInfo['size'] = filesize($file_path);
			$fileInfo['type'] = null;
			
			$this->mime_type = $this->evalMimeType($fileInfo);
			$this->size = $fileInfo['size'];
			$this->name = $fileInfo['name'];
			$this->disk_name = $this->getDiskFileName($fileInfo);

			$destPath = $this->getFileSavePath($this->disk_name);

			if ( !@copy($file_path, $destPath) )
				throw new Phpr_SystemException( "Error copying file to $destPath." );

			return $this;
		}
		
		protected function getDiskFileName($fileInfo)
		{
			$ext = $this->getFileExtension($fileInfo);
			$name = uniqid(null, true);
			
			return $ext !== null ? $name.'.'.$ext : $name;
		}
		
		protected function evalMimeType($fileInfo)
		{
			$type = $fileInfo['type'];
			$ext = $this->getFileExtension($fileInfo);
			
			$mime_types = array_merge($this->autoMimeTypes, Phpr::$config->get('auto_mime_types', array()));

			if (array_key_exists($ext, $mime_types))
				return $mime_types[$ext];
			
			return $type;
		}
		
		protected function getFileExtension($fileInfo)
		{
			$pathInfo = pathinfo($fileInfo['name']);
			if (isset($pathInfo['extension']))
				return strtolower($pathInfo['extension']);

			return null;
		}

		public function getFileSavePath($diskName)
		{
			if (!$this->is_public)
				return PATH_APP.'/uploaded/'.$diskName;
			else
				return PATH_APP.'/uploaded/public/'.$diskName;
		}
		
		public function after_create() 
		{
			Db_DbHelper::query('update db_files set sort_order=:sort_order where id=:id', array(
				'sort_order'=>$this->id,
				'id'=>$this->id
			));
			$this->sort_order = $this->id;
		}

		public function after_delete()
		{
			$destPath = $this->getFileSavePath($this->disk_name);
			
			if (file_exists($destPath))
				@unlink($destPath);

			$thumbPath = PATH_APP.'/uploaded/thumbnails/db_file_img_'.$this->id.'_*.jpg';
			$thumbs = glob($thumbPath);
			if (is_array($thumbs))
			{
				foreach ($thumbs as $filename) 
				    @unlink($filename);
			}
		}

		public function output($disposition = 'inline')
		{
			$path = $this->getFileSavePath($this->disk_name);
			if (!file_exists($path))
				throw new Phpr_ApplicationException('Error: file not found.');
			
			$encoding = Phpr::$config["FILESYSTEM_CODEPAGE"];
			$fileName = mb_convert_encoding( $this->name, $encoding );
			
			$mime_type = $this->mime_type;
			if (!strlen($mime_type) || $mime_type == 'application/octet-stream')
			{
				$fileInfo = array('type'=>$mime_type, 'name'=>$fileName);
				$mime_type = $this->evalMimeType($fileInfo);
			}

			header("Content-type: ".$mime_type);
			header('Content-Disposition: '.$disposition.'; filename="'.$fileName.'"');
			header('Cache-Control: private');
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Cache-Control: pre-check=0, post-check=0, max-age=0');
			header('Accept-Ranges: bytes');
			header('Content-Length: '.$this->size);
//			header("Connection: close");

			Phpr_Files::readFile( $path );
		}
		
		public function getThumbnailPath($width, $height, $returnJpeg = true, $params = array('mode' => 'keep_ratio'))
		{
			$processed_images = Backend::$events->fireEvent('core:onProcessImage', $this, $width, $height, $returnJpeg, $params);
			foreach ($processed_images as $image)
			{
				if (strlen($image))
				{
					if (!preg_match(',^(http://)|(https://),', $image))    
						return root_url($image);
					else 
						return $image;
				}
			}

			$ext = $returnJpeg ? 'jpg' : 'png';

			$thumbPath = '/uploaded/thumbnails/db_file_img_'.$this->id.'_'.$width.'x'.$height.'.'.$ext;
			$thumbFile = PATH_APP.$thumbPath;

			if (file_exists($thumbFile))
				return root_url($thumbPath);

			try
			{
				Phpr_Image::makeThumbnail($this->getFileSavePath($this->disk_name), $thumbFile, $width, $height, false, $params['mode'], $returnJpeg);
			}
			catch (Exception $ex)
			{
				@copy(PATH_APP.'/phproad/resources/images/thumbnail_error.gif', $thumbFile);
			}

			return root_url($thumbPath);
		}
		
		public function getPath()
		{
			if (!$this->is_public)
				return '/uploaded/'.$this->disk_name;
			else
				return '/uploaded/public/'.$this->disk_name;
		}

		public function copy()
		{
			$srcPath = $this->getFileSavePath($this->disk_name);
			$destName = $this->getDiskFileName(array('name'=>$this->disk_name));

			$obj = new Db_File();
			$obj->mime_type = $this->mime_type;
			$obj->size = $this->size;
			$obj->name = $this->name;
			$obj->disk_name = $destName;
			$obj->description = $this->description;
			$obj->sort_order = $this->sort_order;
			
			if (!copy($srcPath, $obj->getFileSavePath($destName)))
				throw new Phpr_SystemException( "Error copying file" );

			return $obj;
		}
		
		public static function set_orders($item_ids, $item_orders)
		{
			if (is_string($item_ids))
				$item_ids = explode(',', $item_ids);
				
			if (is_string($item_orders))
				$item_orders = explode(',', $item_orders);

			foreach ($item_ids as $index=>$id)
			{
				$order = $item_orders[$index];
				Db_DbHelper::query('update db_files set sort_order=:sort_order where id=:id', array(
					'sort_order'=>$order,
					'id'=>$id
				));
			}
		}
		
		public function is_image()
		{
			$pathInfo = pathinfo($this->name);
			$extension = null;
			if (isset($pathInfo['extension']))
				$extension = strtolower($pathInfo['extension']);
				
			return in_array($extension, self::$image_extensions);
		}

		public function before_create($deferred_session_key = null) 
		{
    		Backend::$events->fireEvent('core:onFileBeforeCreate', $this);
		}
	}

?>
