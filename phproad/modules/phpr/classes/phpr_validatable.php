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
	 * PHP Road Validatable Class
	 *
	 * Phpr_Validatable is a base class for classes that may perform data validation.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Validatable extends Phpr_Extensible
	{
		/**
		 * Executes a validation method.
		 * This method is used by the PHP Road internally.
		 * @param string $Method Specifies a method name.
		 * @param string $Name Specifies a name of the field
		 * @param string $Value Specifies a value to validate
		 * @return mixed
		 */
		public function _execValidation( $Method, $Name, $Value )
		{
			if ( !method_exists($this, $Method) )
				throw new Phpr_SystemException( "Unknown validation method: $Method" );

			return $this->$Method($Name, $Value);
		}
	}
?>