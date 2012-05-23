<?
	
	class Net_Service {
		public function __construct($options = array('active' => true, 'max_connections' => 30)) {
			$this->settings = $options;
			$this->connections = array();
			$this->jobs = array();
	
			$this->mc = curl_multi_init();
		}
		
		public static function create($options = array('active' => true, 'max_connections' => 30)) {
			return new self($options);
		}
	
		public function run($request, $callback = null) {
			$c = curl_init();
	
			foreach($request->get_options() as $key => $value)
				curl_setopt($c, $key, $value);
	
			// we've got a callback so let's go asynchronous
			if($callback) {
				$this->jobs[] = array('request' => $request, 'handle' => $c, 'callback' => $callback);
			}
			// nope, no asio for us today
			else {
				$r = new Net_Response();
	
				$headers = array();
	
				ob_start();
				$r->data = curl_exec($c);
				ob_end_clean();
	
				$r->headers = $headers;
				$r->request = $request;
				$r->info = curl_getinfo($c);
				$r->status_code = $r->info['http_code'];
	
				curl_close($c);
	
				return $r;
			}
	
			$this->last_request = $request;
		}
	
		public function update() {
			if(!$this->settings['active'])
				return;
			
			while(count($this->connections) < $this->settings['max_connections'] && count($this->jobs) > 0) {
				$job = array_shift($this->jobs);
	
				$host = $job['request']->get_option(CURLOPT_URL);
	
				if(!$host)
					return $job['callback'](null);
	
				if(strpos($host, 'http') !== 0)
					$job['request']->set_option(CURLOPT_URL, 'http://' . $host);
	
				$host = parse_url($job['request']->get_option(CURLOPT_URL), PHP_URL_HOST);
	
				// check if the domain is bad and will block multicurl
				if(!$this->is_host_active($host)) {
					if($job['callback'] != null)
						if(phpversion() >= 5.3)
							$job['callback'](null);
						else
							call_user_func_array($job['callback'], array(null));
	
					continue;
				}
	
				$this->connections[$job['handle']] = array('request' => $job['request'], 'handle' => $job['handle'], 'callback' => $job['callback']);
	
				curl_multi_add_handle($this->mc, $job['handle']);
			}
	
			while(($status = curl_multi_exec($this->mc, $running)) == CURLM_CALL_MULTI_PERFORM)
				continue;
	
			if($status != CURLM_OK)
				return;
	
			while($item = curl_multi_info_read($this->mc)) {
				usleep(20000); // dont tie up the cpu
				
				$handle = $item['handle'];
	
				$connection = $this->connections[$handle];
			
				$info = curl_getinfo($handle);
	
				$data = curl_multi_getcontent($handle);
	
				curl_multi_remove_handle($this->mc, $handle);
	
				unset($this->connections[$handle]);
	
				$response = new Net_Response();
				$response->request = $connection['request'];
				$response->data = $data;
				$response->info = $info;
				$response->status_code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
	
				$this->last_response = $response;
	
				if($connection['callback'] != null)
					if(phpversion() >= 5.3)
						$connection['callback']($response);
					else
						call_user_func_array($connection['callback'], array($response));
			}
		}
	
		public function is_host_active($host) {
			if(!$host)
				return false;
				
			// if this isn't linux don't check it
			if(!stristr(PHP_OS, 'linux'))
				return true;
	
			// if this is an IP don't check it
			if(long2ip(ip2long($host)) == $host)
				return true;
	
			return true;
		}
	
		public function get_last_request() {
			return $this->last_request;
		}
	
		public function get_last_response() {
			return $this->last_response;
		}
	
		public function set_settings($settings) {
			foreach($settings as $name => $value)
				$this->settings[$name] = $value;
		}
	
		public function set_setting($name, $value) {
			$this->settings[$name] = $value;
		}
	
		public function get() {
			return $this->mc;
		}
	
		protected $settings;
		protected $mc;
		protected $connections;
		protected $jobs;
		protected $last_request;
		protected $last_response;
	}