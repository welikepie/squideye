<?php

	/**
	 * Base class for all form elements
	 */
	class Db_FormElement
	{
		public $tab;
		public $noPreview = false;
		public $noForm = false;
		public $sortOrder = null;
		public $collapsable = false;

		/**
		 * Specifies a caption of a tab to place the field into
		 * If you decide to use tabs, you should call this method for each form field in the model
		 */
		public function tab($tabCaption)
		{
			$this->tab = $tabCaption;
			return $this;
		}
		
		/**
		 *  Hides the element from form preview
		 */
		public function noPreview()
		{
			$this->noPreview = true;
			return $this;
		}

		/**
		 *  Hides the element from form
		 */
		public function noForm()
		{
			$this->noForm = true;
			return $this;
		}
		
		/**
		 * Sets the element position on the form. For elements without any position 
		 * specified, the position is calculated automatically, basing on the 
		 * add_form_field() method call order. For the first element the sort order
		 * value is 10, for the second element it is 20 and so on.
		 * @param int $value Specifies a form position.
		 */
		public function sortOrder($value)
		{
			$this->sortOrder = $value;
			return $this;
		}
		
		/**
		 * Places the element to the form or tab collapsable area
		 * @param boolean $value Determines whether the element should be placed to the collapsable area.
		 */
		public function collapsable($value = true)
		{
			$this->collapsable = $value;
			return $this;
		}
	}

?>