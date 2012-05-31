<h1>Categories</h1>
<?
	try {
		$params = array('list_class' => 'main_category_list', 'category_url_name' => $product->category_list->objectArray[0]->url_name);
	} catch (Exception $e) {
		$params = array('list_class' => 'main_category_list');
	}
	$this->render_partial('shop:categories', $params); unset($params);
?>