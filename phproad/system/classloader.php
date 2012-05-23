<?

	/**
	 * Class loader
	 * This class is used by the PHP Road internally for finding and loading classes.
	 * The instance of this class is available in the Phpr global object: Phpr::$classLoader.
	 */
	class Phpr_ClassLoader {
		private $directory_cache;
		private $module_paths;
		private $library_paths;
		
		public function __construct() {
			$this->directory_cache = array();
			$this->application_paths = array(PATH_APP, PATH_SYSTEM);
			$this->library_paths = array('controllers', 'classes', 'controls/controllers', 'components/controllers', 'models');
			$this->module_paths = array('controls/controllers', 'components/controllers', 'classes', 'helpers', 'models', 'behaviors', 'controllers', 'shipping_types', 'payment_types');
			$this->ns_library_paths = array();
		}
		
		private function file_exists($file_path) {
			try {
				$dir = dirname($file_path);

				if(!array_key_exists($dir, $this->directory_cache)) {
					if(is_dir($dir))
						$this->directory_cache[$dir] = scandir($dir);
					else
						$this->directory_cache[$dir] = array();
				}

				$result = in_array(basename($file_path), $this->directory_cache[$dir]); 
			} 
			catch(exception $ex) {
				echo $file_path . '  ';
				echo $ex->getMessage();
			}
			
			return $result;
		}

		/**
		 * Attempts to find a class in the module directory.
		 * @param string $module_path Specifies a path to the module
		 * @param string &$class_name Specifies the name of the class to load
		 * @param string &$file_name Specifies a name of the class source file.
		 * @return boolean Determines whether the class was found.
		 */
		private function lookup_module($module_path, &$class_name, &$file_name) {
			if(!$module_path)
				return false;

			foreach($this->module_paths as $path) {
				$full_path = $module_path . '/' . $path . '/' . $file_name . '.' . PHPR_EXT;

				if($this->file_exists($full_path)) {
					include($full_path);
					
					if(class_exists($class_name))
						return true;
				}
			}

			return false;
		}

		/**
		 * Registers a class library directory.
		 * Use this method to register a directory containing your application classes.
		 * @param string $path Specifies a full path to the directory. No trailing slash.
		 */
		public function add_library_directory($path) {
			array_unshift($this->library_paths, $path);
		}
		
		/**
		 * Registers a application directory.
		 * Use this method to register a directory containing your application classes.
		 * @param string $path Specifies a full path to the directory. No trailing slash.
		 */
		public function add_application_directory($path) {
			array_unshift($this->application_paths, $path);
		}
		
		/**
		 * Registers a module directory.
		 * Use this method to register a directory containing your module classes.
		 * @param string $path Specifies a full path to the directory. No trailing slash.
		 */
		public function add_module_directory($path) {
			array_unshift($this->module_paths, $path);
		}
		
		/**
		 * Registers a directory for loading classes by namespaces.
		 * Use this method to register a directory containing application or module classes for loading
		 * classes by class namespaces. This feature is supported only since PHP version 5.3.0.
		 * @param string $path Specifies a full path to the directory. No trailing slash.
		 */
		public function add_namespace_library_directory($path) {
			array_unshift($this->ns_library_paths, $path);
		}

		/**
		 * Loads the class.
		 * @param string $class_name Specifies the name of the class to load
		 * @param bool $force_disabled Forces loading disabled module classes
		 * @return bool Determines whether the class requested was found
		 */
		public function load($class_name, $force_disabled = false) {
			global $CONFIG;

			if(class_exists($class_name))
				return true;

			$file_name = strtolower($class_name);
			
			// Check registered libraries
			foreach($this->library_paths as $path) {
				$full_path = $path . '/' . $file_name . '.' . PHPR_EXT;
				
				if(!$this->file_exists($full_path))
					continue;
					
				include($full_path);
				
				if(class_exists($class_name))
					return true;
			}
			
			// Check registered namespace libraries
			if(strpos($class_name,'\\') !==false) {
				$file_name = str_replace('\\', '/', $class_name);
				foreach ($this->ns_library_paths as $path) {
					$full_path = $path .'/'. $file_name . '.' . PHPR_EXT;
					
					if(!$this->file_exists($full_path))
						continue;

					include($full_path);

					if(class_exists($class_name))
						return true;
				}
			}

			// Look up in the modules
			$underscore_position = strpos($class_name, '_');
			$module_name = strtolower($underscore_position !== false ? substr($class_name, 0, $underscore_position) : $class_name);
			
			$disabled_modules = isset($CONFIG['DISABLE_MODULES']) ? $CONFIG['DISABLE_MODULES'] : array();

			foreach($this->application_paths as $path) {
				if(in_array($module_name, $disabled_modules) && !$force_disabled)
					continue;
				
				if($this->lookup_module($path . '/modules/' . $module_name, $class_name, $file_name))
					return true;
			}

			return false;
		}

		/**
		 * Loads a controller by the class name and returns the controller instance.
		 * Controller must be situated in the application controllers directory, otherwise it will not be loaded.
		 * @param string $class_name Specifies a name of the controller to load.
		 * @param string $controller_path Specifies a path to the controller directory.
		 * @return Phpr_Controller The controller instance or null.
		 */
		public function load_controller($class_name, $controller_directory = null) {
			foreach($this->application_paths as $path) {
				$controller_path = ($controller_directory !== null) ? $path . '/' . $controller_directory : $path . '/controllers';
				$controller_path = realpath($controller_path . '/' . strtolower($class_name) . '.' . PHPR_EXT);
	
				// Determine whether the controller source file exists and situated in the application controllers directory.
				if(!strlen($controller_path))
					continue;
	
				if(!class_exists($class_name)) {
					require_once $controller_path;
	
					// Return null if class is not found
					if(!class_exists($class_name))
						continue;
					
					// Return the class instance
					return Phpr_Controller::$current = new $class_name();
				}
	
				// If the class requested is declared, determine whether its source file is in the application controllers directory.
				$class_info = new ReflectionClass($class_name);
				if($class_info->getFileName() !== $controller_path)
					continue; // this is the wrong controller
				
				// Return the class instance
				return Phpr_Controller::$current = new $class_name();
			}
		}
		
		/**
		 * @deprecated
		 */
		public function addDirectory($path) {
			return $this->add_library_directory($path);
		}
		
		/**
		 * @deprecated
		 */
		public function loadController($class_name, $controller_path = null) {
			return $this->load_controller($class_name, $controller_path);
		}
	}