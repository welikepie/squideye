<?php

	class Backend_FileBrowser extends Phpr_ControllerBehavior
	{
		/**
		 * Specifies a list of directories to output
		 * public $filebrowser_dirs = array('resources'=>array('path'=>'/resources', 'root_upload'=>false));
		 */
		public $filebrowser_dirs = array();
		public $filebrowser_default_dirs = false;
		public $filebrowser_onFileClick = 'return false;';
		public $filebrowser_absoluteUrls = true;
		public $file_browser_file_list_class = null;

		public function __construct($controller)
		{
			parent::__construct($controller);
			
			if ($this->_controller->filebrowser_default_dirs)
			{
				$resources_path = Cms_SettingsManager::get()->resources_dir_path;
				$this->_controller->filebrowser_dirs[$resources_path] = array('path'=>'/'.$resources_path, 'root_upload'=>true, 'title'=>'Website resources directory');

				if (Cms_Theme::is_theming_enabled())
				{
					if ($theme = Cms_Theme::get_edit_theme())
					{
						$theme_resources_path = 'themes/'.$theme->code.'/resources';

						$this->_controller->filebrowser_dirs[$theme_resources_path] = array(
							'path'=>'/'.$theme_resources_path, 
							'root_upload'=>true,
							'title'=>'Theme resources directory'
						);
					}
				}
			}

			$this->addEventHandler('onFileBrowserFolderClick');
			$this->addEventHandler('onFileBrowserSetViewMode');
			$this->addEventHandler('onFileBrowserInsertImage');
			$this->addEventHandler('onFileBrowserInsertLink');
			$this->addEventHandler('onLoadResourceUploader');
			
			$this->addEventHandler('onFileBrowserInsertImageProcess');

			$this->hideAction('filebrowserGetFileThumb');
			$this->hideAction('filebrowserGetUploadUrl');

			$this->_controller->addPublicAction('filebrowser_file_upload');

			$this->_controller->addCss('/modules/backend/behaviors/backend_filebrowser/resources/css/filebrowser.css');
			$this->_controller->addJavaScript('/modules/backend/behaviors/backend_filebrowser/resources/javascript/filebrowser.js?'.module_build('backend'));
		}

		/**
		 *
		 * Public methods - you may call it from your views
		 *
		 */

		/**
		 * Renders the file browser
		 */
		public function filebrowserRender()
		{ 
			$path = Db_UserParameters::get($this->browserGetName().'_path');
			if (strlen($path) && !file_exists(PATH_APP.$path))
				$path = null;
				
			$test_path = str_replace('\\', '/', $path);
			$path_is_valid = false;
			foreach ($this->_controller->filebrowser_dirs as $dir_info)
			{
				$dir_path = str_replace('\\', '/', $dir_info['path']);
				if (substr($dir_path, 0, 1) != '/')
					$dir_path = '/'.$dir_path;

				if (substr($test_path, 0, strlen($dir_path)) == $dir_path)
				{
					$path_is_valid = true;
					break;
				}
			}
			
			if (!$path_is_valid)
				$path = null;
			
			$this->prepareFileList($path);
			$this->renderPartial('file_browser');
		}

		public function filebrowserRenderPartial($view, $params = array())
		{
			$this->renderPartial($view, $params);
		}

		/*
		 * Event handlers
		 */

		public function onFileBrowserFolderClick()
		{
			try
			{
				Db_UserParameters::set($this->browserGetName().'_path', post('path'));
				$this->ajaxPrepareRender(post('path'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onFileBrowserSetViewMode()
		{
			try
			{
				$this->setFolderViewMode(post('path'), post('mode'));
				$this->ajaxPrepareRender(post('path'));
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function onFileBrowserInsertImage()
		{
			try
			{
				$path = post('image_path');
				$file_path = PATH_APP.$path;
				if (!file_exists($file_path))
					throw new Phpr_ApplicationException('Image not found');

				$this->viewData['image_path'] = $path;
				$this->viewData['thumb_path'] = $this->filebrowserGetFileThumb($path, 130, 'auto');

				$size = getimagesize(PATH_APP.$path);
				$this->viewData['width'] = $size[0];
				$this->viewData['height'] = $size[1];
				$this->viewData['insert_action'] = $this->_controller->getEventHandler('onFileBrowserInsertImageProcess');
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('insert_image_form');
		}
		
		public function onFileBrowserInsertLink()
		{
			try
			{
				$this->viewData['link_path'] = root_url(post('link_path'));
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('insert_link_form');
		}
		
		public function onFileBrowserInsertImageProcess()
		{
			try
			{
				$size_option = post('image_size');
				$alt_text = h(post('image_alt_text'));

				if ($size_option !== 'orig')
				{
					$width = 'auto';
					$height = 'auto';
					if (post('image_width_option') == 'exact')
					{
						$width = trim(post('image_width_value'));
						if (!preg_match('/^[0-9]+$/', $width))
							throw new Phpr_ApplicationException('Please specify image width as number.');

						$width = (int)$width;

						if ($width == 0)
							throw new Phpr_ApplicationException('Image width must be greater than zero.');
					}
					if (post('image_height_option') == 'exact')
					{
						$height = trim(post('image_height_value'));
						if (!preg_match('/^[0-9]+$/', $height))
							throw new Phpr_ApplicationException('Please specify image height as number.');

						$height = (int)$height;
						if ($height == 0)
							throw new Phpr_ApplicationException('Image height must be greater than zero.');
					}

					echo root_url($this->filebrowserGetFileThumb(post('image_path'), $width, $height)).'^|^'.$alt_text;
				} else
				{
					echo root_url(post('image_path')).'^|^'.$alt_text;
				}
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		/**
		 *
		 * Private methods - used by the behavior
		 *
		 */
		
		protected function prepareFileList($path)
		{
			$files = array();
			$display_path = null;
			$current_path = null;
			$allow_upload = false;

			if (!strlen($path))
			{
				$dir_num = count($this->_controller->filebrowser_dirs);
				
				if ($dir_num > 1)
				{
					foreach ($this->_controller->filebrowser_dirs as $alias=>$dir_info)
						$files[] = (object)array('type'=>'folder', 'name'=>$alias, 'path'=>$dir_info['path'], 'title'=>(isset($dir_info['title']) ? $dir_info['title'] : null));
						
					$display_path = 'please select a directory...';
				} elseif ($dir_num == 1)
				{
					$dirs = array_values($this->_controller->filebrowser_dirs);
					$dir_info = $dirs[0];
					
					$this->listFolderContent($dir_info['path'], $files);
					$current_path = $display_path = $dir_info['path'];
					$allow_upload = array_key_exists('root_upload', $dir_info) ? $dir_info['root_upload'] : true;
				}
			} else {
				$files[] = (object)array('type'=>'up', 'name'=>'Level up...', 'path'=>dirname($path));
				
				$this->listFolderContent($path, $files);
				$current_path = $display_path = $path;
				$allow_upload = true;
			}

			$this->viewData['filebrowser_files'] = $files;
			$this->viewData['filebrowser_displaypath'] = $display_path;
			$this->viewData['filebrowser_current_path'] = $current_path;
			$this->viewData['filebrowser_allowuploads'] = $allow_upload;

			$view_mode = strlen($current_path) ? $this->getFolderViewMode($current_path) : null;

			$this->viewData['filebrowser_viewmode'] = $view_mode;
		}
		
		private function listFolderContent($folder, &$result)
		{
			if (!strlen($folder))
				return array();

			$dir = $folder;
			$folders = array();
			$files = array();
			if ($dh = opendir(PATH_APP.$folder))
			{
				while ( ($file = readdir($dh)) !== false )
				{
					if ($file == '.' || $file == '..')
						continue;

					$file_path = $folder.'/'.$file;

					$dir = is_dir(PATH_APP.$file_path);
					if (!is_file(PATH_APP.$file_path) && !$dir || $file{0} == '.')
						continue;

					if (!$dir)
					{
						$pathInfo = pathinfo(PATH_APP.$file_path);
						$ext = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : null;

						if (in_array($ext, array('jpg', 'jpeg', 'gif', 'png')))
							$type = 'image';
						else
							$type = 'file';

						$files[] = (object)array('type'=>$type, 'name'=>$file, 'path'=>$file_path,  'size'=>filesize(PATH_APP.$file_path), 'time'=>filemtime(PATH_APP.$file_path));
					} else
						$folders[] = (object)array('type'=>'folder', 'name'=>$file, 'path'=>$file_path);
				}
				closedir($dh);
			}

			usort($files, 'backend_filebrowser_sort_files');

			foreach ($folders as $folder)
				$result[] = $folder;

			foreach ($files as $file)
				$result[] = $file;
		}
		
		private function pathValidated($path, $strict = true)
		{
			$path = str_replace('\\', '/', $path);
			if (strpos('..', $path) !== false)
				return null;
				
			if (!$strict)
				return $path;

			$cnt = count($this->_controller->filebrowser_dirs);
			foreach ($this->_controller->filebrowser_dirs as $alias=>$dir_info)
			{
				$dir = $dir_info['path'];
				
				$pos = strpos($path, $dir);
				if ($pos !== false && $pos === 0)
				{
					if ($path == $dir && $cnt == 1)
						return null;
					
					return $path;
				}
			}
			
			return null;
		}
		
		private function browserGetName()
		{
			return get_class($this->_controller).'_'.Phpr::$router->action.'_filebrowser';
		}
		
		private function setFolderViewMode($path, $mode)
		{
			$param_name = $this->browserGetName().'_viewmode';
			
			$view_modes = Db_UserParameters::get($param_name, null, array());
			$view_modes[$path] = $mode;
			
			Db_UserParameters::set($param_name, $view_modes);
		}
		
		private function getFolderViewMode($path)
		{
			$view_modes = Db_UserParameters::get($this->browserGetName().'_viewmode', null, array());
			if (!array_key_exists($path, $view_modes))
				return 'list';
				
			return $view_modes[$path];
		}

		private function ajaxPrepareRender($path)
		{
			$path = $this->pathValidated($path);
			$this->prepareFileList($path);

			echo '>>file_browser_file_list<<';
			$this->renderPartial('file_list');
			echo '>>file_browser_statusbar<<';
			$this->renderPartial('status_bar');
			echo '>>file_browser_toolbar<<';
			$this->renderPartial('toolbar');
		}
		
		public function filebrowserGetFileThumb($path, $width = 50, $height = 35)
		{
			$pathInfo = pathinfo($path);
			$ext = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : null;
			
			$isImage = in_array($ext, array('jpg', 'jpeg', 'gif', 'png'));

			if (!$isImage)
				return null;

			$thumbName = Phpr_Image::createThumbnailName($path, $width, $height, 'keep_ratio');
			$fileUrl = '/uploaded/thumbnails/'.$thumbName;
			if (file_exists(PATH_APP.$fileUrl))
				return $fileUrl;
				
			$errThumbName = Phpr_Image::createThumbnailName($path, $width, $height, 'keep_ratio_err');
			$errFileUrl = '/uploaded/thumbnails/'.$errThumbName;

			if (file_exists(PATH_APP.$errFileUrl))
				return $errFileUrl;

			$this->deleteThumbs($thumbName);

			try
			{
				Phpr_Image::makeThumbnail(
					PATH_APP.$path, 
					PATH_APP.'/uploaded/thumbnails/'.$thumbName, $width, $height, false, 'keep_ratio');
			} catch (Exception $ex)
			{
				@copy(
					PATH_APP.'/modules/cms/resources/images/error_thumb.jpg', 
					PATH_APP.'/uploaded/thumbnails/'.$errThumbName);
					
				return $errFileUrl;
			}

			return $fileUrl;
		}
		
		public function filebrowserGetUploadUrl($path)
		{
			$url = Backend_Html::controllerUrl();
			$url = substr($url, 0, -1);
			
			$parts = array(
				$url,
				'filebrowser_file_upload',
				Phpr::$security->getTicket(),
				base64_encode($path)
			);

			return implode('/', $parts);
		}
		

		public function onLoadResourceUploader()
		{
			$this->viewData['file_path'] = post('dir');
			$this->renderPartial('upload_form');
		}
		
		public function filebrowser_file_upload($ticket, $path)
		{
			$this->_controller->suppressView();

			$result = array();
			try
			{
				if (!Phpr::$security->validateTicket($ticket, true))
					throw new Phpr_ApplicationException('Authorization error.');

				if (!array_key_exists('file', $_FILES))
					throw new Phpr_ApplicationException('File was not uploaded.');

				$pathValidated = $this->pathValidated(base64_decode($path), false);

				if ($pathValidated)
				{
					$allowed_dirs = array(
						'/'.Cms_SettingsManager::get()->resources_dir_path,
						'/themes'
					);
					
					$allowed = false;
					foreach ($allowed_dirs as $dir)
					{
						$pos = strpos($pathValidated, $dir);
						if ($pos !== false)
						{
							$allowed = true;
							break;
						}
					}
					
					if (!$allowed)
						throw new Phpr_ApplicationException('Uploading files into this directory is not allowed.');
					
					$path = PATH_APP.$pathValidated;
					if (!is_writable($path))
						throw new Phpr_ApplicationException('Directory is not writable.');
					
					$destPath = $path.'/'.$_FILES['file']['name'];
					
					if (!file_exists($destPath) || post('override_files'))
					{
						if ( !@move_uploaded_file($_FILES['file']["tmp_name"], $destPath) )
							throw new Phpr_SystemException( "Error copying file to $destPath." );
					} else
						throw new Phpr_ApplicationException('File already exists.');
				}

				$result['result'] = 'success';
			}
			catch (Exception $ex)
			{
				$result['result'] = 'failed';
				$result['error'] = $ex->getMessage();
			}
			
			header('Content-type: application/json');
			echo json_encode($result);
		}

		protected function deleteThumbs($path)
		{
			Phpr_Image::deleteImageThumbnails($path);
		}
	}
	
	function backend_filebrowser_sort_files($a, $b)
	{
		if ($a->time == $b->time)
			return 0;
			
		return $a->time < $b->time ? 1 : -1;
	}

?>