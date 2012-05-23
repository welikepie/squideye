/*
 * URL functions
 */

function ls_root_url(url)
{
	if (!lemonstand_root_dir)
		return url;
		
	if (url.substr(0,1) == '/')
		url = url.substr(1);
	
	return lemonstand_root_dir + url;
}

/*
 * Phpr request
 */

Request.Phpr = new Class({
	Extends: Request.HTML,
	loadIndicatorName: false,
	lockName: false,
	singleUpdateElement: false,

	options: {
		handler: false,
		extraFields: {},
		loadIndicator: {
			show: false,
			hideOnSuccess: true
		},
		lock: true,
		lockName: false,
		evalResponse: true,
		onAfterError: $empty,
		onBeforePost: $empty,
		treeUpdate: false,
		confirm: false,
		preCheckFunction: false,
		postCheckFunction: false,
		prepareFunction: $empty,
		execScriptsOnFailure: true,
		evalScriptsAfterUpdate: false,
		alert: false,
		noLoadingIndicator: false // front-end feature
	},
	
	getRequestDefaults: function()
	{
		return {
			loadIndicator: {
				element: null
			},
			onFailure: this.popupError.bind(this),
			errorHighlight: {
				element: null,
				backgroundFromColor: '#f00',
				backgroundToColor: '#ffffcc'
			}
		};
	},

	initialize: function(options)
	{
		this.parent($merge(this.getRequestDefaults(), options));

		this.setHeader('PHPR-REMOTE-EVENT', 1);
		this.setHeader('PHPR-POSTBACK', 1);
		
		if (this.options.handler)
			this.setHeader('PHPR-EVENT-HANDLER', 'ev{'+this.options.handler+'}');

		this.addEvent('onSuccess', this.updateMultiple.bind(this));
		this.addEvent('onComplete', this.processComplete.bind(this));
	},
	
	post: function(data)
	{
		if (this.options.lock)
		{
			var lockName = this.options.lockName ? this.options.lockName : 'request' + this.options.handler + this.options.url;
			
			if (lockManager.get(lockName))
				return;
		}

		if (this.options.preCheckFunction)
		{
			if (!this.options.preCheckFunction.call())
				return;
		}

		if (this.options.alert)
		{
			alert(this.options.alert);
			return;
		}

		if (this.options.confirm)
		{
			if (!confirm(this.options.confirm))
				return;
		}
		
		if (this.options.postCheckFunction)
		{
			if (!this.options.postCheckFunction.call())
				return;
		}

		if (this.options.prepareFunction)
			this.options.prepareFunction.call();
			
		if (this.options.lock)
		{
			var lockName = this.options.lockName ? this.options.lockName : 'request' + this.options.handler + this.options.url;

			lockManager.set(lockName);
			this.lockName = lockName;
		}

		this.dataObj = data;
		
		if (!this.options.data)
		{
			var dataArr = [];

			switch ($type(this.options.extraFields)){
				case 'element': dataArr.push($(this.options.extraFields).toQueryString()); break;
				case 'object': case 'hash': dataArr.push(Hash.toQueryString(this.options.extraFields));
			}
			
			switch ($type(data)){
				case 'element': dataArr.push($(data).toQueryString()); break;
				case 'object': case 'hash': dataArr.push(Hash.toQueryString(data));
			}
			
			this.options.data = dataArr.join('&');
		}

		if (this.options.loadIndicator.show)
		{
			this.loadIndicatorName = 'request' + new Date().getTime();
			$(this.options.loadIndicator.element).showLoadingIndicator(this.loadIndicatorName, this.options.loadIndicator);
		}
		
		this.fireEvent('beforePost', {});

		if (MooTools.version >= "1.3")
			this.parent(this.options.data);
		else
			this.parent();
	},
	
	processComplete: function()
	{
		if (this.options.lock)
			lockManager.remove(this.lockName);
	},
	
	success: function(text)
	{
		var options = this.options, response = this.response;

		response.html = text.phprStripScripts(function(script){

			response.javascript = script;
		});

		if (options.update && options.update != 'multi' && !(/window.location=/.test(response.javascript)) && $(options.update))
		{
			if (this.options.treeUpdate)
			{
				var temp = this.processHTML(response.html);
				response.tree = temp.childNodes;
				if (options.filter) response.tree = response.elements.filter(options.filter);
				
				response.elements = temp.getElements('*');
		 		$(options.update).empty().adopt(response.tree);
			}
			else
	 			$(options.update).set({html: response.html});
		}

		this.fireEvent('beforeScriptEval', {});

		if (options.evalScripts && !options.evalScriptsAfterUpdate) 
			$exec(response.javascript);
		
		this.onSuccess(response.tree, response.elements, response.html, response.javascript);
	},
	
	updateMultiple: function(responseTree, responseElements, responseHtml, responseJavascript)
	{
		this.fireEvent('onResult', [this, responseHtml], 20);

		if (this.options.loadIndicator.hideOnSuccess)
			this.hideLoadIndicator();
			
		var updated_elements = [];

		if (!this.options.update || this.options.update == 'multi')
		{
			this.multiupdateData = new Hash();

			var pattern = />>[^<>]*<</g; 
			var Patches = responseHtml.match(pattern);
			if (!Patches) return;
			for ( var i=0; i < Patches.length; i++ )
			{
				var index = responseHtml.indexOf(Patches[i]) + Patches[i].length;
				var updateHtml = (i < Patches.length-1) ? responseHtml.slice( index, responseHtml.indexOf(Patches[i+1]) ) :
					responseHtml.slice(index);
				var updateId = Patches[i].slice(2, Patches[i].length-2);

				if ( $(updateId) )
				{
					$(updateId).set({html: updateHtml}); 
					updated_elements.push(updateId);
				}
			}
		}

		if (this.options.evalScripts && this.options.evalScriptsAfterUpdate) 
			$exec(this.response.javascript);
			
		$A(updated_elements).each(function(element_id){
			window.fireEvent('onAfterAjaxUpdate', element_id);
		});

		this.fireEvent('onAfterUpdate', [this, responseHtml], 20);
		window.fireEvent('onAfterAjaxUpdateGlobal');
	},

	isSuccess: function(){
		return !this.xhr.responseText.test("@AJAX-ERROR@");
	},
	
	hideLoadIndicator: function()
	{
		if (this.options.loadIndicator.show)
			$(this.options.loadIndicator.element).hideLoadingIndicator(this.loadIndicatorName);
	},

	onFailure: function()
	{
		this.hideLoadIndicator();

		var javascript = null;
		text = this.xhr.responseText.phprStripScripts(function(script){javascript = script;});
		this.fireEvent('complete').fireEvent('failure', {message: text.replace('@AJAX-ERROR@', ''), responseText: text, responseXML: text} );

		if (this.options.execScriptsOnFailure)
			$exec(javascript);

		this.fireEvent('afterError', {});
	},
	
	popupError: function(xhr)
	{
		alert(xhr.responseText.replace('@AJAX-ERROR@', ''));
	},
	
	highlightError: function(xhr)
	{
		var element = null;

		if (this.options.errorHighlight.element != null)
			element = $(this.options.errorHighlight.element);
		else
		{
			if (this.dataObj && $type(this.dataObj) == 'element')
				element = $(this.dataObj).getElement('.formFlash');
		}

		if (!element)
			return;

		element.innerHTML = '';
		var pElement = new Element('p', {'class': 'error'});
		pElement.innerHTML = xhr.responseText.replace('@AJAX-ERROR@', '');
		pElement.inject(element, 'top');
		pElement.set('morph', {duration: 'long', transition: Fx.Transitions.Sine.easeOut});

		if (this.options.errorHighlight.backgroundFromColor)
		{
			pElement.morph({
				'background-color': [this.options.errorHighlight.backgroundFromColor, 
					this.options.errorHighlight.backgroundToColor]
			});
		}
		
		/*
		 * Re-align popup forms
		 */
		realignPopups();
	}
});

