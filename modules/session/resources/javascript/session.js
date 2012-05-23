Element.implement({
	getLoadingIndicatorDefaults: function()
	{
		return {
			overlayClass: 'formOverlay',
			pos_x: 'center',
			pos_y: 'center',
			src: 'modules/session/resources/images/load70x70.gif',
			injectInElement: false,
			noImage: false,
			z_index: 9999,
			absolutePosition: true,
			injectPosition: 'bottom',
			hideElement: true
		};
	}
});

Request.Phpr.implement({
	getRequestDefaults: function()
	{
		return {
			loadIndicator: {
				element: 'FormElement',
				show: true,
				hideOnSuccess: false
			},
			onFailure: this.highlightError.bind(this),
			errorHighlight: {
				backgroundFromColor: '#f00',
				backgroundToColor: '#ffffcc'
			},
			onAfterError: this.highlightFormError.bind(this)
		};
	},
	
	highlightFormError: function()
	{
		$(document.body).getElements('ul.formElements li.field').each(function(el){
			el.removeClass('error');
		});
		
		var el = $(window.phprErrorField);
		if (!el)
			return;

		var parentLi = el.getParent('li.field');
		if (parentLi)
			parentLi.addClass('error');
	}
});

/*
 * Preload load indicator images
 */

new Asset.image(ls_root_url('/modules/session/resources/images/load70x70.gif'));
