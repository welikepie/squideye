<?php

	/**
	 * This class adds some Core functions and filters to Twig engine.
	 */
	class Core_TwigExtension extends Twig_Extension
	{
		public static function create()
		{
			return new self();
		}
		
		public function getName()
		{
			return 'Core extension';
		}
		
		public function getFunctions()
		{
			return array(
				'method',
				'field'
			);
		}
		
		public function getFilters()
		{
			return array(
				'currency' => new Twig_Filter_Method($this, 'currency_filter'),
				'unescape' => new Twig_Filter_Method($this, 'unescape_filter', array('is_safe' => array('html'))),
				'unset' => new Twig_Filter_Method($this, 'unset_filter'),
				'repeat' => new Twig_Filter_Method($this, 'repeat_filter')
			);
		}
		
		public function getTests()
		{
			return array(
				'instance_of' => new Twig_Filter_Method($this, 'instance_of_test'),
				'array' => new Twig_Filter_Method($this, 'array_test'),
			);
		}
		
		public function instance_of_test($obj, $class_name)
		{
			return $obj instanceof $class_name;
		}
		
		public function array_test($var)
		{
			return is_array($var);
		}
		
		public function method()
		{
			$args = func_get_args();
			
			if (count($args) < 2)
				throw new Twig_Error_Runtime('The method() function should have at least 2 arguments - the class name and the method name.');
				
			$class = trim(array_shift($args));
			$method = trim(array_shift($args));
			
			if (!Core_Configuration::is_php_allowed())
			{
				$prohibited_class_map = Phpr::$config->get('CORE_PROHIBITED_CLASS_MAP', array());
				if ($prohibited_class_map)
				{
					foreach ($prohibited_class_map as $prohibited_class_name=>$prohibited_methods)
					{
						if (strtoupper($prohibited_class_name) == strtoupper($class) || $class instanceof $prohibited_class_name)
						{
							if ($prohibited_methods == '*')
								throw new Twig_Error_Runtime(sprintf('Using the %s class in Twig templates is prohibited.', $class));
								
							foreach ($prohibited_methods as $prohibited_method)
							{
								$prohibited_method = strtoupper($prohibited_method);
								if ($prohibited_method == strtoupper($method))
									throw new Twig_Error_Runtime(sprintf('Using the %s::%s() method in Twig templates is prohibited.', $class, $method));
							}
						}
					}
				}
			}
			
			if (class_exists($class) && method_exists($class, $method))
				return call_user_func_array(array($class, $method), $args);
				
			return null;
		}
		
		public function field($object, $field)
		{
			return $object->$field;
		}
		
		public function currency_filter($num, $decimals = 2)
		{
			return format_currency($num, $decimals);
		}
		
		public function unescape_filter($value)
		{
			return $value;
		}
		
		public function unset_filter($array, $element)
		{
			if (array_key_exists($element, $array))
				unset($array[$element]);
				
			return $array;
		}
		
		public function repeat_filter($str, $count)
		{
			return str_repeat($str, $count);
		}
	}

?>