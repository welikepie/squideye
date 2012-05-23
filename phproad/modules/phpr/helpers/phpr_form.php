<?

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
	 * PHP Road Form helper
	 *
	 * This class contains functions for working with HTML forms.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */
	class Phpr_Form
	{
		/**
		 * Returns the opening form tag.
		 * @param array $attributes Optional list of the opening tag attributes.
		 * @return string
		 */
		public static function open_tag($attributes = array()) {
			$DefUrl = h(rawurldecode(strip_tags(root_url(Phpr::$request->GetCurrentUri()))));
			
			if (($pos = mb_strpos($DefUrl, '|')) !== false)
				$DefUrl = mb_substr($DefUrl, 0, $pos);

			$result = "<form ";
			$result .= Phpr_Html::formatAttributes( $attributes, array("action"=>$DefUrl, "method"=>"post", "id"=>"FormElement", "onsubmit"=>"return false;") );
			$result .= ">\n";

			return $result;
		}
		 
		/**
		 * @deprecated
		 */
		public static function openTag($attributes = array()) {
			return self::open_tag($attributes);
		}
		
		/**
		 * Returns the closing form tag.
		 * @return string
		 */
		public static function close_tag() {
			$result = "</form>";
			
			return $result;
		}

		/**
		 * Returns the checked="checked" string if the $Value is true.
		 * Use this helper to set a checkbox state.
		 * @param boolean $Value Specifies the checbox state value
		 * @return string
		 */
		public static function checkboxState( $Value )
		{
			return $Value ? "checked=\"checked\"" : "";
		}

		/**
		 * Returns the checked="checked" string if the $Value1 equals $Value2
		 * Use this helper to set a radiobutton state.
		 * @param boolean $Value1 Specifies the first value
		 * @param boolean $Value2 Specifies the second value
		 * @return string
		 */
		public static function radioState( $Value1, $Value2 )
		{
			return $Value1 == $Value2 ? "checked=\"checked\"" : "";
		}

		/**
		 * Returns the selected="selected" string if the $SelectedState = $CurrentState
		 * Use this helper to set a select option state.
		 * @param boolean $SelectedState Specifies the select value that is currently selected
		 * @param boolean $CurrentState Specifies the current option value
		 * @return string
		 */
		public static function optionState( $SelectedState, $CurrentState )
		{
			return $SelectedState == $CurrentState ? 'selected="selected"' : null;
		}
	}
