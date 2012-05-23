<?php
	class System_EmailParams extends Db_ActiveRecord 
	{
		public $table_name = 'system_mail_settings';
		public static $loadedInstance = null;
		public $encrypted_columns = array('smtp_password', 'smtp_user');
		
		const mode_smtp = 'smtp';
		const mode_sendmail = 'sendmail';
		const mode_mail = 'mail';
		
		public $sender_name = 'LemonStand';

		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public static function get()
		{
			if (self::$loadedInstance)
				return self::$loadedInstance;
			
			return self::$loadedInstance = self::create()->order('id desc')->find();
		}

		public static function isConfigured()
		{
			$obj = self::get();
			if (!$obj)
				return false;

			return $obj->send_mode != self::mode_smtp || (self::mode_smtp && strlen($obj->smtp_address));
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('sender_name', 'Sender Name')->validation()->fn('trim')->required('Please specify sender name.');
			$this->define_column('sender_email', 'Sender Email')->validation()->fn('trim')->required('Please specify sender email address.')->email();

			$this->define_column('send_mode', 'Email Method');
			$this->define_column('smtp_address', 'SMTP Address')->validation()->fn('trim')->method('validate_smtp_address');
			
			$this->define_column('templating_engine', 'Templating engine')->validation()->method('validate_templating_engine');
			
			$this->define_column('smtp_authorization', 'SMTP Authorization Required');
			$this->define_column('smtp_user', 'User')->validation()->fn('trim')->method('validate_authorization');
			$this->define_column('smtp_password', 'Password')->validation()->fn('trim');

			$this->define_column('smtp_port', 'SMTP Port')->validation()->fn('trim');
			$this->define_column('smtp_ssl', 'SSL connection required');
			
			$this->define_column('sendmail_path', 'Sendmail path')->validation()->fn('trim')->method('validate_sendmail');
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('send_mode')->tab('General Parameters')->renderAs(frm_dropdown);

			$this->add_form_field('sender_name', 'left')->tab('General Parameters');
			$this->add_form_field('sender_email', 'right')->tab('General Parameters');
			$this->add_form_field('templating_engine')->comment('Please specify a templating engine to use for parsing compound email variables and email layouts.', 'above')->tab('General Parameters')->renderAs(frm_dropdown);

			$this->add_form_field('smtp_address')->tab('SMTP');
			$this->add_form_field('smtp_authorization')->tab('SMTP')->comment('Use this checkbox if your SMTP server requires authorization.');
			$this->add_form_field('smtp_user', 'left')->tab('SMTP')->comment('SMTP user name', 'above')->renderAs(frm_text);
			$this->add_form_field('smtp_password', 'right')->tab('SMTP')->renderAs(frm_password)->comment('SMTP user password', 'above');

			$this->add_form_field('smtp_port')->tab('SMTP');
			$this->add_form_field('smtp_ssl')->tab('SMTP');

			$this->add_form_field('sendmail_path')->tab('Sendmail')->comment('Please specify the path of the sendmail program. Leave this field empty to use the default path: /usr/sbin/sendmail', 'above');
			
			$this->form_tab_id('SMTP', 'tab_smtp');
			$this->form_tab_id('Sendmail', 'tab_sendmail');
			$this->form_tab_visibility('SMTP', $this->send_mode == self::mode_smtp);
			$this->form_tab_visibility('Sendmail', $this->send_mode == self::mode_sendmail);
		}
		
		public function get_send_mode_options($key_index = -1)
		{
			return array(
				self::mode_smtp=>'SMTP',
				self::mode_sendmail=>'Sendmail',
				self::mode_mail=>'PHP mail'
			);
		}
		
		public function before_save($deferred_session_key = null)
		{
			if (!strlen($this->smtp_password))
				$this->smtp_password = $this->fetched['smtp_password'];
			else
				$this->smtp_password = base64_encode($this->smtp_password);
		}

		public function validate_authorization($name, $value)
		{
			if (!$this->smtp_authorization || $this->send_mode != self::mode_smtp)
				return true;

			if (strlen($value))
				return true;
				
			$this->validation->setError("Please specify SMTP user name and password.", $name, true);
			return true;
		}
		
		public function validate_sendmail($name, $value)
		{
			if ($this->send_mode != self::mode_sendmail)
				return true;
				
			$path = $this->fix_sendmail_path($value);
			if (!file_exists($path))
				$this->validation->setError("The sendmail program is not found at the specified path: ".$path, $name, true);

			return true;
		}

		protected function fix_sendmail_path($value)
		{
			if (!strlen($value))
				$value = '/usr/sbin/sendmail';

			if (substr($value, -1) == '/')
				$value = substr($value, 0, -1);

			if (substr($value, -9) != '/sendmail')
				$value .= '/sendmail';

			return $value;
		}

		public function validate_smtp_address($name, $value)
		{
			if ($this->send_mode != self::mode_smtp)
				return true;

			if (!strlen($value))
				$this->validation->setError("Please specify SMTP server address.", $name, true);

			return true;
		}
		
		public function validate_templating_engine($name, $value)
		{
			if ($value == 'php' && !Core_Configuration::is_php_allowed())
				$this->validation->setError('The application configuration doesn\'t allow PHP in compound email variables and layouts.', $name, true);
			
			return true;
		}
		
		public function get_templating_engine_options($key_value = -1)
		{
			$engines = array(
				'php'=>'PHP',
				'twig'=>'Twig'
			);
			
			if (!Cms_Controller::is_php_allowed())
				$engines['php'] .= ' (not allowed)';
				
			return $engines;
		}
		
		public function configure_mailer($mailer)
		{
			switch ($this->send_mode)
			{
				case self::mode_smtp :
					$mailer->Port = strlen($this->smtp_port) ? $this->smtp_port : 25;
					if ($this->smtp_ssl)
						$mailer->SMTPSecure= 'ssl';

					$mailer->Host = $this->smtp_address;
					if ( $this->smtp_authorization )
					{
						$mailer->SMTPAuth = true;
						$mailer->Username = $this->smtp_user;
						$mailer->Password = base64_decode($this->smtp_password);
					} else
						$mailer->SMTPAuth = false;

					$mailer->IsSMTP();
				break;
				case self::mode_sendmail :
					$mailer->IsSendmail();
					$mailer->Sendmail = $this->fix_sendmail_path($this->sendmail_path);
				break;
				case self::mode_sendmail :
					$mailer->IsMail();
				break;
			}
		}
		
		public static function get_templating_engine()
		{
			$obj = self::get();
			$engine = $obj->templating_engine;
			if (!strlen($engine))
				$engine = 'php';
				
			if ($engine == 'php' && !Core_Configuration::is_php_allowed())
				throw new Phpr_SystemException('The application configuration doesn\'t allow PHP in compound email variables and layouts.');
				
			return $engine;
		}
	}
?>