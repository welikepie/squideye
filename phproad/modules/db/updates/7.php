<?

	$desc = Db_DbHelper::getTableStruct('core_versions');
	if (strpos($desc, 'version_str') === false) {
		Db_DbHelper::query('alter table core_versions add column version_str varchar(50)');
		Db_DbHelper::query('alter table core_update_history add column version_str varchar(50)');
	}

	Db_DbHelper::query("update core_versions set version_str=concat('1.0.', version) where version_str is null");
	Db_DbHelper::query("update core_update_history set version_str=concat('1.0.', version) where version_str is null");

?>