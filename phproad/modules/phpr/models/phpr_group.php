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
	 * PHP Road group base class.
	 *
	 * Use this class to manage the application user groups.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Group extends Db_ActiveRecord
	{
		protected $table_name = "groups";
		public $primary_key = 'Id';
		protected $has_and_belongs_to_many = array("users"=>array('class_name'=>'Phpr_User') );

		/**
		 * Group database identifier.
		 * @var int
		 */
		public $Id;

		/**
		 * Group name.
		 * @var string
		 */
		public $Name = '';
	}
?>