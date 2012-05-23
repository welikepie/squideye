<?

	class Phpr_Strings
	{
		/**
		 * Returns a single or plural form of a word
		 * @param int $n Specifies a number
		 * @param string $word Specifies a word
		 * @param bool $add_number Determines whether the number should be added to the result before the word
		 * @return Returns string
		 */
		public static function word_form($n, $word, $add_number = false)
		{
			if ($n < 1 || $n > 1)
				return $add_number ? $n.' '.Phpr_Inflector::pluralize($word) : Phpr_Inflector::pluralize($word);

			return $add_number ? $n.' '.$word : $word;
		}
	}

?>