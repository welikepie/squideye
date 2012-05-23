<?php

	/**
	 * Backend UI tab description
	 * Do not create instances of this class directly. Instead use Backend_TabCollection::tab() method.
	 */
	class Backend_Tab
	{
		public $id;
		public $caption;
		public $url;
		public $position;
		public $moduleId;
		
		public $secondLevelTabs = array();

		/**
		 * Creates a tab description object
		 * @param string $id Specifies a tab identifier
		 * @param string $caption Specifies the tab caption
		 * @param string $url Specifies an URL corresponding the tab
		 * @param int $position Specifies the tab position in the tab bar
		 * @param string $moduleId Specified a module identifier
		 */
		public function __construct($id, $caption, $url, $position, $moduleId)
		{
			if (substr($url, 0, 1) == '/')
				$url = substr($url, 1);
			
			$this->id = $id;
			$this->caption = $caption;
			$this->url = $url;
			$this->position = $position;
			$this->moduleId = $moduleId;
		}
		
		/**
		 * Adds a second-level tab
		 * @param string $id Specifies a tab identifier
		 * @param string $caption Specifies the tab caption
		 * @param string $url Specifies an URL corresponding the tab
		 * @return Backend_Tab
		 */
		public function addSecondLevel($id, $caption, $url, $submenu = null)
		{
			if (substr($url, 0, 1) == '/')
				$url = substr($url, 1);

			$this->secondLevelTabs[$id] = array($caption, $url, $submenu);
			return $this;
		}
	}

?>