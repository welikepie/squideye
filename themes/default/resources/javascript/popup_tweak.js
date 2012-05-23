window.addEvent('frontendready', function(){
  Request.Phpr.implement({
	popupError: function(xhr)
	{
		// Default action
		// alert(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
		
		// Modified action
		console.log(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
	}
  })
});