Element.implement({
	sendPhpr: function(handlerName, options)
	{
		var action = $(this).get('action');
		
		var defaultOptions = {url: action, handler: handlerName, loadIndicator: {element: this}};
		new Request.Phpr($merge(defaultOptions, options)).post(this);
		return false;
	}
});

function popupAjaxError(xhr)
{
	alert(xhr.responseText.replace('@AJAX-ERROR@', '').replace(/(<([^>]+)>)/ig,""));
}

/*
 * Class mutators
 */

Class.Mutators.Binds = function(self, methods) 
{
  $splat(methods).each(function(method){
      var fn = self[method];
      self[method] = function(){
         return fn.apply(self, arguments);
      };
   });
};

/*
 * Element extensions
 */

var CHotkeySelector = new Class({
	ContainerElement: null,
	Key: null,
	Modifiers: null,
	Function: Class.Empty,

	initialize: function( ContainerId, Key, Modifiers, Function )
	{
		this.Key = Key;
		this.Modifiers = Modifiers;
		this.Function = Function;
		
		if ( $type(ContainerId) == 'string' && ContainerId.length )
			this.ContainerElement = $(ContainerId);
	}
});

Element.implement({
	activeSpinners: new Hash(),
	keyMap: false,
	boundKeys: false,
	
	getForm: function()
	{
		return this.findParent('form');
	},

	findParent: function(tagName)
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			if ($(CurrentParent).get('tag') == tagName)
				return $(CurrentParent);

			CurrentParent = CurrentParent.parentNode;
		}

		return null;
	},
	
	selectParent: function(selector)
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			if ($(CurrentParent).match(selector))
				return $(CurrentParent);

			CurrentParent = CurrentParent.parentNode;
		}

		return null;
	},

	/*
	 * Focus handling
	 */	
	focusFirst: function() 
	{
		for (var el=0; el < this.elements.length; el++)
		{
			var TagName = this.elements[el].tagName;

			if ( (((TagName == 'INPUT' && this.elements[el].type != 'hidden')) || TagName == 'SELECT' || TagName == 'BUTTON' || TagName == 'TEXTAREA') && !this.elements[el].disabled && $(this.elements[el]).isVisible() )
			{
				this.elements[el].focus();
				break;
			}
		}

		return true;
	},

	focusField: function(field)
	{
		var fieldObj = $type(field) == 'string' ? $(field) : field;

		if (fieldObj && !fieldObj.disabled)
		{
			window.TabManagers.some(function(manager){
				return manager.findElement(fieldObj.get('id'));
			});

			if (fieldObj.isVisible())
				fieldObj.focus();
		}
	},
	
	safe_focus: function()
	{
		try
		{
			this.focus();
		} catch (e) {}
	},

	/*
	 * Visibility
	 */
	hide: function()
	{
		this.addClass('hidden');
	},

	show: function()
	{
		this.removeClass('hidden');
	},

	isVisible: function()
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			if ($(CurrentParent).hasClass('Hidden') || $(CurrentParent).hasClass('hidden'))
				return false;

			CurrentParent = CurrentParent.parentNode;
		}

		return true;
	},

	invisible: function()
	{
		this.addClass('invisible');
	},
	
	visible: function()
	{
		this.addClass('visible');
	},

	/*
	 * Loading indicators
	 */
	getLoadingIndicatorDefaults: function()
	{
		return {
			overlayClass: null,
			pos_x: 'center',
			pos_y: 'center',
			src: null,
			injectInElement: false,
			noImage: false,
			z_index: 9999,
			absolutePosition: true,
			injectPosition: 'bottom',
			overlayOpacity: 1,
			hideElement: true
		};
	},
	
	showLoadingIndicator: function(name, options)
	{
		options = $merge(this.getLoadingIndicatorDefaults(), options ? options : {});

		if (!$('content'))
			throw "showLoadingIndicator: Element with identifier 'content' is not found.";

		if (options.src == null && !options.noImage)
			throw "showLoadingIndicator: options.src is null";
			
		var container = options.injectInElement ? this : $('content');

		var position = options.absolutePosition ? 'absolute' : 'static';
		
		var overlayElement = null;
		var imageElement = null;

		if (options.overlayClass)
			overlayElement = $(document.createElement('div')).set({
				'styles': {
					'visibility': 'hidden', 
					'position': position,
					'opacity': options.overlayOpacity,
					'z-index': options.z_index},
				'class': options.overlayClass
			}).inject(container, options.injectPosition);

		if (!options.noImage) {
			imageElement = $(document.createElement('img')).set({
				'styles': {
					'visibility': 'hidden', 
					'position': 'absolute', 
					'z-index': options.z_index+1},
				'src': options.src
			}).inject(container, options.injectPosition);
		}

		var eS = this.getCoordinates();
		if (!options.noImage)
			var iS = imageElement.getCoordinates();

		var top = options.injectInElement ? 0 : eS.top;
		var left = options.injectInElement ? 0 : eS.left;
		
		if (overlayElement)
		{
			overlayElement.set({
				styles:{
					'width': eS.width,
					'height': eS.height,
					'top': top,
					'left': left,
					'visibility': 'visible'
				}
			});
		}

		if (!options.noImage)
		{
			if (iS.width == 0)
			{
				var size_str = options.src.match(/[0-9]+x[0-9]+/);
				if (size_str)
				{
					var dim = options.src.match(/([0-9]+)x([0-9]+)/);
					iS.width = dim[1];
					iS.height = dim[2];
				}
			}
			
			imageElement.set({
				'styles': {
					'left': function(){
						switch (options.pos_x) {
							case 'center' : return eS.width/2 + left - iS.width/2;
							case 'left': return left;
							case 'right': return left + eS.width - iS.width;
						}}(),
					'top': function(){
						switch (options.pos_y) {
							case 'center' : return eS.height/2 + top - iS.height/2;
							case 'top': return top;
							case 'bottom': return top + eS.height - iS.height;
						}}(),
					'visibility': 'visible'
				}
			});
		}

		if (options.hideElement)
			this.setStyle( 'visibility', 'hidden' );
			
		this.activeSpinners.set( name, {spinner: imageElement, overlay: overlayElement}); 
	},
	
	hideLoadingIndicator: function(name)
	{
		if ( this.activeSpinners.has(name) )
		{
			var spinner = this.activeSpinners.get(name);
			if (spinner.spinner)
				spinner.spinner.destroy();

			if (spinner.overlay)
				spinner.overlay.destroy();

			this.activeSpinners.erase(name);

			if (!this.activeSpinners.getKeys.length)
				this.setStyle( 'visibility', 'visible' );
		}
	},
	
	/*
	 * Key mapping
	 */
	bindKeys: function(keyMap)
	{
		if (Browser.Engine.trident)
			$(document).addEvent('keydown', this.handleKeys.bindWithEvent(this, this) );
		else
			$(window).addEvent('keydown', this.handleKeys.bindWithEvent(this, this) );

		this.keyMap = keyMap;
		this.rebindKeyMap();
	},
	
	unBindKeys: function()
	{
		if (window.ie)
			$(document).removeEvent('keydown', this.handleKeys );
		else
			$(window).removeEvent('keydown', this.handleKeys );
	},
	
	rebindKeyMap: function()
	{
		if (this.keyMap)
		{
			this.boundKeys = [];

			for (var key in this.keyMap)
			{
				var mapElement = key;
				var containerElement = '';
				if (key.test(/^[a-z0-9_]+:/i))
				{
					var Parts = key.split(':');
					mapElement = Parts[1].trim();
					containerElement = Parts[0].trim();
				}

				var keySets = mapElement.split(',');

				keySets.each(function(keySet)
				{
					keySets = keySet.trim();
					var parts = keySet.split("+");
					for (var i=0; i < parts.length; i++)
						parts[i] = parts[i].trim();

					if (parts.length)
					{
						this.boundKeys.include(
							new CHotkeySelector( containerElement, parts.getLast(), parts.erase(parts.getLast()), this.keyMap[key])
						);
					}
				}, this);
			};
		};
	},
	
	handleKeys: function(event, element)
	{
		var event = new Event(event);
		var is_modifier_found = false;

		if (element.boundKeys)
		{
			element.boundKeys.each(function(selector)
			{
				if (is_modifier_found)
					return;

				if ( selector.Key == event.key )
				{
					var container = selector.ContainerElement ? selector.ContainerElement : element;

					if (container.hasChild(event.target) || event.target == element)
					{
						var ModifierFound = true;

						selector.Modifiers.each(function(Modifier){
							if ( Modifier == 'alt' && !event.alt ) ModifierFound = false;
							if ( Modifier == 'meta' && !event.meta ) ModifierFound = false;
							if ( (Modifier == 'control' || Modifier == 'ctrl')  && !event.control ) ModifierFound = false;
							if ( Modifier == 'shift' && !event.shift ) ModifierFound = false;
						});

						if ( ModifierFound )
						{
							is_modifier_found = true;
							event.stop();
							event.preventDefault();
							selector.Function(event);
							return;
						}
					}
				}
			});
		};
	},
	
	toQueryString: function(){
		var queryString = [];
		$(this).getElements('input, select, textarea').each(function(el){
			if (!el.name || el.disabled) return;
			var value = (el.tagName.toLowerCase() == 'select') ? Element.getSelected(el).map(function(opt){
				return opt.value;
			}) : ((el.type == 'radio' || el.type == 'checkbox') && !el.checked) ? null : el.value;
			$splat(value).each(function(val){
				if (val || el.type != 'checkbox') queryString.push(el.name + '=' + encodeURIComponent(val));
			});
		});
		return queryString.join('&');
	},
	
	fieldsToHash: function() {
		var result = {};
		$(this).getElements('input, select, textarea').each(function(el){
			if (!el.name || el.disabled) return;
			var value = (el.tagName.toLowerCase() == 'select') ? Element.getSelected(el).map(function(opt){
				return opt.value;
			}) : ((el.type == 'radio' || el.type == 'checkbox') && !el.checked) ? null : el.value;
			$splat(value).each(function(val){
				if (val || el.type != 'checkbox') result[el.name] = val;
			});
		});
		
		return result;
	},
	
	insertTextAtCursor: function(text){
		if (document.selection) {
			this.focus();
			sel = document.selection.createRange();
			sel.text = text + sel.text;
			this.focus();
		} else if (this.selectionStart || this.selectionStart == '0') {
			var prevScrollTop = this.scrollTop;
			var startPos = this.selectionStart;
			this.value = this.value.substring(0, startPos) + text + this.value.substring(startPos, this.value.length);
			this.selectionStart = startPos + text.length;
			this.selectionEnd = this.selectionStart;
			this.scrollTop = prevScrollTop;
			this.focus.delay(20, this);
		} else
			this.value += text;
	},
	
	addTextServices: function()
	{
		this.addEvent('keydown', function(e){
			if (e.code == 9)
			{
				e.stop();
				this.insertTextAtCursor("\t");
			}
		}.bind(this));
	},
	
	getOffsetsIeFixed: function(){
		/*
		 * MooTools 1.2 code replaced with MooTools 1.2.3. It correctly calculates offsets for IE.
		 */

		if (this.getBoundingClientRect){
			var bound = this.getBoundingClientRect(),
			html = $(this.getDocument().documentElement),
			scroll = html.getScroll(),
			isFixed = (fix_styleString(this, 'position') == 'fixed');
			return {
				x: parseInt(bound.left, 10) + ((isFixed) ? 0 : scroll.x) - html.clientLeft,
				y: parseInt(bound.top, 10) +  ((isFixed) ? 0 : scroll.y) - html.clientTop
			};
		}

		var element = this, position = {x: 0, y: 0};
		if (isBody(this)) return position;

		while (element && !fix_isBody(element)){
			position.x += element.offsetLeft;
			position.y += element.offsetTop;

			if (Browser.Engine.gecko){
				if (!borderBox(element)){
					position.x += leftBorder(element);
					position.y += topBorder(element);
				}
				var parent = element.parentNode;
				if (parent && styleString(parent, 'overflow') != 'visible'){
					position.x += leftBorder(parent);
					position.y += topBorder(parent);
				}
			} else if (element != this && Browser.Engine.webkit){
				position.x += fix_leftBorder(element);
				position.y += fix_topBorder(element);
			}

			element = element.offsetParent;
		}
		if (Browser.Engine.gecko && !borderBox(this)){
			position.x -= fix_leftBorder(this);
			position.y -= fix_topBorder(this);
		}
		return position;
	},

	getPositionIeFixed: function(relative){
		var offset = this.getOffsetsIeFixed(), scroll = this.getScrolls();
		var position = {x: offset.x - scroll.x, y: offset.y - scroll.y};
		var relativePosition = (relative && (relative = $(relative))) ? relative.getPositionIeFixed() : {x: 0, y: 0};
		return {x: position.x - relativePosition.x, y: position.y - relativePosition.y};
	},

	getCoordinatesIeFixed: function(element){
		if (!Browser.Engine.trident)
			return this.getCoordinates(element);
		
		var position = this.getPositionIeFixed(element), size = this.getSize();
		var obj = {left: position.x, top: position.y, width: size.x, height: size.y};
		obj.right = obj.left + obj.width;
		obj.bottom = obj.top + obj.height;
		return obj;
	},
	
	getOffsetParentIeFixed: function(){
		if (!Browser.Engine.trident)
			return this.getOffsetParent();
		
		var element = this;
		if (fix_isBody(element)) return null; 
		if (!Browser.Engine.trident) return element.offsetParent;
		while ((element = element.parentNode) && !fix_isBody(element)){ 
			if (fix_styleString(element, 'position') != 'static') return element;
		} 
		return null;
	}
});

