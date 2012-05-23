<?

	class Cms_PageNavigationNode
	{
		public $id;
		public $title;
		public $url;
		public $parent_id;
		public $parent_key_index;
		public $navigation_visible;
		public $is_published;
		public $navigation_label;
		public $visible_for_group;
		
		public function __construct($db_record)
		{
			$this->title = $db_record->title;
			$this->id = $db_record->id;
			$this->url = $db_record->url;
			$this->parent_id = $db_record->parent_id;
			$this->navigation_visible = $db_record->navigation_visible;
			$this->navigation_label = $db_record->navigation_label;
			$this->visible_for_group = $db_record->visible_for_group;
			$this->is_published = $db_record->is_published;
		}
		
		/**
		 * Returns the navigation menu label, specified on the Navigation tab of the page edit form.
		 * If the navigation menu label was not specified for this page, the function will return the page title.
		 * @return string
		 */
		public function navigation_label()
		{
			if (strlen($this->navigation_label))
				return $this->navigation_label;
				
			return $this->title;
		}
		
		/**
		 * Returns a list of subpages grouped under this page.
		 * @return array Returns an array of the Cms_PageNavigationNode objects
		 */
		public function navigation_subpages()
		{
			if (array_key_exists($this->id, Cms_Page::$navigation_parent_cache))
				return Cms_Page::$navigation_parent_cache[$this->id];

			return array();
		}
		
		public function is_current() {
			return $this->url == Phpr::$request->getCurrentUri();
		}
	}