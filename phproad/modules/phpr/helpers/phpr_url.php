<?

	/**
	 * PHP Road URL helper
	 *
	 * This class contains functions that may be useful for working with URLs.
	 */
	class Phpr_Url
	{
		/**
		 * Returns an URL of a specified resource relative to the LemonStand domain root
		 */
		public static function root_url($resource, $add_host_name_and_protocol = false, $protocol = null)
		{
			if (substr($resource, 0, 1) == '/')
				$resource = substr($resource, 1);

			$result = Phpr::$request->getSubdirectory().$resource;
			if ($add_host_name_and_protocol)
				$result = Phpr::$request->getRootUrl($protocol).$result;
				
			return $result;
		}
		
		/**
		 * Returns the URL of the website, as specified in the configuration WEBSITE_URL parameter.
		 * @param string $Resource Optional path to a resource. 
		 * Use this parameter to obtain the absolute URL of a resource.
		 * Example: Phpr_Url::siteUrl( 'images/button.gif' ) will return http://www.your-company.com/images/button.gif
		 * @param boolean $SuppressProtocol Indicates whether the protocol name (http, https) must be suppressed.
		 * @return string
		 */
		public static function siteUrl( $Resource = null, $SuppressProtocol = false )
		{
			$URL = Phpr::$config->get('WEBSITE_URL', null);

			if ( $SuppressProtocol )
			{
				$URL = str_replace( 'https://', '', $URL );
				$URL = str_replace( 'http://', '', $URL );
			}

			if ( $Resource === null || !strlen($Resource) )
				return $URL;

			if ( $URL !== null )
			{
				if ( $Resource{0} == '/' )
					$Resource = substr( $Resource, 1 );

				return $URL.'/'.$Resource;
			}

			return $Resource;
		}
		
		public static function get_params($url) {
			if(strpos($url, '/') === 0)
				$url = substr($url, 1);
			
			$segments = explode('/', $url);
			$params = array();
			
			foreach($segments as $segment) {
				if(strlen($segment))
					$params[] = $segment;
			}
			
			return $params;
		}
	}