var fix_styleString = Element.getComputedStyle;

function fix_isBody(element){
	return (/^(?:body|html)$/i).test(element.tagName);
};

function fix_styleNumber(element, style){
	return fix_styleString(element, style).toInt() || 0;
};

function fix_borderBox(element){
	return fix_styleString(element, '-moz-box-sizing') == 'border-box';
};

function fix_topBorder(element){
	return fix_styleNumber(element, 'border-top-width');
};

function fix_leftBorder(element){
	return fix_styleNumber(element, 'border-left-width');
};

function fix_isBody(element){
	return (/^(?:body|html)$/i).test(element.tagName);
};

function fix_getCompatElement(element){
	var doc = element.getDocument();
	return (!doc.compatMode || doc.compatMode == 'CSS1Compat') ? doc.html : doc.body;
};

Element.Events.keyescape = {
  base: 'keyup',
  condition: function(e) {
    return e.key == 'esc';
  }
};

if (MooTools.version < "1.3")
{
	Fx.implement({
		step: function(){
			if (!this.options.transition)
			{
				this.parent();
			}
			else
			{
				var time = $time();
				if (time < this.time + this.options.duration){
					var delta = this.options.transition((time - this.time) / this.options.duration);
					this.set(this.compute(this.from, this.to, delta));
				} else {
					this.set(this.compute(this.from, this.to, 1));
					this.complete();
				}
				this.fireEvent('step', this.subject);
			}
		}
	});
}

