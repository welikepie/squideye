<?php
	class System_LoginLogRecord extends Db_ActiveRecord 
	{
		public $table_name = 'system_login_log';
		public $belongs_to = array('user'=>array(
			'class_name'=>'Users_User', 'foreign_key'=>'user_id'
		));

		public static function create($values = null) 
		{
			return new self($values);
		}

		public static function create_record($user)
		{
			Db_DbHelper::query('delete from system_login_log where datediff(NOW(), created_at) > 30');
			
			$obj = self::create();
			$obj->user_id = $user ? $user->id : null;
			$obj->ip = Phpr::$request->getUserIp();
			$obj->save();
		}

		public function define_columns($context = null)
		{
			$this->define_column('created_at', 'Date and Time')->dateFormat('%x %X');
			$this->define_relation_column('user_name', 'user', 'User', db_varchar, Users_User::shortNameExpr);
			$this->define_column('ip', 'IP Address');
			$this->define_relation_column('firstName', 'user', 'First Name', db_varchar, '@firstName');
			$this->define_relation_column('lastName', 'user', 'Last Name', db_varchar, '@lastName');
			$this->define_relation_column('login', 'user', 'Login', db_varchar, '@login');
			$this->define_relation_column('email', 'user', 'Email', db_varchar, '@email');
		}
	}
?>