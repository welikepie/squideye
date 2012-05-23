<?php

	class Db_FormSection extends Db_FormElement
	{
		public $title;
		public $description;
		
		public function __construct($title, $description)
		{
			$this->title = $title;
			$this->description = $description;
		}
	}

?>