LockManager = new Class({
	locks: false,
	
    initialize: function(name){
        this.locks = new Hash();
    },

	set: function(name)
	{
		this.locks.set(name, 1);
	},

	get: function(name)
	{
		return this.locks.has(name);
	},

	remove: function(name)
	{
		this.locks.erase(name);
	}
});

lockManager = new LockManager();


/*
 * Manage select boxes for IE
 */

function hideSelects()
{
	if (Browser.Engine.trident && Browser.Engine.version <= 4)
	{
		$(document).getElements('select').each(function(element){
			element.addClass('invisible');
		});
	}
}

function showSelects()
{
	if (Browser.Engine.trident && Browser.Engine.version <= 4)
		$(document).getElements('select').each(function(element){
			element.removeClass('invisible');
		});
}

/*
 * Some string fixes
 */

String.implement({
	phprStripScripts: function(option){
		var scripts = '';

		var text = this.replace(/<script[^>]*>([^\b]*?)<\/script>/gi, function(){
			scripts += arguments[1] + '\n';
			return '';
		});

		if (option === true) $exec(scripts);
		else if ($type(option) == 'function') option(scripts, text);
		return text;
	},
	
	htmlEscape: function(){
		var value = this.replace("<", "&lt;");
		value = value.replace(">", "&gt;");
		return value.replace('"', "&quot;");
	}
});

