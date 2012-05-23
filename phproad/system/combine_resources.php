<?
	/**
	 * PHP Road
	 *
	 * JavaScript/CSS resources combining
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov
	 */

	if (!isset($_GET['file'])) 
		exit();
		
	function phpr_minify_css($css) 
	{
		$css = preg_replace( '#\s+#', ' ', $css );
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = str_replace( '; ', ';', $css );
		$css = str_replace( ': ', ':', $css );
		$css = str_replace( ' {', '{', $css );
		$css = str_replace( '{ ', '{', $css );
		$css = str_replace( ', ', ',', $css );
		$css = str_replace( '} ', '}', $css );
		$css = str_replace( ';}', '}', $css );

		return trim( $css );
	}
	
	function phpr_is_remote_file($path)
	{
		return substr($path, 0, 7) === 'http://' || substr($path, 0, 8) === 'https://';
	}

	define( "PATH_APP", str_replace("\\", "/", realpath(dirname(dirname(dirname(__FILE__))))));
		
	include(PATH_APP . '/config/config.php'); // we need to load the config when it has access to PATH_APP for $allowed_paths

	$allowed_types = isset($CONFIG['ALLOWED_RESOURCE_EXTENSIONS']) ? $CONFIG['ALLOWED_RESOURCE_EXTENSIONS'] : array('js', 'css');
	$allowed_paths = isset($CONFIG['ALLOWED_RESOURCE_PATHS']) ? $CONFIG['ALLOWED_RESOURCE_PATHS'] : array(PATH_APP);
	$symbolic_links = isset($CONFIG['RESOURCE_SYMLINKS']) ? $CONFIG['RESOURCE_SYMLINKS'] : array();
	
	$recache = isset($_GET['reset_cache']);
	$skip_cache = isset($_GET['skip_cache']);
	$src_mode = isset($_GET['src_mode']);

	$files = $_GET['file'];
	$assets = array();
	
	/*
	 * Prepare the asset list
	 */
	
	$aliases = array(
		'mootools'=>'/modules/cms/resources/javascript/mootools_src.js',
		'ls_core_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
		'ls_core_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
		'jquery'=>'/modules/cms/resources/javascript/jquery_src.js',
		'jquery_noconflict'=>'/modules/cms/resources/javascript/jquery_noconflict.js',
		'ls_styles'=>'/modules/cms/resources/css/frontend_css.css',
		'frontend_mootools'=>'/modules/cms/resources/javascript/ls_mootools_core_src.js',
		'frontend_jquery'=>'/modules/cms/resources/javascript/ls_jquery_core_src.js',
		'frontend_styles'=>'/modules/cms/resources/css/frontend_css.css'
	);
	
	foreach($files as $url) 
	{
		$allowed = false; // is this file allowed to be an asset?
		
		$file = $orig_url = str_replace(chr(0), '', urldecode($url));
		if (array_key_exists($file, $aliases))
			$file = $aliases[$file];

		$type = pathinfo(strtolower($file), PATHINFO_EXTENSION);
		
		if(!in_array($type, $allowed_types))
			continue;
		
		if (!phpr_is_remote_file($file)) {
			$file = str_replace('\\', '/', realpath(PATH_APP . $file));
			
			foreach($allowed_paths as $allowed_path) {
				if($allowed)
					continue; // we already found this file in the allowed paths
			
				$is_relative = strpos($allowed_path, '/') !== 0 && strpos($allowed_path, ':') !== 1;
				
				if($is_relative)
					$allowed_path = PATH_APP . '/' . $allowed_path; // allow relative path such as ../

				$allowed_path = str_replace('\\', '/', realpath($allowed_path));

				if(strpos($file, $allowed_path) === 0) {
					$allowed = true; // the file is allowed to be an asset because it matches the requirements (allowed paths)
				}
			}
		} else
			$allowed = true; // always allow remote files
		
		if(!$allowed) // not allowed to be an asset
			continue;

		$assets[$orig_url] = $file;
	}

	/*
	 * Check whether GZIP is supported by the browser
	 */ 
	$supportsGzip = false;
	$encodings = array();
	if (isset($_SERVER['HTTP_ACCEPT_ENCODING']))
		$encodings = explode(',', strtolower(preg_replace('/\s+/', '', $_SERVER['HTTP_ACCEPT_ENCODING'])));

	if (
			(in_array('gzip', $encodings) || in_array('x-gzip', $encodings) || isset($_SERVER['---------------'])) 
			&& function_exists('ob_gzhandler') 
			&& !ini_get('zlib.output_compression')
	)
	{
		$enc = in_array('x-gzip', $encodings) ? 'x-gzip' : 'gzip';
		$supportsGzip = true;
	}

	/*
	 * Caching
	 */
	 
	function phpr_get_resource_type() {
		$type = null;
		
		if(preg_match('#ls_(.+)_combine#simU', htmlentities($_GET['q'], ENT_COMPAT, 'UTF-8'), $match)) { // htmlentities just incase something malicious (just being safe)
			$type = $match[1];
		}
	
		return $type;
	}
	 
	$type = phpr_get_resource_type();
	
	$mime = 'text/' . ($type == 'js' ? 'javascript' : $type);
	
	$cache_path = PATH_APP . '/temp/resource_cache';
	if (!file_exists($cache_path))
		mkdir($cache_path);

	$cache_hash = sha1(implode(',', $assets));

	$cache_filename = $cache_path.'/'.$cache_hash.'.'.$type;
	if ($supportsGzip) 
		$cache_filename .= '.gz';
		
	$cache_exists = file_exists($cache_filename);

	if ($recache && $cache_exists)
		@unlink($cache_filename);

	$assets_mod_time = 0;
	foreach($assets as $file) 
	{
		if (!phpr_is_remote_file($file))
		{
			if (file_exists($file))
				$assets_mod_time = max($assets_mod_time, filemtime($file));
		} else
		{
			/*
			 * We cannot reliably check the modification time of a remote resource,
			 * because time on the remote server could not exactly match the time
			 * on this server.
			 */

			//$assets_mod_time = 0;
		}
	}

	$cached_mod_time = $cache_exists ? (int) @filemtime($cache_filename) : 0;
	
	if ($type == 'css')
		require PATH_APP.'/phproad/thirdpart/csscompressor/UriRewriter.php';
		
	$enable_remote_resources = !isset($CONFIG['ENABLE_REMOTE_RESOURCES']) || $CONFIG['ENABLE_REMOTE_RESOURCES'];

	$content = '';
	if ($skip_cache || $cached_mod_time < $assets_mod_time || !$cache_exists)
	{
		foreach($assets as $orig_url=>$file) 
		{
			$is_remote = phpr_is_remote_file($file);
			
			if($is_remote && !$enable_remote_resources)
				continue;
			
			if (file_exists($file) || $is_remote)
			{
				$data = @file_get_contents($file) . "\r\n";

				if ($type == 'css')
				{
					if (!$is_remote)
					{
						$data = Minify_CSS_UriRewriter::rewrite(
							$data,
							dirname($file),
							null,
							$symbolic_links
						);
					} else 
					{
						$data = Minify_CSS_UriRewriter::prepend(
							$data,
							dirname($file).'/'
						);
					}
				}
				
				$content .= $data;
			}
			else
				$content .= sprintf("\r\n/* Asset Error: asset %s not found. */\r\n", $orig_url);
		}

		if ($type == 'javascript' && !$src_mode)
		{
			require PATH_APP.'/phproad/thirdpart/javascriptpacker/JSMin.php';
			$content = JSMin::minify($content);
		} elseif ($type == 'css' && !$src_mode)
		{
			$content = phpr_minify_css($content);
		}

		if ($supportsGzip)
			$content = gzencode($content, 9, FORCE_GZIP);

		if (!$skip_cache)
			@file_put_contents($cache_filename, $content);
	}
	elseif (
		isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) &&
		$assets_mod_time <= strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])) 
	{
			header('Content-Type: ' . $mime);
			if (php_sapi_name() == 'CGI')
				header('Status: 304 Not Modified');
			else
				header('HTTP/1.0 304 Not Modified');

			exit();
	} 
	elseif (file_exists($cache_filename)) 
		$content = @file_get_contents($cache_filename);

	/*
	 * Output
	 */

	header('Content-Type: ' . $mime);
	header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $assets_mod_time).' GMT');
	
	if ($supportsGzip) 
	{
		header('Vary: Accept-Encoding');  // Handle proxies
		header('Content-Encoding: ' . $enc);
	}

	echo $content;
?>