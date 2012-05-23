<?php

	class System_EmailTemplate extends Db_ActiveRecord 
	{
		public $table_name = 'system_email_templates';
		public $reply_to_mode = 'default';
		
		public static function create($values = null) 
		{
			return new self($values);
		}
		
		public function define_columns($context = null)
		{
			$this->define_column('code', 'Code')->validation()->fn('trim')->required('Please specify the template code.')->unique('Code %s is already in use. Please specify another code.')->regexp('/^[a-z_0-9:]*$/i', 'Template code can only contain latin characters, numbers, colons and underscores.');
			$this->define_column('subject', 'Subject')->validation()->fn('trim')->required('Please specify the message subject.');
			$this->define_column('description', 'Description')->validation()->fn('trim')->required('Please provide the template description.');
			$this->define_column('content', 'Content')->invisible()->validation()->required('Please fill out the template content.');
			$this->define_column('is_system', 'Is System');

			$this->define_column('reply_to_mode', 'Reply-To Address')->invisible();
			$this->define_column('reply_to_address', 'Reply To Address value')->invisible()->validation()->email(true, 'Please specify a valid email address');
		}

		public function define_form_fields($context = null)
		{
			if ($context == 'create')
			{
				$this->add_form_field('code', 'left')->comment('Template code is used by modules to refer templates', 'above')->tab('Message');
				$this->add_form_field('subject', 'right')->comment('Email message subject', 'above')->tab('Message');
				$this->add_form_field('description')->size('tiny')->tab('Message');
			} else
			{
				$this->add_form_field('subject')->tab('Message');
				$this->add_form_field('description')->size('tiny')->tab('Message');
			}
				
			$editor_config = System_HtmlEditorConfig::get('system', 'system_email_template');
			$field = $this->add_form_field('content')->renderAs(frm_html)->size('huge')->tab('Message')->saveCallback('save_template');
			$field->htmlPlugins .= ',save';
			$editor_config->apply_to_form_field($field);
			$field->htmlFullWidth = true;
			
			$this->add_form_field('reply_to_mode')->tab('Email Settings')->renderAs(frm_radio)->comment('Please choose which reply-to email address should be used in email messages based on this template.', 'above');
			$this->add_form_field('reply_to_address')->tab('Email Settings')->cssClassName('checkbox_align')->cssClasses('form400')->noLabel();
		}
		
		public function get_reply_to_mode_options($key_value = -1)
		{
			$params = System_EmailParams::get();
			
			$result = array('default'=>'System default ('.$params->sender_email.')');
			$result['customer'] = 'Customer email address (if applicable)';
			$result['sender'] = 'Sender user email address (if applicable)';
			$result['custom'] = 'Specific email address';
			
			return $result;
		}
		
		public function before_delete($id=null)
		{
			if ($this->is_system)
				throw new Phpr_ApplicationException('System email templates cannot be deleted.');
			
			Backend::$events->fireEvent('onDeleteEmailTemplate', $this);
		}
		
		public function send_test_message()
		{
			$content = $this->content;
			$variables = Core_ModuleManager::listEmailVariables(null, true);
			
			$subject = $this->subject;
			foreach ($variables as $section=>$variables)
			{
				foreach ($variables as $variable=>$info)
				{
					$content = str_replace('{'.$variable.'}', $info[1], $content);
					$subject = str_replace('{'.$variable.'}', $info[1], $subject);
				}
			}

			$user = Phpr::$security->getUser();
			$this->subject = $subject;
			
			if ($this->is_system)
				$this->send_to_team(array($user->name=>$user->email), $content, $user->email, $user->name, 'John Smith', 'john@example.com');
			else
				$this->send($user->email, $content, $user->name, $user->email, $user->name, 'John Smith', 'john@example.com');
		}
		
		public function get_reply_address($sender_email, $sender_name, $customer_email, $customer_name)
		{
			$result = array();

			switch ($this->reply_to_mode)
			{
				case 'customer' :
					if (strlen($customer_email))
						$result[$customer_email] = strlen($customer_name) ? $customer_name : $customer_email;
				break;
				case 'sender' :
					if (strlen($sender_email))
						$result[$sender_email] = strlen($sender_name) ? $sender_name : $sender_email;
				break;
				case 'custom' :
					if (strlen($this->reply_to_address))
						$result[$this->reply_to_address] = $this->reply_to_address;
				break;
			}
			
			$params = System_EmailParams::get();

			if (!$result)
				$result[$params->sender_email] = strlen($params->sender_name) ? $params->sender_name : $params->sender_email;
				
			return $result;
		}
		
		/**
		 * Sends email message to a specified customer
		 * @param Shop_Customer $customer Specifies a customer to send a message to
		 * @param string $message_text Specifies a message text
		 */
		public function send_to_customer($customer, $message_text, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null, $custom_data = null)
		{
			try
			{
				$template = System_EmailLayout::find_by_code('external');
				$message_text = $template->format($message_text);

				$viewData = array('content'=>$message_text, 'custom_data'=>$custom_data);
				$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);

				Core_Email::send('system', 'email_message', $viewData, $this->subject, $customer->name, $customer->email, array(), null, $reply_to);
			}
			catch (exception $ex)
			{
			}
		}
		
		/**
		 * Sends email message to a a list of the store team members
		 * @param mixed $users Specifies a list of users to send the message to
		 * @param string $message_text Specifies a message text
		 */
		public function send_to_team($users, $message_text, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null, $throw_exceptions = false)
		{
			$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);

			try
			{
				$template = System_EmailLayout::find_by_code('system');
				$message_text = $template->format($message_text);

				$viewData = array('content'=>$message_text);
				Core_Email::sendToList('system', 'email_message', $viewData, $this->subject, $users, $throw_exceptions, $reply_to);
			}
			catch (exception $ex)
			{
				if ($throw_exceptions)
					throw $ex;
			}
		}
		
		/**
		 * Sends email message to a specified email address
		 * @param string $email Specifies an email address to send the message to
		 * @param string $message_text Specifies a message text
		 * @param string $name Specifies a recipient name
		 */
		public function send($email, $message_text, $name = null, $sender_email = null, $sender_name = null, $customer_email = null, $customer_name = null)
		{
			if (!$name)
				$name = $email;
				
			$template = System_EmailLayout::find_by_code('external');
			$message_text = $template->format($message_text);

			$viewData = array('content'=>$message_text);
			$reply_to = $this->get_reply_address($sender_email, $sender_name, $customer_email, $customer_name);
			
			Core_Email::send('system', 'email_message', $viewData, $this->subject, $name, $email, array(), null, $reply_to);
		}
	}
	
?>