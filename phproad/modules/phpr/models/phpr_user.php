<?php

	/**
	 * PHP Road
	 *
	 * PHP application framework
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov, Andy Chentsov
	 * @since		Version 1.0
	 * @filesource
	 */

	/**
	 * PHP Road user base class.
	 *
	 * Use this class to manage the application user list.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_User extends Db_ActiveRecord
	{
		public $table_name = "users";
		public $primary_key = 'id';
		public $has_and_belongs_to_many = array("groups"=>array('class_name'=>'Phpr_Group') );

		private $AuthorizationCache = array();

		/**
		 * Finds a user by the login name and password.
		 * @param string $Login Specifies the user login name
		 * @param string $Password Specifies the user password
		 * @return Phpr_User Returns the user instance or null
		 */
		public function findUser( $Login, $Password )
		{
			return $this->where('login = lower(?)', $Login)->where('password = ?', md5($Password))->find();
		}

		/**
		 * Finds a user by the login name.
		 * @param string $Login Specifies the user login name
		 * @return Phpr_User Returns the user instance or null
		 */
		public function findUserByLogin( $Login )
		{
			return $this->where('login = lower(?)', $Login)->find();
		}

		/**
		 * Determines whether the user is allowed to have access to a specified resource.
		 * @param string $Module Specifies the name of a module that owns the resource ("blog").
		 *
		 * @param string $Resource Specifies the name of a recource ("post").
		 * You may omit this parameter to determine if user has access rights to any module resource.
		 *
		 * @param string $Object Specifies the resource object ("1").
		 * You may omit this parameter to determine if user has accssess rights to any object in context of specified module resource.
		 *
		 * @return mixed
		 */
		public function authorize( $Module, $Resource = null, $Object = null )
		{
			$CacheResource = $Resource === null ? '_NULL_' : $Resource;
			$CacheObject = $Object === null ? '_NULL_' : $Object;

			if ( isset($this->AuthorizationCache[$Module][$CacheResource][$CacheObject]) )
				return $this->AuthorizationCache[$Module][$CacheResource][$CacheObject];

			if ( $Object !== null )
				$Result = self::$db->fetchOne( self::$db->select()->from('rights', 'MAX(Value)')->joinInner('groups_users', 'groups_users.group_Id=rights.group_Id')->where('groups_users.user_Id=?', $this->Id)->where('rights.Module=?', $Module)->where('rights.Resource=?', $Resource)->where('rights.Object=?', $Object) );
			else
				if ( $Resource != null )
					$Result = self::$db->fetchOne( self::$db->select()->from('rights', 'MAX(Value)')->joinInner('groups_users', 'groups_users.group_Id=rights.group_Id')->where('groups_users.user_Id=?', $this->Id)->where('rights.Module=?', $Module)->where('rights.Resource=?', $Resource) );
				else
					$Result = self::$db->fetchOne( self::$db->select()->from('rights', 'MAX(Value) as RightValue')->joinInner('groups_users', 'groups_users.group_Id=rights.group_Id')->where('groups_users.user_Id=?', $this->Id)->where('rights.Module=?', $Module) );

			if ( !isset($this->AuthorizationCache[$Module]) )
				$this->AuthorizationCache[$Module] = array();

			if ( !isset($this->AuthorizationCache[$Module][$CacheResource]) )
				$this->AuthorizationCache[$Module][$CacheResource] = array();

			$this->AuthorizationCache[$Module][$CacheResource][$CacheObject] = $Result;

			return $Result;
		}

		/**
		 * Returns the user name in format first name last name
		 */
		public function getName()
		{
			return $this->firstName.' '.$this->lastName;
		}
	}
?>