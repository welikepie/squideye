<?php

	class Db_UserParameters
	{
		private static $_parameterCache = null;

		private static function initCache()
		{
			if ( self::$_parameterCache !== null )
				return;

			self::$_parameterCache = array();

			$records = Db_DbHelper::queryArray('select * from userparams');
			foreach( $records as $param )
			{
				$UserId = $param['user_id'];
				$Name = $param['name'];
				if ( !isset(self::$_parameterCache[$UserId]) )
					self::$_parameterCache[$UserId] = array();

				self::$_parameterCache[$UserId][$Name] = $param['value'];
			}
		}

		public static function set( $Name, $Value, $UserId = null )
		{
			if (Phpr::$config->get('USER_PARAMS_USE_SESSION'))
			{
				$params = Phpr::$session->get('phpr_user_params', array());
				$params[$Name] = $Value;
				Phpr::$session->set('phpr_user_params', $params);
				return;
			}

			self::initCache();

			if ( $UserId === null )
				$UserId = Phpr::$security->getUser()->id;

			$Value = serialize($Value);

			self::$_parameterCache[$UserId][$Name] = $Value;
			$bind = array('user_id'=>$UserId, 'name'=>$Name, 'value'=>$Value);
			
			Db_DbHelper::query('delete from userparams where user_id=:user_id and name=:name', $bind);
			Db_DbHelper::query('insert into userparams(user_id, name, value) values (:user_id,:name,:value)', $bind);
		}

		public static function get( $Name, $UserId = null, $Default = null, $ForceDb = false )
		{
			if (Phpr::$config->get('USER_PARAMS_USE_SESSION') && !$ForceDb)
			{
				$params = Phpr::$session->get('phpr_user_params', array());
				return array_key_exists($Name, $params) ? $params[$Name] : self::get($Name, $UserId, $Default, true);
			}

			self::initCache();

			if ( $UserId === null )
			{
				$user = Phpr::$security->getUser();
				if (!$user)
					return $Default;

				$UserId = $user->id;
			}

			if ( !isset(self::$_parameterCache[$UserId]) )
				return $Default;

			if ( !isset(self::$_parameterCache[$UserId][$Name]) )
				return $Default;

			return unserialize(self::$_parameterCache[$UserId][$Name]);
		}
	}

?>