<?php

	/**
	 * Adds created_at, update_at, created_user_name, updated_user_name invisible columns to model
	 */
	class Db_AutoFootprints extends Phpr_Extension
	{
		protected $_model;
		protected $_loadedData =  array();
		
		public $auto_footprints_visible = false;
		public $auto_footprints_default_invisible = true;
		public $auto_footprints_created_at_name = 'Created At';
		public $auto_footprints_created_user_name = 'Created By';
		public $auto_footprints_updated_at_name = 'Updated At';
		public $auto_footprints_updated_user_name = 'Updated By';
		public $auto_footprints_date_format = '%x %H:%M';

		public $auto_footprints_user_not_found_name = '';
		
		public function __construct($model)
		{
			$this->_model = $model;
			$this->_model->addEvent('onDefineColumns', $this, 'autoFootprintsColumnsDefined');
		}
		
		public function autoFootprintsColumnsDefined()
		{
			if (Db_ActiveRecord::$execution_context == 'front-end')
				return;

			$this->_model->add_relation('belongs_to', 'updated_user', array('class_name'=>'Users_User'));
			
			$hasUpdateFields = $this->_model->column('updated_user_id');
			
			$modelTable = $this->_model->table_name;
			$nameString = "trim(concat(ifnull(firstName, ''), ' ', ifnull(lastName, ' '), ' ', ifnull(middleName, '')))";
			
			if ($hasUpdateFields)
				$this->_model->calculated_columns['updated_user_name'] = "select $nameString from users where users.id={$modelTable}.updated_user_id";
				
			$this->_model->calculated_columns['created_user_name'] = "trim(ifnull((select $nameString from users where users.id={$modelTable}.created_user_id), '{$this->_model->auto_footprints_user_not_found_name}'))";

			$field = $this->_model->define_column('created_at', $this->_model->auto_footprints_created_at_name)->dateFormat($this->_model->auto_footprints_date_format);
			if (!$this->_model->auto_footprints_visible)
				$field->invisible();
			if ($this->_model->auto_footprints_default_invisible)
				$field->defaultInvisible();

			$field = $this->_model->define_column('created_user_name', $this->_model->auto_footprints_created_user_name)->noLog()->type(db_varchar);
			if (!$this->_model->auto_footprints_visible)
				$field->invisible();
			if ($this->_model->auto_footprints_default_invisible)
				$field->defaultInvisible();

			if ($hasUpdateFields)
			{
				$field = $this->_model->define_column('updated_at', $this->_model->auto_footprints_updated_at_name)->dateFormat($this->_model->auto_footprints_date_format);
				if (!$this->_model->auto_footprints_visible)
					$field->invisible();
				if ($this->_model->auto_footprints_default_invisible)
					$field->defaultInvisible();
			}
			
			if ($hasUpdateFields)
			{
				$field = $this->_model->define_column('updated_user_name', $this->_model->auto_footprints_updated_user_name)->noLog();
				if (!$this->_model->auto_footprints_visible)
					$field->invisible();
				if ($this->_model->auto_footprints_default_invisible)
					$field->defaultInvisible();
			}
		}
	}
	
?>