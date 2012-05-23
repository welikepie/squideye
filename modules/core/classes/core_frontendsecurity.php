<?php

	/**
	 * Core security class.
	 * This class extends the standard PHP Road Security class.
	 */
	class Core_FrontEndSecurity extends Phpr_Security
	{
		public $userClassName = "Shop_Customer";
		public $cookieName = "eCommerce";
		protected $cookieLifetimeVar = 'FRONTEND_AUTH_COOKIE_LIFETIME';

		protected static $UserCache = array();
		protected $cookie_updated = false;
		
		/**
		 * Validates user login name and password and logs user in.
		 *
		 * @param string $Login Specifies the user login name.
		 * If you omit this parameter the 'Login' POST variable will be used.
		 *
		 * @param string $Password Specifies the user password
		 * If you omit this parameter the 'Password' POST variable will be used.
		 *
		 * @param string $Redirect Optional URL to redirect the user browser in case of successful login.
		 * @param Phpr_Validation $Validation Optional validation object to report errors.
		 *
		 * @return boolean
		 */
		public function login(Phpr_Validation $Validation = null, $Redirect = null, $Login = null, $Password = null)
		{
			return parent::login($Validation, $Redirect, $Login, $Password);
		}

		public function getUser()
		{
			if ( $this->user !== null )
				return $this->user;

			/*
			 * Determine whether the authentication cookie is available
			 */

			$CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
			$Ticket = Phpr::$request->cookie( $CookieName );

			if ( $Ticket === null )
			{
				/*
				 * Check whether the front-end ticket was passed as a GET parameter
				 */
				$frontend_ticket_param = Phpr::$config->get('TICKET_PARAM_NAME', 'ls_frontend_ticket');
				$Ticket = $this->restoreTicket(Phpr::$request->getField($frontend_ticket_param));
			}
			
			if (!$Ticket)
				return null;

			/*
			 * Validate the ticket
			 */
			$Ticket = $this->validateTicket( $Ticket );
			if ( $Ticket === null )
				return null;

			/*
			 * Return the ticket user
			 */
			$UserId = trim(base64_decode($Ticket['user']));
			if ( !strlen($UserId) )
				return null;
			
			return $this->findUser($UserId);
		}

		public function authorize_user()
		{
			if (!$this->check_session_host())
				return null;

			$user = $this->getUser();

			if (!$user)
				return null;

			if (!$this->cookie_updated)
			{
				$this->updateCookie( $user->id );
				$this->cookie_updated = true;
			}

			return $user;
		}

		protected function updateCookie($Id)
		{
			/*
			 * Prepare the authentication ticket
			 */
			$Ticket = $this->getTicket( $Id );

			/*
			 * Set a cookie
			 */
			$CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
			$CookieLifetime = Phpr::$config->get($this->cookieLifetimeVar, $this->cookieLifetime);

			$CookiePath = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
			$CookieDomain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

			Phpr::$response->setCookie( $CookieName, $Ticket, $CookieLifetime, $CookiePath, $CookieDomain );
		}
		
		public function customerLogin($CustomerId)
		{
			$this->updateCookie($CustomerId);
			Backend::$events->fireEvent('onFrontEndLogin');
		}

		public function logout($Redirect = null)
		{
			$CookieName = Phpr::$config->get('FRONTEND_AUTH_COOKIE_NAME', $this->cookieName);
			$CookiePath = Phpr::$config->get('FRONTEND_AUTH_COOKIE_PATH', $this->cookiePath);
			$CookieDomain = Phpr::$config->get('FRONTEND_AUTH_COOKIE_DOMAIN', $this->cookieDomain);

			Phpr::$response->deleteCookie( $CookieName, $CookiePath, $CookieDomain );

			$this->user = null;

			Phpr::$session->destroy();

			if ( $Redirect !== null )
				Phpr::$response->redirect( $Redirect );
		}

		protected function beforeLoginSessionDestroy($user)
		{
			Backend::$events->fireEvent('onFrontEndLogin');
		}
		
		protected function keepSessionData()
		{
			return strlen(Shop_CheckoutData::get_coupon_code());
		}

		public function findUser($UserId)
		{
			if (isset(self::$UserCache[$UserId]))
				return self::$UserCache[$UserId];
			
			return self::$UserCache[$UserId] = Shop_Customer::create()->where('deleted_at is null')->where('shop_customers.id=?', $UserId)->find();
		}
	}
	
?>