/*
 * Save trigger function
 */

function phprTriggerSave()
{
	window.fireEvent('phprformsave');
}

/*
 * Tab managers
 */
window.TabManagers = [];

var TabManagerBase = new Class({
	Implements: [Options, Events],
	
	tabs: [],
	pages: [],
	tabs_element: null,
	current_page: null,
	
	options: {
		trackTab: true
	},
	
	initialize: function(tabs, pages, options){
		this.setOptions(options);
		
		this.tabs_element = $(tabs);

		$(tabs).getChildren().each(function(tab){
			this.tabs.push(tab);
			tab.addEvent('click', this.onTabClick.bindWithEvent(this, tab));
		}, this);

		$(pages).getChildren().each(function(page){
			this.pages.push(page);
		}, this);
		
		window.TabManagers.push(this);
		window.fireEvent('onTabManagerAdded', this);

		var tabClicked = false;
		if (document.location.hash && this.options.trackTab)
		{
			var hashValue = document.location.hash.substring(1);

			this.pages.some(function(item, index){
				if (item.id == hashValue)
				{
					this.onTabClick(null, this.tabs[index]);
					tabClicked = true;
				}

			}, this);
		}
		
		if (this.tabs.length && !tabClicked)
			this.onTabClick(null, this.tabs[0]);
	},
	
	onTabClick: function(e, tab)
	{
		if (e && !this.options.trackTab)
			e.stop();
			
		var tabIndex = this.tabs.indexOf(tab);
		if ( tabIndex == -1 )
			return;

		this.tabClick(tab, this.pages[tabIndex], tabIndex);
		this.fireEvent('onTabClick', [this.tabs[tabIndex], this.pages[tabIndex]]);
		this.pages[tabIndex].fireEvent('onTabClick');
		this.tabs[tabIndex].fireEvent('onTabClick');
		this.current_page = this.pages[tabIndex];

		realignPopups();
		return false;
	}, 
	
	tabClick: function(tab, page, tabIndex)
	{
		return null;
	},
	
	findElement: function(elementId)
	{
		for (var i = 0; i < this.pages.length; i++)
		{
			var el = this.pages[i].getElement('#'+elementId);
			if (el)
			{
				this.onTabClick(null, this.tabs[i]);
				return true;
			}
		}
		
		return false;
	}
});

