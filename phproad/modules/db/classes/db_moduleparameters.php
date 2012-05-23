<?php

	class Db_ModuleParameters
	{
		private static $_parameterCache = null;

		private static function initCache()
		{
			if ( self::$_parameterCache !== null )
				return;

			self::$_parameterCache = array();

			$records = Db_DbHelper::queryArray('select * from moduleparams');
			foreach( $records as $param )
			{
				$ModuleId = $param['module_id'];
				$Name = $param['name'];
				if ( !isset(self::$_parameterCache[$ModuleId]) )
					self::$_parameterCache[$ModuleId] = array();

				self::$_parameterCache[$ModuleId][$Name] = $param['value'];
			}
		}

		public static function set( $ModuleId, $Name, $Value )
		{
			self::initCache();
			
			$Value = serialize($Value);

			self::$_parameterCache[$ModuleId][$Name] = $Value;
			$bind = array('module_id'=>$ModuleId, 'name'=>$Name, 'value'=>$Value);
			
			Db_DbHelper::query('delete from moduleparams where module_id=:module_id and name=:name', $bind);
			Db_DbHelper::query('insert into moduleparams(module_id, name, value) values (:module_id,:name,:value)', $bind);
		}

		public static function get( $ModuleId, $Name, $Default = null )
		{
			self::initCache();

			if ( !isset(self::$_parameterCache[$ModuleId]) )
				return $Default;

			if ( !isset(self::$_parameterCache[$ModuleId][$Name]) )
				return $Default;

			try
			{
				return @unserialize(self::$_parameterCache[$ModuleId][$Name]);
			}
			catch (Exception $ex)
			{
				return $Default;
			}
		}
	}

?>