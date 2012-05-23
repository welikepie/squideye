<?php

	class Core_Email
	{
		/**
		 * Sends email message
		 * @param string $moduleId Specifies an identifier of a module the email view belongs to
		 * @param string $view Specifies a name of the email view file
		 * @param mixed $viewData Message-specific information to be passed into a email view
		 * @param string $subject Specifies a message subject
		 * @param string $recipientName Specifies a name of a recipient
		 * @param string $recipientEmail Specifies an email address of a recipient
		 * @param mixed $attachments A list of file attachemnts in format path=>name
		 */
		public static function send( $moduleId, $view, &$viewData, $subject, $recipientName, $recipientEmail, $recipients = array(), $settingsObj = null, $replyTo = array(), $attachments = array() )
		{
			if (!$settingsObj)
				$settings = System_EmailParams::get();
			else
				$settings = $settingsObj;

			if (!$settings)
				throw new Phpr_SystemException( "Email system is not configured." );

			if (!$settings->isConfigured())
				throw new Phpr_SystemException( "Email system is not configured." );

			/**
			 * Load the view contents
			 */
			$Wrapper = new Core_EmailViewWrapper( $moduleId, $view, $viewData );

			/*
			 * Send the message
			 */
			require_once PATH_APP."/modules/core/thirdpart/class.phpmailer.php";

			$Mail = new PHPMailer();

			$Mail->Encoding = "8bit";
			$Mail->CharSet = "utf-8";
			$Mail->From = $settings->sender_email;
			$Mail->FromName = $settings->sender_name;
			$Mail->Sender = $settings->sender_email;
			$Mail->Subject = $subject;
			$Mail->WordWrap = 0;

			if ($replyTo)
			{
				foreach ($replyTo as $address=>$name)
					$Mail->AddReplyTo($address, $name);
			}

			$settings->configure_mailer($Mail);

			$Mail->IsHTML(true);

			$Wrapper->ViewData['RecipientName'] = $recipientName;
			$HtmlBody = $Wrapper->execute();
			
			/* 
			 * Apply common email variables
			 */
			
			foreach ($attachments as $file_path=>$file_name)
				$Mail->AddAttachment($file_path, $file_name);

			/*
			 * Format the message and send
			 */

			$Mail->ClearAddresses();

			$external_recipients = array();
			if ( !count($recipients) )
			{
				$Mail->AddAddress($recipientEmail, $recipientName);
				$external_recipients[$recipientEmail] = $recipientName;
			}

			foreach ( $recipients as $Recipient=>$Email )
			{
				if (!is_object($Email))
				{
					$Mail->AddAddress($Email, $Recipient);
					$external_recipients[$Email] = $Recipient;
				}
				elseif ($Email instanceof Phpr_User)
				{
					$Mail->AddAddress($Email->email, $Email->name);
					$external_recipients[$Email->email] = $Email->name;
				}
			}
			
			$HtmlBody = str_replace('{recipient_email}', implode(', ', array_keys($external_recipients)), $HtmlBody);
			
			$TextBody = trim(strip_tags( preg_replace('|\<style\s*[^\>]*\>[^\<]*\</style\>|m', '', $HtmlBody) ));
			
			$Mail->Body = $HtmlBody;
			$Mail->AltBody = $TextBody;
			
			$custom_data = array_key_exists('custom_data', $viewData) ? $viewData['custom_data'] : null;
			
			$external_sender_params = array(
				'content'=>$HtmlBody,
				'reply_to'=>$replyTo,
				'attachments'=>$attachments,
				'recipients'=>$external_recipients,
				'from'=>$settings->sender_email,
				'from_name'=>$settings->sender_name,
				'sender'=>$settings->sender_email,
				'subject'=>$subject,
				'data'=>$custom_data
			);

			$external_sender_params = (object)$external_sender_params;
			$send_result = Backend::$events->fireEvent('core:onSendEmail', $external_sender_params);
			foreach ($send_result as $result)
			{
				if ($result)
					return;
			}

			if ( !$Mail->Send() )
				throw new Phpr_SystemException( 'Error sending message '.$subject.': '.$Mail->ErrorInfo );
		}
		
		public static function sendOne($moduleId, $view, &$viewData, $subject, $userId)
		{
			$result = false;
			
			try
			{
				$user = is_object($userId) ? $userId : Users_User::create()->find($userId);
				if (!$user)
					return;
				
				self::send($moduleId, $view, $viewData, $subject, $user->short_name, $user->email);
				return true;
			}
			catch (Exception $ex)
			{
			}
			
			return false;
		}
		
		public static function sendToList($moduleId, $view, &$viewData, $subject, $recipients, $throw = false, $replyTo = array())
		{
			try
			{
				if (is_array($recipients) && !count($recipients))
					return;
					
				if (is_object($recipients) && !$recipients->count)
					return;
				
				self::send($moduleId, $view, $viewData, $subject, null, null, $recipients, null, $replyTo);
			}
			catch (Exception $ex)
			{
				if ($throw)
					throw $ex;
			}
		}
	}

?>