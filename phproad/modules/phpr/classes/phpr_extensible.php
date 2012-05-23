<?

	/*
	 * Extensible object model
	 */
	class Phpr_Extensible extends Phpr_Extension {
		protected $extensible_data = array(
			'extensions' => array(),
			'methods' => array(),
			'dynamic_methods' => array()
		);
		
		public $implement;

		public function __construct() {
			if($this->implement !== null) {
				if(is_string($this->implement)) {
					$extensions = explode(',', $this->implement);
				}
				elseif(is_array($this->implement)) {
					$extensions = $this->implement;
				}
				else {
					throw new Phpr_SystemException(sprintf('Invalid "implement" value specified in ' . get_class($this) . ' class.'));
				}
				
				foreach($extensions as $extension){
					$class_name = trim($extension);
					
					$this->extend_with($class_name);
				}
			}
		}

		public function extend_with($extension, $recursion_extension = true, $proxy_model_class = null) {
			if(empty($extension))
				return $this;

			if(is_string($extension)) {
				$name = $extension;
				
				if(array_key_exists($name, $this->extensible_data['extensions'])) 
					throw new Phpr_SystemException(sprintf('Extension "%s" already added', $name));
					
				$this->extensible_data['extensions'][$name] = new $name($this, $proxy_model_class);
				$this->cache_methods($name);
			}
			else if(is_object($extension)) {
				$name = get_class($extension);
			
				if(array_key_exists($name, $this->extensible_data['extensions'])) 
					throw new Phpr_SystemException(sprintf('Extension "%s" already added', $name));
					
				$this->extensible_data['extensions'][$name] = $extension;
				$this->cache_methods($name);
			}
			
			if($recursion_extension && is_subclass_of($this->extensible_data['extensions'][$name], 'Phpr_Extensible')) {
				$this->extensible_data['extensions'][$name]->extend_with($this, false, $proxy_model_class);
			}
		}
		
		public function is_extended_with($class_name) {
			foreach($this->extensible_data['extensions'] as $name => $extension)
				if($name === $class_name)
					return true;
					
			return false;
		}
		
		public function get_extension($class_name) {
			if(array_key_exists($class_name, $this->extensible_data['extensions']))
				return $this->extensible_data['extensions'][$class_name];
				
			return null;
		}

		protected function cache_methods($extension) {
			foreach(get_class_methods($extension) as $symbol) {
				if($symbol === '__construct' || $this->extensible_data['extensions'][$extension]->extMethodIsHidden($symbol))
					continue;
				
				//if(isset($this->extensible_data['methods'][$symbol])) 
				//	throw new Phpr_SystemException(sprintf('Extension symbol "%s" already exported from "%s"', $symbol, $this->extensible_data['methods'][$symbol]));
					
				$this->extensible_data['methods'][$symbol] = $extension;
			}
		}

		public function __get($property) {
			// build a list of names to check for a property
			$property_names = array(
				$property,
				Phpr_Inflector::camelize($property),
				Phpr_Inflector::underscore($property)
			);
			
			// loop until we finding a property to match the name
			foreach($property_names as $property_name) {
				if(property_exists($this, $property_name)) 
					return $this->{$property_name};
	
				foreach($this->extensible_data['extensions'] as $extension) {
					if(property_exists($extension, $property_name))
						return $extension->{$property_name};
				}
			}
		
			return $this->get_property($property);
		}

		public function __set($property, $value) {
			// build a list of names to check for a property
			$property_names = array(
				$property,
				Phpr_Inflector::camelize($property),
				Phpr_Inflector::underscore($property)
			);
			
			// loop until we finding a property to match the name
			foreach($property_names as $property_name) {
				if(property_exists($this, $property_name)) {
					return $this->{$property_name} = $value;
				}
	
				foreach($this->extensible_data['extensions'] as $extension) {
					if(!isset($extension->{$property_name}))
						continue;
					
			 		return $extension->{$property_name} = $value;
				}
			}
			
			return $this->set_property($property, $value);
		}
		
		public function __unset($offset) {

		}

		public function __call($method, $arguments = null) {
			// build a list of names to check for a method
			$method_names = array(
				$method,
				Phpr_Inflector::camelize($method),
				Phpr_Inflector::underscore($method)
			);
			
			// loop until we finding a method to match the name
			foreach($method_names as $method_name) {
				if(method_exists($this, $method_name))
					return call_user_func_array(array($this, $method_name), $arguments);
	
				if(isset($this->extensible_data['methods'][$method_name])) {
					$extension = $this->extensible_data['methods'][$method_name];
					
					if(method_exists($extension, $method_name)) {
						//array_unshift($arguments, $this);
						return call_user_func_array(array($this->extensible_data['extensions'][$extension], $method_name), $arguments);
					}
				}
	
				if(isset($this->extensible_data['dynamic_methods'][$method_name])) {
					$extension = $this->extensible_data['dynamic_methods'][$method_name][0];
					
					if(method_exists($extension, $this->extensible_data['dynamic_methods'][$method_name][1])) 
						return call_user_func_array(array($extension, $this->extensible_data['dynamic_methods'][$method_name][1]), $arguments);
				}
			}
			
			return $this->call_method($method, $arguments);
		}
		
		public function method_exists($method) {
			// build a list of names to check for a method
			$method_names = array(
				$method,
				Phpr_Inflector::camelize($method),
				Phpr_Inflector::underscore($method)
			);
			
			// loop until we finding a method to match the name
			foreach($method_names as $method_name) {
				if(method_exists($this, $method_name) || isset($this->extensible_data['methods'][$method_name]) || isset($this->extensible_data['dynamic_methods'][$method_name]))
					return true;
			}
			
			return false;
		}
		
		public function add_dynamic_method($extension, $dynamic_name, $actual_name) {
			$this->extensible_data['dynamic_methods'][$dynamic_name] = array($extension, $actual_name);
		}

		public function get_property($property) {
			//throw new Phpr_SystemException('Property ' . $property . ' is not defined in class ' . get_class($this));
		}
		
		public function set_property($property, $value) {
			//throw new Phpr_SystemException('Property ' . $property . ' is not defined in class ' . get_class($this));
		}
		
		public function call_method($method, $arguments) {
			throw new Phpr_SystemException('Method ' . $method . ' is not defined in class ' . get_class($this));
		}
		
		/**
		 * @deprecated
		 */
		public function addDynamicMethod($extension, $dynamic_name, $actual_name) {
			return $this->add_dynamic_method($extension, $dynamic_name, $actual_name);
		}
		
		/**
		 * @deprecated
		 */
		public function methodExists($method) {
			return $this->method_exists($method);
		}
		
		/**
		 * @deprecated
		 */
		public function isExtendedWith($class_name) {
			return $this->is_extended_with($class_name);
		}
	}