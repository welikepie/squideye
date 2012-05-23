<?php

	class Backend_Html
	{
		/*
		 * Returns a backend-relative URL
		 */
		public static function url($url)
		{
			$url = Core_String::normalizeUri($url);
			$backendUrl = Core_String::normalizeUri(Phpr::$config->get('BACKEND_URL', 'backend'));

			return root_url($backendUrl.$url);
		}

		/**
		 * Outputs a menu button
		 */
		public static function menuButton($caption, $items, $attributes = array())
		{
			if (array_key_exists('class', $attributes))
				$attributes['class'] = 'button menu '.$attributes['class'];
			
			$attrList = Phpr_Html::formatAttributes($attributes, array('class'=>'button menu'));
			
			$controlId = 'menu'.uniqid();

			$result = null;
			$result .= '<div id="'.$controlId.'" class="button menu">';
			$result .= '<a class="trigger" href="#">'.h($caption).'</a>';
			$result .= '<div class="wrapper">';
			$result .= '<ul>';

			$lastIndex = count($items)-1;

			$index = 0;
			foreach ($items as $caption=>$attributes)
			{
				if (!is_array($attributes))
					$attributes = array('href'=>$attributes);

				$attrStr = Phpr_Html::formatAttributes($attributes);
				$result .= '<li><a '.$attrStr.'>'.h($caption).'</a>';
				if ($lastIndex == $index)
					$result .= '<div class="rb"></div><div class="lb"></div>';
				$result .= '</li>';
				$index ++;
			}

			$result .= '</ul></div><div class="lt"></div><div class="rt"></div></div>';
			
			$result .= '<script type="text/javascript">
				new ButtonMenu("'.$controlId.'");
			</script>';
			
			return $result;
		}
		
		/**
		 * Outputs a control panel menu button
		 */
		public static function ctr_menuButton($caption, $button_class, $items, $attributes = array())
		{
			$attrList = Phpr_Html::formatAttributes($attributes, array('class'=>'menu imageLink '.$button_class));
			
			$controlId = 'menu'.uniqid();

			$result = null;
			$result .= '<div id="'.$controlId.'" '.$attrList.'>';
			$result .= '<a class="trigger" href="#">'.h($caption).'</a>';
			$result .= '<div class="wrapper">';
			$result .= '<ul>';

			$lastIndex = count($items)-1;

			$index = 0;
			foreach ($items as $caption=>$attributes)
			{
				if (!is_array($attributes))
					$attributes = array('href'=>$attributes);

				$attrStr = Phpr_Html::formatAttributes($attributes);
				$result .= '<li><a '.$attrStr.'>'.h($caption).'</a>';
				$result .= '</li>';
				$index ++;
			}

			$result .= '</ul></div></div>';
			
			$result .= '<script type="text/javascript">
				new ButtonMenu("'.$controlId.'");
			</script>';
			
			return $result;
		}

		/**
		 * Outputs a backend button
		 */
		public static function button($caption, $attributes = array(), $ajaxHandler=null, $ajaxParams = null, $formElement = null)
		{
			$divAttrs = array();
			$aAttrs = array();

			if (is_array($attributes))
			{
				foreach ($attributes as $key=>$value)
				{
					if ($key !== 'onclick' && $key !== 'href')
					{
						if ($key === 'class')
							$value = 'button '.$value;

						$divAttrs[$key] = $value;
					}
					else 
						$aAttrs[$key] = $value;
				}

				$ajaxRequest = null;
				if ($ajaxHandler !== null)
				{
					if ($formElement == null)
						$formElement = '$(this).getForm()';
					else
						$formElement = "$('$formElement')";

					$updateFlash = 'update: $(this).getForm().getElement(\'div.formFlash\')';
					if ($ajaxParams !== null)
					{
						if (strpos($ajaxParams, 'update') === false)
							$ajaxParams .= ', '.$updateFlash;
					} else
						$ajaxParams = $updateFlash;

					$ajaxParams = $ajaxParams !== null ? '{'.$ajaxParams.'}' : '{}';
					$ajaxRequest = "$formElement.sendPhpr('{$ajaxHandler}', {$ajaxParams});";

					if (array_key_exists('onclick', $aAttrs))
						$aAttrs['onclick'] = $ajaxRequest.$aAttrs['onclick'];
					else
						$aAttrs['onclick'] = $ajaxRequest.' return false;';
				}

				$aAttrList = Phpr_Html::formatAttributes($aAttrs, array('href'=>'#'));
				$divAttrList = Phpr_Html::formatAttributes($divAttrs, array('class'=>'button'));
			} else
			{
				$aAttrList = Phpr_Html::formatAttributes($aAttrs, array('href'=>$attributes));
				$divAttrList = Phpr_Html::formatAttributes($divAttrs, array('class'=>'button'));
			}

			return '<div '.$divAttrList.'><a '.$aAttrList.'>'.$caption.'</a></div>';
		}

		/**
		 * Outputs a control panel backend button
		 */
		public static function ctr_button($caption, $button_class, $attributes = array(), $ajaxHandler=null, $ajaxParams = null, $formElement = null)
		{
			$aAttrs = array();

			if (is_array($attributes))
			{
				foreach ($attributes as $key=>$value)
				{
					if ($key === 'class')
						$value = $button_class.' imageLink img_noBottomPading '.$value;
					
					$aAttrs[$key] = $value;
				}
				
				if (!array_key_exists('class', $aAttrs))
					$aAttrs['class'] = $button_class.' imageLink img_noBottomPading';

				$ajaxRequest = null;
				if ($ajaxHandler !== null)
				{
					if ($formElement == null)
						$formElement = '$(this).getForm()';
					else
						$formElement = "$('$formElement')";

					$updateFlash = 'update: $(this).getForm().getElement(\'div.formFlash\')';
					if ($ajaxParams !== null)
					{
						if (strpos($ajaxParams, 'update') === false)
							$ajaxParams .= ', '.$updateFlash;
					} else
						$ajaxParams = $updateFlash;

					$ajaxParams = $ajaxParams !== null ? '{'.$ajaxParams.'}' : '{}';
					$ajaxRequest = "$formElement.sendPhpr('{$ajaxHandler}', {$ajaxParams});";

					if (array_key_exists('onclick', $aAttrs))
						$aAttrs['onclick'] = $ajaxRequest.$aAttrs['onclick'];
					else
						$aAttrs['onclick'] = $ajaxRequest.' return false;';
				}

				$aAttrList = Phpr_Html::formatAttributes($aAttrs, array('href'=>'#'));
			} else
				$aAttrList = Phpr_Html::formatAttributes($aAttrs, array('href'=>$attributes, 'class'=>$button_class.' imageLink img_noBottomPading'));

			return '<a '.$aAttrList.'>'.$caption.'</a>';
		}

		/*
		 * A simplified version of the button helper. Outputs a button triggering AJAX request, with default parameters.
		 * Usually usage of this helper reuires only 2 parameters - a catption and a server handler method name.
		 */
		public static function ajaxButton($caption, $ajaxHandler, $attributes = array(), $ajaxParams = null)
		{
			return self::button($caption, $attributes, $ajaxHandler, $ajaxParams, null);
		}

		/*
		 * A simplified version of the control panel button helper. Outputs a button triggering AJAX request, with default parameters.
		 */
		public static function ctr_ajaxButton($caption, $button_class, $ajaxHandler, $attributes = array(), $ajaxParams = null)
		{
			return self::ctr_button($caption, $button_class, $attributes, $ajaxHandler, $ajaxParams, null);
		}

		/**
		 * Returns word "even" each even call for a specified counter.
		 * Example: <tr class="<?= Backend_Html::zebra('customer') ?>">
		 * $param string $counterName Specifies a counter name.
		 */
		public static function zebra($counterName)
		{
			global $zebraCounters;
			if (!is_array($zebraCounters))
				$zebraCounters = array();

			if (!isset($zebraCounters[$counterName]))
				$zebraCounters[$counterName] = 0;

			$zebraCounters[$counterName]++;
			return $zebraCounters[$counterName] % 2 ? null : 'even';
		}

		/**
		 * Outputs a pagination markup with AJAX next and previous link handlers
		 * @param Phpr_Pagination $pagination Specifies a pagination object
		 * @param string $nextPageHandler JavaScript code for handling the next page link
		 * @param string $prevPageHandler JavaScript code for handling the previous page link
		 * @param string $exactPageHandler JavaScript code for handling the page number page link. 
		 * The link should contain the %s sequence for substituting the page index
		 */
		public static function ajaxPagination($pagination, $nextPageHandler='null', $prevPageHandler='null', $exactPageHandler='null')
		{
			$curPageIndex = $pagination->getCurrentPageIndex();
			$pageNumber = $pagination->getPageCount();

			$result = '<div class="pagination">';
			$result .= '<p>Showing  <strong>'.($pagination->getFirstPageRowIndex()+1).'-'.($pagination->getLastPageRowIndex()+1).'</strong>';
			$result .= ' of <strong id="list_row_count_label">'.$pagination->getRowCount().'</strong> records. ';

			$result .= 'Page: <span class="numbers">';
			
			if ($pageNumber < 11)
			{
				for ($i = 1; $i <= $pageNumber; $i++)
				{
					if ($i != $curPageIndex+1)
						$result .= '<a href="#" onclick="return '.sprintf($exactPageHandler, $i-1).'">';

					$result .= $i.' ';

					if ($i != $curPageIndex+1)
						$result .= '</a>';
				}
			} else
			{
				$startIndex = $curPageIndex-5;
				$endIndex = $curPageIndex+5;
				$lastPageIndex = $pageNumber-1;
				
				if ($startIndex < 0)
					$startIndex = 0;
					
				if ($endIndex > $lastPageIndex)
					$endIndex = $lastPageIndex;

				if (($endIndex - $startIndex) < 11)
					$endIndex = $startIndex + 11;

				if ($endIndex > $lastPageIndex)
					$endIndex = $lastPageIndex;

				if (($endIndex - $startIndex) < 11)
					$startIndex = $endIndex - 11;

				if ($startIndex < 0)
					$startIndex = 0;
					
				$pages_str = null;
					
				for ($i = $startIndex+1; $i <= $endIndex+1; $i++)
				{
					if ($i != $curPageIndex+1)
						$pages_str .= '<a href="#" onclick="return '.sprintf($exactPageHandler, $i-1).'">';

					$pages_str .= $i.' ';

					if ($i != $curPageIndex+1)
						$pages_str .= '</a>';
				}
				
				if ($startIndex > 0)
				{
					$start_links = '<a href="#" onclick="return '.sprintf($exactPageHandler, 0).'">1</a> ';
					if ($startIndex > 1)
						$start_links .= ' ... ';
					
					$pages_str = $start_links.$pages_str;
				}
				
				if ($endIndex < $lastPageIndex)
				{
					if ($lastPageIndex - $endIndex > 1)
						$pages_str .= '... ';

					$pages_str .= '<a href="#" onclick="return '.sprintf($exactPageHandler, $lastPageIndex).'">'.($lastPageIndex+1).'</a>';
				}
				
				$result .= $pages_str;
			}
			
			$result .= '</span></p>';
			$result .= '<p class="pages">';

			if ($curPageIndex) 
				$result .= '<a href="#" onclick="return '.$nextPageHandler.'">';

			$result .= '&#x2190; Previous page';
			if ($curPageIndex) 
				$result .= '</a>';

			$result .= ' | ';

			if ($curPageIndex < $pageNumber-1) 
				$result .= '<a href="#" onclick="return '.$prevPageHandler.'">';

			$result .= 'Next page &#x2192;';
			if ($curPageIndex < $pageNumber-1) 
				$result .= '</a>';

			$result .= '</p></div>';
			return $result;
		}

		public static function flash()
		{
			$result = null;

			foreach ( Phpr::$session->flash as $type=>$message )
			{
				if ($type == 'system')
					continue;

				$result .= '<div class="'.$type.'">'.$message.'</div>';
			}

			Phpr::$session->flash->now();

			return $result;
		}
		
		/**
		 * Outputs flash-type message
		 * @param string $message Message to putput
		 * @param string $type Message type - success or error
		 * @return string
		 */
		public static function flash_message($message, $type = 'success')
		{
			return '<div class="'.$type.'">'.h($message).'</div>';
		}

		/**
		 * Attaches the calendar control to a specified HTML field
		 * @return string Returns java-script code string
		 */
		public static function calendar($fieldId)
		{
			$dateFormat = str_replace('%', null, Phpr::$lang->mod( 'phpr', 'short_date_format', 'dates'));
			$week = Phpr::$lang->mod( 'phpr', 'week_abbr', 'dates');

			$days = self::loadDatesLangArr('A_weekday_', 7);
			$daysShort = self::loadDatesLangArr('a_weekday_', 7, 7);
			$months = self::loadDatesLangArr('n_month_', 12);
			$monthShort = self::loadDatesLangArr('b_month_', 12);

			$result = "new DateRangePicker({
				inputs: ['$fieldId'], 
				'format': '$dateFormat', 
				'date': '', 
				'locale': {
					'days': [$days],
					'daysShort': [$daysShort],
					'daysMin': [$daysShort],
					'months': [$months],
					'monthsShort': [$monthShort],
					'weekMin': '$week'
				}});";

			return $result;
		}

		public static function loadDatesLangArr($modifier, $num, $offset = 1)
		{
			$result = array();

			$index = $offset;
			$cnt = 1;
			while ($cnt <= $num)
			{
				$result[] = "'".Phpr::$lang->mod( 'phpr', $modifier.$index, 'dates')."'";
				$index++;
				$cnt++;

				if ($index > $num)
					$index = 1;
			}

//			for ($i=$offset; $i<=$num; $i++)
//				$result[] = "'".Phpr::$lang->mod( 'phpr', $modifier.$i, 'dates')."'";

			return implode(',', $result);
		}

		/**
		 * Compares two strings and returns a content of the second string,
		 * surrounding changed words with span elements
		 * @return string
		 */
		public static function stringDiff($str1, $str2, $treatAsList = false)
		{
			$separator = $treatAsList ? ', ' : ' ';
			$first = explode($separator, $str1);
			$second = explode($separator, $str2);

			if ($treatAsList)
			{
				foreach ($first as &$value) $value = trim($value);
				foreach ($second as &$value) $value = trim($value);
			}

			$difference = array_diff($second,$first);

			$result = null;
			$cnt = count($second);
			foreach($second as $index=>$word)
			{
				$spillter = $index < $cnt-1 ? $separator : null;
				$changed = !$treatAsList ? in_array($word, $difference) : !in_array($word, $first);

				if ($changed)
					$result .= "<span>".$word."</span>".$spillter;
				else
					$result .= $word.$spillter;
			}

			return $result;
		}

		public static function controllerUrl()
		{
			$module = Phpr::$router->param('module');
			$controller = str_replace($module.'_', '',  Phpr::$router->controller);

			return self::url($module.'/'.$controller);
		}
		
		public static function click_link($url)
		{
			$handler = "window.location.href = '".$url."'";
			return 'onclick="'.$handler.'"';
		}
		
		public static function alt_click_link($url, $alt_url)
		{
			return "if (new Event(event).alt) window.location.href = '".$alt_url."'; else window.location.href = '".$url."'; return false";
		}
	}

?>