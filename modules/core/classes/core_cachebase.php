<?
	/**
	 * Base class for caching classes
	 */
	abstract class Core_CacheBase
	{
		protected static $instance = null;
		public static $disabled = false;
		
		/**
		 * Returns a cache class instance
		 */
		public static function create()
		{
			if (self::$instance !== null)
				return self::$instance;

			$caching_config = Phpr::$config->get('CACHING');
			$params = array();
			$class_name = 'Core_DummyCache';

			if ($caching_config)
			{
				$class_name = isset($caching_config['CLASS_NAME']) ? $caching_config['CLASS_NAME'] : null;
				if (!$class_name)
					throw new Phpr_SystemException('Caching class name is not specified in the caching configuration.');

				$params = isset($caching_config['PARAMS']) ? $caching_config['PARAMS'] : array();
				self::$disabled = isset($caching_config['DISABLED']) ? $caching_config['DISABLED'] : false;
			}

			return self::$instance = new $class_name($params);
		}
		
		/**
		 * Creates a key string, which value depends on different current conditions,
		 * for example on the current page URL or on the catalog contents version.
		 * @param string $prefix A prefix string
		 * @param bool &$recache Indicates whether the item should be recached because of
		 * catalog, CMS content, or blog content versions have been changed.
		 * @param array $vary_by A list of vary-by parameters. 
		 * @param array $version A list content system content types to depend on
		 * @return string
		 */
		public static function create_key($prefix, &$recache, $vary_by = array(), $version = array())
		{
			$result = $prefix;
			$recache = false;
			
			$vary_by = Phpr_Util::splat($vary_by);
			$version = Phpr_Util::splat($version);
			
			$obj = self::create();

			$controller = Cms_Controller::get_instance();
			foreach ($vary_by as $key=>$param_name)
			{
				if (is_int($key))
				{
					switch ($param_name)
					{
						case 'url' : 
							$url = Phpr::$request->getCurrentUrl();
							$caching_params = Phpr::$config->get('CACHING', array());
							$reset_cache_key = array_key_exists('RESET_PAGE_CACHE_KEY', $caching_params) ? $caching_params['RESET_PAGE_CACHE_KEY'] : null;
							
							if ($reset_cache_key)
							{
								$url = str_replace('?'.$reset_cache_key.'=1', '', $url);
								$url = str_replace('&'.$reset_cache_key.'=1', '', $url);
							}
							
							$result .= $url;
						break;
						case 'customer' :
							if ($controller)
							{
								$customer = $controller->get_customer();
								if ($customer)
									$result .= '-' .$customer->id;
							}
						break;
						case 'customer_group' :
							if ($controller)
								$result .= '-' .$controller->get_customer_group_id();
						break;
						case 'customer_presence' :
							if ($controller)
							{
								$customer = $controller->get_customer();
								if ($customer)
									$result .= '-customer';
							}
						break;
						default:
							throw new Phpr_SystemException('Unknown cache vary-by parameter: '.$param_name);
						break;
					}
				} else {
					$result .= '-'.$key.':';
					if (!is_string($param_name) && !is_int($param_name))
						$param_name = serialize($param_name);

					$result .= $param_name;
				}
			}
			
			$result = $prefix.'_'.sha1($result);
			
			foreach ($version as $param_name)
			{
			    $result_key = sha1($result);
				$key_versions = $obj->get('sys_key_vrs_'.$result_key);
				
				switch ($param_name)
				{
					case 'catalog' :
						$prev_version_value = ($key_versions && array_key_exists('catalog', $key_versions)) ? $key_versions['catalog'] : 0;
						$new_version_value = Shop_Module::get_catalog_version();
						$recache = $recache || ($prev_version_value != $new_version_value);
						if ($recache)
							$key_versions['catalog'] = $new_version_value;
					break;
					case 'cms' :
						$prev_version_value = ($key_versions && array_key_exists('cms', $key_versions)) ? $key_versions['cms'] : 0;
						$new_version_value = Cms_Module::get_cms_content_version();
						$recache = $recache || ($prev_version_value != $new_version_value);
						if ($recache)
							$key_versions['cms'] = $new_version_value;
					break;
					case 'blog' :
						$prev_version_value = ($key_versions && array_key_exists('blog', $key_versions)) ? $key_versions['blog'] : 0;
						$new_version_value = Blog_Module::get_blog_content_version();
						$recache = $recache || ($prev_version_value != $new_version_value);
						if ($recache)
							$key_versions['blog'] = $new_version_value;
					break;
					default:
						throw new Phpr_SystemException('Unknown cache version parameter: '.$param_name);
					break;
				}
				
				if ($recache)
					$obj->set('sys_key_vrs_'.$result_key, $key_versions);
			}
			
			return $result;
		}
		
		/**
		 * Creates the caching class instance.
		 * @param mixed $Params Specifies the class configuration options
		 */
		public function __construct($params = array())
		{
		}
		
		/**
		 * Adds or updates value to the cache
		 * @param string $key The key that will be associated with the item.
		 * @param mixed $value The variable to store.
		 * @param int $ttl Time To Live; store var in the cache for ttl seconds. After the ttl has passed,
		 * the stored variable will be expunged from the cache (on the next request). If no ttl is supplied
		 * (or if the ttl is 0), the value will persist until it is removed from the cache manually
		 * @return bool Returns TRUE on success or FALSE on failure.
		 */
		public function set($key, $value, $ttl = null)
		{
			if (self::$disabled)
				return false;
				
			return $this->set_value($key, $value, $ttl);
		}

		/**
		 * Returns value from the cache
		 * @param mixed $key The key or array of keys to fetch.
		 */
		public function get($key)
		{
			if (self::$disabled)
				return false;
				
			return $this->get_value($key);
		}
		
		/**
		 * Adds or updates value to the cache
		 */
		abstract protected function set_value($key, $value, $ttl = null);

		/**
		 * Returns value from the cache
		 * @param mixed $key The key or array of keys to fetch.
		 */
		abstract protected function get_value($key);
	}
?>