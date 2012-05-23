function setup_comment_controls()
{
	jQuery('#listBlog_Posts_preview_list').tableRowMenu();
	$('listBlog_Posts_preview_list').addEvent('listUpdated', function(){
		jQuery('#listBlog_Posts_preview_list').tableRowMenu();
	})
}

function update_comment_status(element, comment_id, confirm_str, status_code)
{
	return $(element).getForm().sendPhpr('preview_onSetCommentStatus', {update: 'comment_list', confirm: confirm_str, 'extraFields': {'id': comment_id, 'status': status_code}, onFailure: popupAjaxError, loadIndicator: {show: false}, onBeforePost: LightLoadingIndicator.show.pass('Updating...'), onComplete: LightLoadingIndicator.hide, onAfterUpdate:setup_comment_controls});
}

window.addEvent('domready', setup_comment_controls)