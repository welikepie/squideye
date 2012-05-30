<?

	if (!isset($category_url_name)) { $category_url_name = null; }
	if (!isset($list_class)) { $list_class = null; }
	if (!isset($item_class)) { $item_class = null; }
	
	if (!function_exists('class_attr')) {
		function class_attr($classes) {
			if (is_array($classes)) { $classes = implode(' ', $classes); }
			if ($classes) { return ' class="' . $classes . '"'; }
			else { return ''; }
		}
	}

	$categories = isset($parent_category) ?
		$parent_category->list_children('front_end_sort_order') :
		Shop_Category::create()->list_root_children('front_end_sort_order');
	
	if ($categories->count) { ?>
	<ul<?= class_attr($list_class) ?>>
		<? foreach ($categories as $category) {
			$item_classes = array();
			if ($item_class) { $item_classes[] = $item_class; }
			if ($category_url_name === $category->url_name) { $item_classes[] = 'current'; }
		?>
		<li<?= class_attr($item_classes) ?>><a href="<?= $category->page_url('/category') ?>"><?= $category->name ?></a></li>
		<? 
			$recursive_params = array('parent_category' => $category);
			if ($category_url_name) { $recursive_params['category_url_name'] = $category_url_name; }
			if ($item_class) { $recursive_params['item_class'] = $item_class; }
			$this->render_partial('shop:categories', $recursive_params);
		} ?>
	</ul>
	<? }

?>