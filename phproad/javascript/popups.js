
/*
 * Popup modal windows
 */

var Overlay = new Class({
 	Implements: [Options, Events],

	options: {
		opacity: 0.1,
		className: 'overlay',
		background: '',
		zIndex: 1600,
		closeByClick: false
	},

	initialize: function(options) 
	{
		this.setOptions(options);
		this.overlay = null;
		this.listeners = {
			resize: this.resize.bind(this),
			click: this.click.bind(this)
		};
	},

	toggleListeners: function(state) 
	{
		var task = state ? 'addEvent' : 'removeEvent';
		window[task]('resize', this.listeners.resize);
		window[task]('scroll', this.listeners.resize);
	},

	build: function() 
	{
		if (!this.overlay) 
		{
			this.overlay = new Element('div', {
				'class': this.options.className,
				'styles': {
					'position': 'fixed',
					'left': 0,
					'top': 0,
					'width': 1,
					'height': 1,
					'padding': 0,
					'margin': 0,
					'opacity': 0,
					'visibility': 'hidden',
					'z-index': this.options.zIndex
				}
			}).inject(document.body, 'top');
			
			if (this.options.background != '')
				this.overlay.setStyle('background', this.options.background);

			this.overlay.addEvent('click', this.listeners.click);
			this.overlay.addEvent('mouseup', function(event) { new Event(event).stop(); } );
		}
	},

	resize: function() 
	{
		this.fireEvent('resize');
		
		if (this.overlay) {
			var sizes = {
				scroll: window.getScroll(),
				scrollSize: window.getScrollSize(),
				size: window.getSize()
			}
			if (Browser.Engine.trident) {
				this.overlay.setStyles({
					'left': sizes.scroll.x,
					'top': sizes.scroll.y
				});
			}
			this.overlay.setStyles({
				'width': sizes.size.x,
				'height': sizes.size.y
			});
		}
	},

	show: function() 
	{
		if (this.overlay && this.overlay.getStyle('visibility') != 'hidden') return;
		this.fireEvent('show');
		this.build();
		if (this.overlay) {
			this.resize();
			this.toggleListeners(true);
			this.overlay.setStyle('visibility', 'visible').set('tween', {duration: 250}).tween('opacity', this.options.opacity);
		}
	},

	hide: function() 
	{
		if (!this.overlay || this.overlay.getStyle('visibility') == 'hidden') 
			return;
			
		this.fireEvent('hide');
		if (this.overlay) 
		{
			this.toggleListeners(false);
			this.overlay.set('tween', {
				duration: 250,
				onComplete: function() {
					this.overlay.setStyles({
						'visibility': 'hidden',
						'left': 0,
						'top': 0,
						'width': 0,
						'height': 0
					});
				}.bind(this)
			}).tween('opacity', 0);
		}
	},

	click: function() 
	{
		this.fireEvent('click');
		if (this.options.closeByClick)
		{
			this.fireEvent('close');
			this.hide();
		}
	},

	destroy: function() 
	{
		if (!this.overlay)
			return;

		this.toggleListeners(false);
		this.overlay.removeEvent('click', this.listeners.click);
		this.overlay.destroy();
		this.overlay = null;
	}
});

