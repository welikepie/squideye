<?

	class Cms_VisitorPreferences
	{
		public static function set($name, $value)
		{
			$params = Phpr::$session->get('cms_visitor_preferences', array());
			$params[$name] = serialize($value);
			
			Phpr::$session->set('cms_visitor_preferences', $params);

			return;
		}

		public static function get( $name, $default = null)
		{
			$params = Phpr::$session->get('cms_visitor_preferences', array());
			if (array_key_exists($name, $params))
			{
				$value = $params[$name];
				if (strlen($value))
				try
				{
					return @unserialize($value);
				} catch (Exception $ex){}
				
				return $value;
			}
			
			return $default;
		}
	}

?>