Element.implement({
	getTab: function()
	{
		var CurrentParent = this;
		while (CurrentParent != null && CurrentParent != document)
		{
			CurrentParent = $(CurrentParent);
			
			if (CurrentParent.get('tag') == 'li' && CurrentParent.parentNode !== null && $(CurrentParent.parentNode).hasClass('tabs_pages'))
				return $(CurrentParent);

			CurrentParent = CurrentParent.parentNode;
		}

		return null;
	}
});

window.phprErrorField = null;

/*
 * Edit area functions
 */

var phpr_field_initialized = new Hash();
var phpr_field_loaded = new Hash();
var phpr_active_code_editor = null;
var phpr_code_editors = [];

function init_code_editor(field_id, language, options)
{
	return new AceWrapper(field_id, language, options);
}

function find_code_editor(field_id)
{
	var result = phpr_code_editors.filter(function(obj){
		if (obj.id == field_id)
			return obj;
	});
	
	if (result.length)
		return result[0].editor;
		
	return null;
}

/*
 * Search control
 */

var SearchControlHandler = new Class({
	Implements: [Options, Events],

	search_element: null,
	input_element: null,
	cancel_element: null,
	
	options: {
		default_text: 'search'
	},

	initialize: function(search_element, options){
		this.setOptions(options);
		this.search_element = $(search_element);
		this.input_element = this.search_element.getElement('input');
		this.cancel_element = this.search_element.getElement('span.right');

		this.input_element.addEvent('click', this.onFieldClick.bind(this));
		this.cancel_element.addEvent('click', this.onCancelClick.bind(this));
		this.input_element.addEvent('keydown', this.onFieldKeyDown.bind(this));
	},

	onFieldClick: function()
	{
		if (this.search_element.hasClass('inactive'))
		{
			this.search_element.removeClass('inactive');
			this.input_element.set('value', '');
		}
	},
	
	onFieldKeyDown: function(event)
	{
		if (event.key == 'enter')
		{
			if (!this.input_element.value.trim().length)
				this.forceCancel(event);
			else
				this.fireEvent('send');
		}
		else 
			if (event.key == 'esc')
				this.forceCancel(event);
	},
	
	forceCancel: function(event)
	{
		this.onCancelClick();
		this.input_element.set('value', this.options.default_text);
		this.input_element.blur();
		event.stop();
	},
	
	onCancelClick: function()
	{
		if (this.search_element.hasClass('inactive'))
			return;
		
		this.search_element.addClass('inactive');
		this.input_element.set('value', this.options.default_text);
		this.fireEvent('cancel');
	}
});

