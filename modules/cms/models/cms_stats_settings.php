<?php

	class Cms_Stats_Settings extends Backend_SettingsRecord
	{
		public $table_name = 'cms_stats_settings';
		public static $obj = null;
		
		public $keep_pageviews = 1000000;
		
		public $custom_columns = array('captcha_value'=>db_text, 'captcha_token'=>db_text);

		public static function get($className = null, $init_columns = true)
		{
			if (self::$obj !== null)
				return self::$obj;
			
			return self::$obj = parent::get('Cms_Stats_Settings');
		}

		public function define_columns($context = null)
		{
			$this->validation->setFormId('settings_form');
			
			$this->define_column('ga_enabled', 'Enable Google Analytics integration');
			$this->define_column('ga_siteid', 'Profile ID')->validation()->fn('trim');
			$this->define_column('ga_property_id', 'Web Property ID')->validation()->fn('trim');
			
			$this->define_column('ga_username', 'Email')->validation()->fn('trim')->Email(true);
			$this->define_column('ga_password', 'Password')->validation()->fn('trim');
			$this->define_column('ga_track_page_speed', 'Track page load time');
			$this->define_column('ga_domain_name', 'Domain name')->validation()->fn('trim');
			
			$this->define_column('enable_builtin_statistics', 'Enable built-in statistics');
			$this->define_column('keep_pageviews', 'Number of pageviews to keep')->validation()->fn('trim')->required();
			$this->define_column('ip_filters', 'IP Filters')->validation()->fn('trim');

			$this->define_column('dashboard_paid_only', 'Display only paid orders in the main chart');
			$this->define_column('dashboard_display_today', 'Display a current date in reports and charts');
		}
		
		public function define_form_fields($context = null)
		{
			$extraFieldClass = $this->ga_enabled ? 'separatedField' : null;
			$this->add_form_field('ga_enabled')->comment('Turning Google Analytics integration ON you allow the system to download reports from your Google Analytics account. If you disable this feature, the built-in statistics will be used.<br/><br/><strong>Important!</strong> In order to track E-Commerce transaction you need to enable E-Commerce reporting in the Google Analytics website profile.', 'below', true)->tab('Google Analytics')->renderAs(frm_onoffswitcher)->cssClassName($extraFieldClass);
			
			$this->add_form_partial('ga_hint')->tab('Google Analytics');

			$extraFieldClass = $this->ga_enabled ? null : 'hidden';
			$this->add_form_field('ga_siteid', 'left')->tab('Google Analytics')->cssClassName($extraFieldClass);
			$this->add_form_field('ga_property_id', 'right')->tab('Google Analytics')->cssClassName($extraFieldClass);

			$this->add_form_field('ga_username', 'left')->tab('Google Analytics')->comment('Email you use to log into Google Analytics', 'above')->cssClassName($extraFieldClass);
			$this->add_form_field('ga_password', 'right')->tab('Google Analytics')->renderAs(frm_password)->comment('Password you use to log into Google Analytics', 'above')->cssClassName($extraFieldClass);
			$this->add_form_field('ga_domain_name')->tab('Google Analytics')->cssClassName($extraFieldClass)->comment('Specify the store domain name to use <a href="http://code.google.com/apis/analytics/docs/tracking/gaTrackingSite.html" target="_blank">Google Analytics cross-domain tracking feature</a>. Leave the field blank to disable the cross-domain tracking for this website.', 'above', true);
			
			$this->add_form_field('ga_track_page_speed')->tab('Google Analytics')->cssClassName($extraFieldClass);

			$this->add_form_custom_area('ga_captcha')->tab('Google Analytics');
			
			$extraFieldClass = $this->enable_builtin_statistics ? 'separatedField' : null;
			$this->add_form_field('enable_builtin_statistics')->comment('Turn off the built-in statistics if you don\'t want LemonStand to store traffic information in the database.', 'below', true)->tab('Built-in Statistics')->renderAs(frm_onoffswitcher)->cssClassName($extraFieldClass);
			
			$extraFieldClass = $this->enable_builtin_statistics ? null : 'hidden';
			$this->add_form_field('keep_pageviews')->comment('How many pageviews to keep in the database. Large number of pageviews may slowdown the Dashboard page loading.', 'above')->tab('Built-in Statistics')->renderAs(frm_dropdown)->cssClassName($extraFieldClass);;
			$this->add_form_field('ip_filters')->tab('Built-in Statistics')->cssClassName($extraFieldClass);;
			
			$this->add_form_field('dashboard_paid_only')->tab('Dashboard')->comment('Use this checkbox if you want to see only paid order totals in the main dashboard chart.');
			$this->add_form_field('dashboard_display_today')->tab('Dashboard')->comment('Use this checkbox if you to include a current date to the dashboard reports and charts. If the Google Analytics integration enabled, the visitors statistic could be wrong for a current date.');
		}
		
		public function get_keep_pageviews_options($key_value = -1)
		{
			return array(
				50000=>50000,
				100000=>100000,
				500000=>500000,
				1000000=>1000000
			);
		}
		
		public function get_ga_tracking_code()
		{
			$propertyId = $this->ga_property_id;

			$result = "\n\t<script type=\"text/javascript\">
\tvar _gaq = _gaq || [];
\t_gaq.push(['_setAccount', '$propertyId']);
\t_gaq.push(['_trackPageview']);
";

			if ($this->ga_track_page_speed)
				$result .= "\t_gaq.push(['_trackPageLoadTime']);\n";
				
			if ($this->ga_domain_name)
				$result .= "\t_gaq.push(['_setDomainName', \"".Core_String::js_encode($this->ga_domain_name)."\"]);\n";
				
			return $result;
		}

		public function get_ga_tracker_close_declaration()
		{
			return "\t(function() {
\tvar ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
\tga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
\tvar s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
\t})();
\t</script>\n";
		}

		public function get_ga_ec_tracking_code($order)
		{
			$order_tax = $order->shipping_tax + $order->goods_tax;

			$company_name = Shop_CompanyInformation::get()->name;
			if (!strlen($company_name))
				$company_name = 'LemonStand';

			$company_name = Core_String::js_encode($company_name);

			$result = "\t_gaq.push(['_addTrans', \"{$order->id}\", \"{$company_name}\", \"{$order->total}\", \"{$order_tax}\", \"{$order->shipping_quote}\", \"\", \"\", \"\"]);\n";

			foreach ($order->items as $item)
			{
				$sku = Core_String::js_encode($item->product->sku);
				$name = Core_String::js_encode($item->product->name);
				$category = Core_String::js_encode($item->product->category_list[0]->name);
				$price = $item->eval_unit_total_price();

				$result .= "\t_gaq.push(['_addItem', \"{$order->id}\", \"{$sku}\", \"{$name}\", \"{$category}\", \"{$price}\", \"{$item->quantity}\"]);\n";
			}
			
			$result .= "\t_gaq.push(['_trackTrans']);";

			return $result;
		}

		/*
		 * Validation
		 */
		
		public function before_update($session_key = null)
		{
			if (!$this->ga_enabled)
			{
				$this->ga_password = $this->fetched['ga_password'];
				return;
			}
				
			if (!strlen($this->ga_siteid))
				$this->validation->setError('Please specify Site Id value', 'ga_siteid', true);
				
			if (!strlen($this->ga_property_id))
				$this->validation->setError('Please specify Web Property Id value', 'ga_property_id', true);
				
			if (!strlen($this->ga_username))
				$this->validation->setError('Please specify Google Analytics account Email address', 'ga_username', true);
				
			if (!strlen($this->ga_password) && !strlen($this->fetched['ga_password']))
				$this->validation->setError('Please specify Google Analytics password', 'ga_password', true);

			if (strlen($this->ga_password))
				$this->ga_password = base64_encode($this->ga_password);
			else
				$this->ga_password = $this->fetched['ga_password'];
				
			$ga = new Cms_GoogleAnalytics();

			$ga->username = $this->ga_username;
			$ga->password = base64_decode($this->ga_password);
			$ga->siteId = $this->ga_siteid;
			
			$ga->captcha_value = $this->captcha_value;
			$ga->captcha_token = $this->captcha_token;
			
			try
			{
				$ga->login();
			}
			catch (Exception $ex)
			{
				if ($ex instanceof Cms_GaCaptchaException)
					throw $ex;
				
				throw new Phpr_ApplicationException('Error logging into Google Analytics. '.$ex->getMessage());
			}
		}

		/*
		 * IP filters
		 */
		
		public function getIpFilters($filters = null)
		{
			$filters = explode(',', $filters === null ? $this->ip_filters : $filters);

			$result = array();
			foreach ($filters as $filter)
			{
				if (!strlen($filter))
					continue;

				$filter = explode('<>', $filter);
				$filter = (object)array('ip'=>$filter[0], 'name'=>$filter[1]);
				$result[] = $filter;
			}
				
			return $result;
		}
		
		public function addIpFilter($filters, $ip, $name)
		{
			$filters = $this->getIpFilters($filters);

			foreach ($filters as $filter)
			{
				if ($filter->ip == $ip)
					throw new Phpr_ApplicationException("Filter $ip already exists");
			}
			
			$filters[] = (object)array('ip'=>$ip, 'name'=>$name);
			$this->implodeFilters($filters);
		}
		
		public function deleteIpFilter($filters, $ip)
		{
			$filters = $this->getIpFilters($filters);

			$new_filters = array();
			foreach ($filters as $filter)
			{
				if ($filter->ip != $ip)
					$new_filters[] = $filter;
			}
			
			$this->implodeFilters($new_filters);
		}
		
		protected function implodeFilters($filters)
		{
			$result = array();
			foreach ($filters as $filter)
				$result[] = $filter->ip.'<>'.$filter->name;
			
			$this->ip_filters = implode(',', $result); 
		}
		
		public static function getLazy()
		{
			if (self::$obj)
				return self::$obj;
			else
			{
				$obj = new Cms_Stats_Settings(null, array('no_column_init'=>true, 'no_validation'=>true));
				self::$obj = $obj->find();
				if (!self::$obj)
					self::$obj = $obj;
				
				return self::$obj;
			}
		}
		
		public static function ipIsFiltered($ip)
		{
			$obj = self::getLazy();

			$filters = $obj->getIpFilters();
			foreach ($filters as $filter)
			{
				$filter_ip = str_replace('.', '\.', $filter->ip);
				$filter_ip = str_replace('*', '.*', $filter_ip);
				
				if (preg_match('/^'.$filter_ip.'$/', $ip))
					return true;
			}

			return false;
		}
	}

?>