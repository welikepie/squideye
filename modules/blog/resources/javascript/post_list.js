window.addEvent('domready', function(){
	jQuery('#listBlog_Posts_index_list').tableRowMenu();
	$('listBlog_Posts_index_list').addEvent('listUpdated', function(){
		jQuery('#listBlog_Posts_index_list').tableRowMenu();
	})
})