/*
 * Collapsabele form areas
 */

function phpr_update_collapsable_status(trigger)
{
	var parent = $(trigger).selectParent('div.form-collapsable-area');
	if (parent.hasClass('collapsed')) 
	{
		parent.removeClass('collapsed');
		$(trigger).set('title', 'Hide');
		backend_focus_first(parent);
	} 
	else {
		parent.addClass('collapsed');
		$(trigger).set('title', 'Show');
	}

	window.fireEvent('phpr_form_collapsable_updated');
	jQuery(window).trigger('phpr_layout_updated');
}


/*
 * MooTools 1.1 sortables
 */

var Sortables11 = new Class({
	is_in_popup: false,

	options: {
		handles: false,
		onStart: Class.empty,
		onComplete: Class.empty,
		ghost: true,
		snap: 3,
		startDelay: 0,
		onDragStart: function(element, ghost){
			ghost.setStyle('opacity', 0.7);
			element.setStyle('opacity', 0.7);
		},
		onDragComplete: function(element, ghost){
			element.setStyle('opacity', 1);
			ghost.destroy();
			this.trash.destroy();
		}
	},

	initialize: function(list, options){
		this.setOptions(options);
		this.list = $(list);
		this.elements = this.list.getChildren();
		this.handles = (this.options.handles) ? $$(this.options.handles) : this.elements;
		this.bound = {
			'start': [],
			'moveGhost': this.moveGhost.bindWithEvent(this)
		};
		for (var i = 0, l = this.handles.length; i < l; i++){
			this.bound.start[i] = this.start.bindWithEvent(this, this.elements[i]);
		}
		this.attach();
		if (this.options.initialize) this.options.initialize.call(this);
		this.bound.move = this.move.bindWithEvent(this);
		this.bound.end = this.end.bind(this);
		this.is_in_popup = this.list.selectParent('.popupForm');
	},

	attach: function(){
		this.handles.each(function(handle, i){
			handle.addEvent('mousedown', this.bound.start[i]);
		}, this);
	},

	detach: function(){
		this.handles.each(function(handle, i){
			handle.removeEvent('mousedown', this.bound.start[i]);
		}, this);
	},
	
	start_delayed: function(event, el)
	{
		this.active = el;
		this.coordinates = this.list.getCoordinates();
		if (this.options.ghost){
			var position = el.getPosition();
			this.offset = event.page.y - position.y;
			this.trash = new Element('div').inject(document.body);
			this.ghost = el.clone().inject(this.trash).setStyles({
				'position': 'absolute',
				'left': position.x,
				'top': event.page.y - this.offset
			});
			document.addEvent('mousemove', this.bound.moveGhost);
			this.fireEvent('onDragStart', [el, this.ghost]);
		}
		document.addEvent('mousemove', this.bound.move);
		document.addEvent('mouseup', this.bound.end);
		this.fireEvent('onStart', el);
		event.stop();
	},

	start: function(event, el){
		if (!this.options.startDelay)
			this.start_delayed(event, el);
		else
		{
			var timer_d = this.start_delayed.delay(100, this, [event, el]);
			document.addEvent('mouseup', function(){
				$clear(timer_d);
			});
		}
	},

	moveGhost: function(event){
		var value = event.page.y - this.offset;
		value = value.limit(this.coordinates.top, this.coordinates.bottom - this.ghost.offsetHeight);
		this.ghost.setStyle('top', value);
		event.stop();
	},

	move: function(event){
		this.active.active = true;
		this.previous = this.previous || event.page.y;
		this.now = event.page.y;
		var direction = ((this.previous - this.now) <= 0) ? 'down' : 'up';
		var prev = this.active.getPrevious();
		var next = this.active.getNext();
		
		var scroll_tweak = this.is_in_popup ? window.getScroll().y : 0;

		var scroll = window.getScroll();
		if (prev && direction == 'up'){
			var prevPos = prev.getCoordinates();
			if (event.page.y < (prevPos.bottom + scroll_tweak)) this.active.injectBefore(prev);
		}
		if (next && direction == 'down'){
			var nextPos = next.getCoordinates();
			if (event.page.y > (nextPos.top + scroll_tweak)) this.active.injectAfter(next);
		}
		this.previous = event.page.y;
	},

	serialize: function(){
		var serial = [];
		this.list.getChildren().each(function(el, i){
			serial[i] = this.elements.indexOf(el);
		}, this);
		return serial;
	},

	end: function(){
		this.previous = null;
		document.removeEvent('mousemove', this.bound.move);
		document.removeEvent('mouseup', this.bound.end);
		if (this.options.ghost){
			document.removeEvent('mousemove', this.bound.moveGhost);
			this.fireEvent('onDragComplete', [this.active, this.ghost]);
		}
		this.fireEvent('onComplete', this.active);
	}

});

