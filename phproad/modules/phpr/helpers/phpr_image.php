<?php

	/**
	 * Image manipulation functions
	 */

	class Phpr_Image
	{
		/**
		 * Creates a thumbnail
		 * @param string $srcPath Specifies a sources image path
		 * @param string $destPath Specifies a destination image path
		 * @param mixed $destWidth Specifies a destination image width. Can have integer value or string 'auto'.
		 * @param mixed $destHeight Specifies a destination image height. Can have integer value or string 'auto'.
		 * @param string $mode Specifies a scaling mode. Possible values: keep_ratio, fit. It works only if both width and height are specified as numbers.
		 * @param string $returnJpeg - returns JPEG (if true) or PNG image
		 */
		public static function makeThumbnail($srcPath, $destPath, $destWidth, $destHeight, $forceGd = false, $mode = 'keep_ratio', $returnJpeg = true)
		{
			$extension = null;
			$pathInfo = pathinfo($srcPath);
			if (isset($pathInfo['extension']))
				$extension = strtolower($pathInfo['extension']);
				
			$allowedExtensions = array('gif', 'jpeg', 'jpg','png');
			if (!in_array($extension, $allowedExtensions))
				throw new Phpr_ApplicationException('Unknown image format');
				
			if (!preg_match('/^[0-9]*!?$/', $destWidth) && $destWidth != 'auto')
				throw new Phpr_ApplicationException("Invalid width specifier. Please use integer or 'auto' value.");

			if (!preg_match('/^[0-9]*!?$/', $destHeight) && $destHeight != 'auto')
				throw new Phpr_ApplicationException("Invalid height specifier. Please use integer or 'auto' value.");

			list($width_orig, $height_orig) = getimagesize($srcPath);
			$ratio_orig = $width_orig/$height_orig;

			$centerImage = false;

			if ($destWidth == 'auto' && $destHeight == 'auto')
			{
				$width = $width_orig;
				$height = $height_orig;
			}
			elseif ($destWidth == 'auto' && $destHeight != 'auto')
			{
				if (substr($destHeight, -1) == '!')
				{
					$destHeight = substr($destHeight, 0, -1);
					$height = $destHeight;
				}
				else
					$height = $height_orig > $destHeight ? $destHeight : $height_orig;

				$width = $height*$ratio_orig;
			} elseif ($destHeight == 'auto' && $destWidth != 'auto')
			{
				if (substr($destWidth, -1) == '!')
				{
					$destWidth = substr($destWidth, 0, -1);
					$width = $destWidth;
				}
				else
					$width = $width_orig > $destWidth ? $destWidth : $width_orig;

				$height = $width/$ratio_orig;
			}
			else
			{
				// Width and height specified as numbers
				//
				
				if ($mode == 'keep_ratio')
				{
					if ($destWidth/$destHeight > $ratio_orig)
					{
						$width = $destHeight*$ratio_orig;
						$height = $destHeight;
					}
					else
					{
						$height = $destWidth/$ratio_orig;
						$width = $destWidth;
					}
					
					$centerImage = true;
					$imgWidth = $destWidth;
					$imgHeight = $destHeight;
				} else
				{
					$height = $destHeight;
					$width = $destWidth;
				}
			}

			if (!$centerImage)
			{
				$imgWidth = $width;
				$imgHeight = $height;
			}

			if (!Phpr::$config->get('IMAGEMAGICK_ENABLED') || $forceGd)
			{
				$image_p = imagecreatetruecolor($imgWidth, $imgHeight);

				$image = self::create_image($extension, $srcPath);
				if ($image == null)
					throw new Phpr_ApplicationException('Error loading the source image');

				if (!$returnJpeg)
				{
					imagealphablending( $image_p, false );
					imagesavealpha( $image_p, true );
				}

				$white = imagecolorallocate($image_p, 255, 255, 255);
				imagefilledrectangle($image_p, 0, 0, $imgWidth, $imgHeight, $white);

				$dest_x = 0;
				$dest_y = 0;

				if ($centerImage)
				{
					$dest_x = ceil($imgWidth/2 - $width/2);
					$dest_y = ceil($imgHeight/2 - $height/2);
				}

				imagecopyresampled($image_p, $image, $dest_x, $dest_y, 0, 0, $width, $height, $width_orig, $height_orig);
				
				if ($returnJpeg)
					imagejpeg($image_p, $destPath, Phpr::$config->get('IMAGE_JPEG_QUALITY', 70));
				else
					imagepng($image_p, $destPath, 8);

				@chmod($destPath, Phpr_Files::getFilePermissions());
				
				imagedestroy($image_p);
				imagedestroy($image);
			} else
				self::im_resample($srcPath, $destPath, $width, $height, $imgWidth, $imgHeight, $returnJpeg);
		}
		
		private static function create_image($extension, $srcPath)
		{
			switch ($extension) 
			{
				case 'jpeg' :
				case 'jpg' :
					return @imagecreatefromjpeg($srcPath);
				case 'png' : 
					return @imagecreatefrompng($srcPath);
				case 'gif' :
					return @imagecreatefromgif($srcPath);
			}
			
			return null;
		}
		
		private static function im_resample($origPath, $destPath, $width, $height, $imgWidth, $imgHeight, $returnJpeg = true)
		{
			try
			{
				$currentDir = 'im'.(time()+rand(1, 100000));
				$tmpDir = PATH_APP.'/temp/';
				if (!file_exists($tmpDir) || !is_writable($tmpDir))
					throw new Phpr_SystemException('Directory '.$tmpDir.' is not writable for PHP.');

				if ( !@mkdir($tmpDir.$currentDir) )
					throw new Phpr_SystemException('Error creating image magic directory in '.$tmpDir.$currentDir);

				@chmod($tmpDir.$currentDir, Phpr_Files::getFolderPermissions());
				
				$imPath = Phpr::$config->get('IMAGEMAGICK_PATH');
				$sysPaths = getenv('PATH');
				if (strlen($imPath))
				{
					$sysPaths .= ':'.$imPath;
					putenv('PATH='.$sysPaths);
				}

				$outputFile = './output';
				$output = array();
				
				chdir($tmpDir.$currentDir);

				if (strlen($imPath))
					$imPath .= '/';

				$JpegQuality = Phpr::$config->get('IMAGE_JPEG_QUALITY', 70);

				$convert_binaries = array('convert', 'convert.exe');
				$convert_strings = array();
				
				$jpegColorspace = Phpr::$config->get('IMAGE_JPEG_COLORSPACE', 'RGB');
				
				foreach($convert_binaries as $convert_binary) {
					if($returnJpeg)
						$convert_string = '"'.$imPath.$convert_binary.'" "'.$origPath.'" -colorspace '.$jpegColorspace.' -antialias -quality '.$JpegQuality.' -thumbnail "'.$imgWidth.'x'.$imgHeight.'>" -bordercolor white -border 1000 -gravity center -crop '.$imgWidth.'x'.$imgHeight.'+0+0 +repage JPEG:'.$outputFile;
					else
						$convert_string = '"'.$imPath.$convert_binary.'" "'.$origPath.'" -antialias -background none -thumbnail "'.$imgWidth.'x'.$imgHeight.'>" -gravity center -crop '.$imgWidth.'x'.$imgHeight.'+0+0 +repage PNG:'.$outputFile;
						
					try {
						$Res = shell_exec($convert_string);
					}
					catch(Exception $ex) {
						$Res = exec($convert_string);
					}
					
					$resultFileDir = $tmpDir . $currentDir;
	
					$file1Exists = file_exists($resultFileDir . '/output');
					$file2Exists = file_exists($resultFileDir . '/output-0');
					
					if(!$file1Exists && !$file2Exists) {
						$convert_strings[] = $convert_string;
					}
					else {
						break;
					}
				}
				
				if(!$file1Exists && !$file2Exists)
					throw new Phpr_ApplicationException("Error converting image with ImageMagick. IM commands: \n\n" . implode($convert_strings, "\n\n") . "\n\n");
				
				if ($file1Exists)
					copy($resultFileDir.'/output', $destPath);
				else	
					copy($resultFileDir.'/output-0', $destPath);
					
				if (file_exists($destPath))
					@chmod($destPath, Phpr_Files::getFilePermissions());
				
				if (file_exists($tmpDir.$currentDir))
					Phpr_Files::removeDir($tmpDir.$currentDir);
			}
			catch (Exception $ex)
			{
				if (file_exists($tmpDir.$currentDir))
					Phpr_Files::removeDir($tmpDir.$currentDir);

				throw $ex;
			}
		}

		/**
		 * Returns a thumbnail file name, unique for a specified 
		 * original file location, file modification time, thumbnail size and scaling mode
		 * @param string $path Specifies a source image path.
		 * @param mixed $width Specifies a thumbnail width. Can have integer value or string 'auto'.
		 * @param mixed $height Specifies a thumbnail height. Can have integer value or string 'auto'.
		 * @param string $mode Specifies a scaling mode. 
		 * @return string
		 */
		public static function createThumbnailName($path, $width, $height, $mode = 'keep_ratio')
		{
			return md5(dirname($path)).basename($path).'_'.filemtime(PATH_APP.$path).'_'.$width.'x'.$height.'_'.$mode.'.jpg';
		}

		/**
		 * Deletes thumbnails of a specified image
		 * @param string $path Specifies a source image path.
		 */
		public static function deleteImageThumbnails($path)
		{
			$thumbName = md5(dirname($path)).basename($path).'_*.jpg';

			$thumbPath = PATH_APP.'/uploaded/thumbnails/'.$thumbName;
			$thumbs = glob($thumbPath);
			if (is_array($thumbs))
			{
				foreach ($thumbs as $filename) 
				    @unlink($filename);
			}

		}
	}

?>