PopupForm = new Class({
 	Implements: [Options, Events],
	Binds: ['cancel'],

	formLoadHandler: null,
	overlay: null,
	formContainer: null,
	tmp: null,
	lockName: false,

	options: {
		opacity: 0.1,
		className: 'popupForm',
		background: '',
		zIndex: 1601,
		closeByClick: false,
		ajaxFields: {},
		closeByEsc: true,
		popupData: {}
	},

	initialize: function(formLoadHandler, options)
	{
		var lockName = 'poppup' + formLoadHandler;

		if (lockManager.get(lockName))
			return;

		lockManager.set(lockName);
		this.lockName = lockName;
		
		this.setOptions(options);
		this.formLoadHandler = formLoadHandler;
		this.show();
		window.PopupWindows.push(this);
	},
	
	show: function()
	{
		this.overlay = new Overlay({
			onClose: cancelPopup, 
			onResize: this.alignForm.bind(this), 
			closeByClick: this.options.closeByClick,
			zIndex: 1601 + window.PopupWindows.length + 1
		});
		
		addPopup();
		
		this.overlay.show();

		this.formContainer = new Element('div', {
			'class': 'popupLoading',
			'styles': {
				'position': 'absolute',
				'visibility': 'hidden',
				'z-index': this.options.zIndex + window.PopupWindows.length + 1,
				'padding': '10px'
			}
		}).inject($('content'), 'top');

		this.tmp = new Element('div', {'styles': {'visibility': 'hidden', 'position': 'absolute'}}).inject($('content'), 'top');
		new Element('div', {'class': 'popupForm'}).inject(this.tmp, 'top');

		this.alignForm();
		this.formContainer.setStyle( 'visibility', 'visible' );
		
		if (this.options.closeByEsc)
	    	$(document).addEvent('keydown', function(event){
				if (event.key == 'esc')
				{
					this.cancel();
					event.stop();
				}
			}.bind(this));
		
		new Request.Phpr({url: location.pathname, handler: this.formLoadHandler, extraFields: this.options.ajaxFields, update: this.tmp.getFirst(), 
		loadIndicator: {show: false}, onSuccess: this.formLoaded.bind(this)}).post({});
	},
	
	cancel: function() 
	{
		var allow_close = true;

		try
		{
			var first_element = this.formContainer.getFirst();
			if (first_element)
			{
				var top_element = first_element.getFirst();
				if (top_element)
				{
					try
					{
						top_element.fireEvent('onClosePopup');
					} catch (e) {
						allow_close = false;
					}
				}
			}
		} catch (e) {}
		
		if (allow_close)
		{
			this.destroy();
		    $(document).removeEvent('keyescape', this.cancel);
			return false;
		}
	},

	formLoaded: function()
	{
		var newSize = this.tmp.getSize();

		var myEffect = new Fx.Morph(this.formContainer, {
			duration: 'short', 
			transition: Fx.Transitions.Sine.easeOut, 
			onStep: this.alignForm.bind(this),
			onComplete: this.loadComplete.bind(this)});

		myEffect.start({
		    'height': newSize.y,
		    'width': newSize.x+1
		}); 
	},
	
	loadComplete: function()
	{
		this.tmp.getFirst().inject(this.formContainer);
		this.tmp.destroy();
		this.formContainer.removeClass('popupLoading');
		this.formContainer.setStyles({'width': 'auto', 'height': 'auto'});
		
		var top_element = this.formContainer.getFirst().getFirst();
		if (top_element)
		{
			top_element.fireEvent('popupLoaded');
			window.fireEvent('popupLoaded', top_element);
			top_element.addClass('popup_content');
			top_element.popupData = this.options.popupData;
			
			var a = new Element('a', {'class': 'popup_close', 'title': 'Close'});
			a.innerHTML = 'Close';
			a.href = '#';
			a.addEvent('click', function(){ cancelPopup(); return false; });
			a.inject(top_element, 'top');
		}
	},
	
	alignForm: function()
	{
		if (!this.formContainer)
			return;

		var windowSize = window.getSize();
		var formSize = this.formContainer.getSize();
		var scroll = window.getScroll();

		if (!Browser.Engine.trident)
		{
			var top = 0;
			if (formSize.y > windowSize.y)
				top = Math.round(windowSize.y/2-formSize.y/2);
			else
				top = Math.round(scroll.y + windowSize.y/2-formSize.y/2);
				
			var left = Math.round(windowSize.x/2-formSize.x/2);
		} 
		else
		{
			var scrollSize = window.getScrollSize();
			
			var top = Math.round(scroll.y + windowSize.y/2-formSize.y/2);
			var left = Math.round(windowSize.x/2-formSize.x/2);
		}
		
		if(top < 0)
			top = 0;
			
		if(left < 0)
			left = 0;
			
		var contentContainer = this.formContainer.getElements('.content');
		var popupContent = this.formContainer.getElements('.popup_content');

		/*
		if(contentContainer && popupContent) {
			var popupSize = popupContent.getSize()[0];
			var contentOffset = contentContainer.getOffsets()[0];
			
			if(popupSize && contentOffset) {
				var offset = formSize.y - popupSize.y + contentOffset.y;
				
				if(formSize.y > windowSize.y) {
					contentContainer.setStyles({
						'overflow-y': 'scroll',
						'height': windowSize.y - offset
					});
				}
				else {
					contentContainer.setStyles({
						'overflow-y': 'hidden',
						'height': 'auto'
					});
				}
			}
		}
		*/
		
		this.formContainer.setStyles({
			'left': left,
			'top': top
		});
	},
	
	destroy: function()
	{
		hide_tooltips();
		lockManager.remove(this.lockName);
		
		this.overlay.destroy();
		this.formContainer.destroy();
		window.PopupWindows.pop();
	}
});

window.PopupWindows = [];

function cancelPopup()
{
	if (window.PopupWindows.length)
		window.PopupWindows.getLast().cancel();
		
	if (!window.PopupWindows.length)
		window.fireEvent('popupHide');

	return false;
}

function cancelPopups()
{
	while (window.PopupWindows.length)
		cancelPopup();
}

function addPopup()
{
	if (window.PopupWindows.length == 0)
		window.fireEvent('popupDisplay');
}

function realignPopups()
{
	window.PopupWindows.each(function(popup){popup.alignForm()});
}