Sortables11.implement(new Events, new Options);

/*
 * Sortable lists
 */

Element.implement({
	makeListSortable: function(server_handler, sort_order_class, item_id_class, item_handle_class, extra_fields)
	{
		var sortable_list = this;
		var sortable_list_orders = [];
		
		var order_class = $type(sort_order_class) == false ? 'SortOrder' : sort_order_class;
		var id_class = $type(item_id_class) == false ? 'ItemId' : item_id_class;
		var handle_class = $type(item_handle_class) == false ? 'VerticalMove' : item_handle_class;
		
		$(this).getElements('input.'+order_class, $(this)).each(function(element){
			sortable_list_orders.push(element.value);
		}, this);
		
		extra_fields = extra_fields || {};

		new Sortables11($(this), {
			handles: $(this).getElements('.'+handle_class),
			onDragStart: function(element, ghost){
				ghost.destroy();
				element.addClass('drag');
			},
			onDragComplete: function(element, ghost){
				element.removeClass('drag');
				this.trash.destroy();
				sortable_list.postListItemsOrder(server_handler, sortable_list_orders, id_class, extra_fields);
				element.getParent().fireEvent('dragComplete', [sortable_list_orders]);
			}
		});
	},
	
	postListItemsOrder: function(server_handler, sortable_list_orders, id_class, extra_fields)
	{
		var ids = [];
		$(this).getElements('input.'+id_class).each(function(element){
			ids.push(element.value);
		});
		
		var post_fields = $merge({
			item_ids: ids.join(','), 
			sort_orders: sortable_list_orders.join(',')},
			extra_fields
		);
		
		$(this).getForm().sendPhpr(server_handler, 
			{
				extraFields: post_fields,
				loadIndicator: {show: false},
				update: 'multi',
				onResult: function(param1, param2){
					this.fireEvent('sortableServerResponse', [param2]);
				}.bind(this)
			}
		);

		return false;
	}
});