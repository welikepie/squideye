<?php

	class Db_FormPartial extends Db_FormElement
	{
		public $path;
		
		public function __construct($path)
		{
			$this->path = $path;
		}
	}

?>