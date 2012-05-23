<?php

	/**
	 * Generic for all class extensions
	 */
	class Phpr_Extension
	{
		protected $_hiddenExtensionMethods = array('extMethodIsHidden', 'extFieldIsHidden');
		protected $_hiddenExtensionFields = array();
		
		/**
		 * Hides a method from merging with an extendable class 
		 */
		protected function extHideMethod($methodName)
		{
			$this->_hiddenExtensionMethods[] = $methodName;
		}
		
		public function extMethodIsHidden($methodName)
		{
			return in_array($methodName, $this->_hiddenExtensionMethods);
		}

		/**
		 * Hides a field from merging with an extendable class 
		 */
		protected function extHideField($fieldName)
		{
			$this->_hiddenExtensionFields[] = $fieldName;
		}
		
		public function extFieldIsHidden($fieldName)
		{
			return in_array($fieldName, $this->_hiddenExtensionFields);
		}
	}

?>