/*
 * Engine configuration
 */

var core_module_build = 0;

/*
 * Preload load indicator images
 */

new Asset.image(ls_root_url('phproad/resources/images/form_load_70x70.gif'));
new Asset.image(ls_root_url('phproad/resources/images/form_load_50x50.gif'));
new Asset.image(ls_root_url('phproad/resources/images/form_load_30x30.gif'));
new Asset.image(ls_root_url('phproad/resources/images/form_load_40x40.gif'));
new Asset.image(ls_root_url('phproad/resources/images/form_load_100x100.gif'));
new Asset.image(ls_root_url('/modules/backend/resources/images/loading_global.gif'));

new Asset.image(ls_root_url('/phproad/resources/images/tree_expand.gif'));
new Asset.image(ls_root_url('/phproad/resources/images/tree_collapse.gif'));
new Asset.image(ls_root_url('/phproad/resources/images/tree_no_expand.gif'));

new Asset.image(ls_root_url('/phproad/modules/db/behaviors/db_formbehavior/resources/images/onoff_on.gif'));
new Asset.image(ls_root_url('/phproad/modules/db/behaviors/db_formbehavior/resources/images/onoff_off.gif'));

/*
 * Define backend-wide load indicator and AJAX request defaults
 */

Element.implement({
	getLoadingIndicatorDefaults: function()
	{
		return {
			overlayClass: 'formOverlay',
			pos_x: 'center',
			pos_y: 'center',
			src: ls_root_url('/phproad/resources/images/form_load_70x70.gif'),
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
			onSuccess: this.hideError.bind(this),
			errorHighlight: {
				backgroundFromColor: '#f00',
				backgroundToColor: '#ffffcc'
			},
			onAfterError: this.highlightFormError.bind(this),
			hideErrorOnSuccess: true
		};
	},
	
	hideError: function()
	{
		if (!this.options.hideErrorOnSuccess)
			return;

		if (!this.options.loadIndicator.hideOnSuccess)
			return;

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
		
		var parent_form = element.selectParent('form');
		if (parent_form)
		{
			$(parent_form).getElements('ul.formElements li.field').each(function(el){
				el.removeClass('error');
			});
		}
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
 * Tabs classes
 */

var TabManager = new Class({
    Extends: TabManagerBase,

	tabClick: function(tab, page, tabIndex)
	{
		this.tabs.each(function(tabElement){tabElement.removeClass('active')});
		tab.addClass('active');

		for (var i = 0; i < this.pages.length; i++)
		{
			if ( i != tabIndex )
				this.pages[i].hide();
		}

		this.pages[tabIndex].show();
		backend_focus_first(this.pages[tabIndex]);
		jQuery(window).trigger('onTabClick');
	}
});

/*
 * Date and date range picker
 */

var DateRangePicker = new Class({
	Implements: [Options, Events],
	Binds: ['cancel', 'show'],
	options: {
		type: 'single',
		displayTrigger: null,
		displayElement: null,
		typeDisplayElement: null,
		typeHiddenElement: null,
		rangesHiddenElement: null,
		inputs: [],
		intYears: [],
		intMonths: []
	},
	
	activeType: null,
	types: null,

	initialize: function(options)
	{
		this.setOptions(options);

		if (this.options.type == 'single')
			this.build();
		else
		{
			window.addEvent('domready', this.build.bind(this));
		
			this.types = {
				day: {name: 'Day'},
				month: {name: 'Month', range: this.options.intMonths}, 
				year: {name: 'Year', range: this.options.intYears}
			};
		}
		
		if (!this.options.displayTrigger)
		{
			if (this.options.type == 'single')
				$(this.options.inputs[0]).addEvent('click', this.show);
		} else
			$(this.options.displayTrigger).addEvent('click', this.show);
	},

	cancel: function()
	{
	    $(document).removeEvent('click', this.cancel);
	    $(document).removeEvent('keyescape', this.cancel);
	
		if (!Browser.Engine.trident)
			this.slideFx.slideOut().chain(this.hideContainer.bind(this));
		else
		{
			this.slideFx.hide();
			this.hideContainer();
		}

		window.fireEvent('datePickerHide');
		
		if (this.options.type == 'single')
			showSelects();
	},

	hideContainer: function()
	{
		this.container.setStyle('visibility', 'hidden');
	},
	
	build: function()
	{
		this.container = new Element('div', {
			'class': 'datePickerWrapper'
		}).inject(this.options.inputs[0], 'after');

		this.slide = new Element('div', {
			'class': 'datePickerSliderConainer '+this.options.type
		}).inject(this.container, 'top');

		this.pickerPanel = new Element('div', {'class': 'picker_panel day_panel'}).inject(this.slide, 'top');

		this.slideFx = new Fx.Slide(this.slide, {'duration': 250});
		this.slideFx.hide();
		
		var pickerOptions = {
			flat: true,
			starts: 1 
		};
		
		pickerOptions = $merge(pickerOptions, this.options);
		
		if (this.options.type == 'single')
			pickerOptions.onDayClick = this.onSetDate.bind(this);
		else
		{
			pickerOptions.calendars = 2;
			pickerOptions.mode = 'range';
		}
		
		this.picker = jQuery(this.pickerPanel).DatePicker(pickerOptions);
		
		/*
		 * Inject OK button to the control
		 */
		
		if (this.options.type != 'single')
		{
			this.typeSelectorPanel = new Element('div', {'class': 'type_selector'}).inject(this.slide, 'top');
			this.typeSelectorPanel.innerHTML = '<h5>Interval</h5>';
		
			var ranges = this.parseRanges();
		
			for (var key in this.types){
				if (key == 'day' || this.types[key].range.length)
				{
					var p = new Element('p').inject(this.typeSelectorPanel, 'bottom');
					var a = new Element('a', {'class': key, 'href': '#'}).inject(p, 'bottom');
					
					a.innerHTML = this.types[key].name;
					
					if (key != 'day')
					{
						var rangeStart = ranges ? ranges[key][0] : null;
						var rangeEnd = ranges ? ranges[key][1] : null;
					
						var panel = new Element('div', {'class': 'picker_panel hidden '+key+'_panel'}).inject(this.slide, 'bottom');
						var from_label = new Element('label').inject(panel, 'bottom');
						from_label.innerHTML = this.types[key].name+', from';
						
						var selectStart = new Element('select', {'class': 'start no-styling'}).inject(panel, 'bottom');
						this.popupateRange(selectStart, this.types[key].range, 1, rangeStart);
						
						var to_label = new Element('label', {'class': 'to'}).inject(panel, 'bottom');
						to_label.innerHTML = 'to';
						
						var selectEnd = new Element('select', {'class': 'end no-styling'}).inject(panel, 'bottom');
						this.popupateRange(selectEnd, this.types[key].range, 2, rangeEnd);
					}
					
					a.addEvent('click', this.switchIntervalType.bind(this, [key]));
				}
			} 
			
			new Element('div', {'class': 'clear'}).inject(this.slide, 'bottom');
			this.controlPanel = new Element('div', {'class': 'control_panel'}).inject(this.slide, 'bottom');
					
			this.cancelBtn = new Element('a', {
				'class': 'calendar_button last',
				'href': '#'
			}).inject(this.controlPanel, 'bottom');
			
			this.cancelBtn.innerHTML = 'Cancel';
					
			this.cancelBtn.addEvent('click', function(){this.cancel(); return false;}.bind(this));
					
			this.okBtn = new Element('a', {
				'class': 'calendar_button',
				'href': '#'
			}).inject(this.controlPanel, 'bottom');
			this.okBtn.innerHTML = 'OK';
					
			this.okBtn.addEvent('click', this.setDateRange.bind(this));
			
			new Element('div', {'class': 'clear'}).inject(this.controlPanel, 'bottom');
		}
		var containerCoords = this.container.getCoordinates();
		if (containerCoords.left < 0)
			this.container.setStyle('right', containerCoords.left);
		
		this.container.setStyle('visibility', 'hidden');
	},
	
	parseRanges: function()
	{
		if (!this.options.rangesHiddenElement)
			return false;
			
		var value = $(this.options.rangesHiddenElement).value;
		if (!value.length)
			return false;
			
		var result = {};
			
		value.split(',').each(function(typeRange){
			if (typeRange.length)
			{
				var typePairs = typeRange.split(':');
				var rangeDates = typePairs[1].split('-');

				var key = typePairs[0];
				result[key] = [rangeDates[0], rangeDates[1]];
			}
		});
		
		return result;
	},
	
	switchIntervalType: function(key)
	{
		this.slide.getElements('.picker_panel').each(function(element){element.hide()});
		this.slide.getElement('.'+key+'_panel').show();
		
		this.typeSelectorPanel.getElements('a').each(function(element){element.removeClass('current')});
		this.typeSelectorPanel.getElement('a.'+key).addClass('current');
		this.activeType = key;
		
		return false;
	},
	
	onSetDate: function(formated, dates)
	{
		$(this.options.inputs[0]).value = formated;
		this.cancel();
	},
	
	popupateRange: function(element, range, type, value)
	{
		range.each(function(range_element){
			var opt = new Element('option', {'value': range_element[type]}).inject(element, 'bottom');
			opt.innerHTML = range_element[0];
		});
		
		if (value)
		{
			$A(element.options).some(function(option){
				if (option.value == value)
				{
					option.selected = true;
					return true;
				}
				
				return false;
			});
		}
	},
	
	setDateRange: function()
	{
		var startDate = null;
		var endDate = null;

		if (this.activeType != 'day')
		{
			var panel = this.getActivePanel();
			var startIndex = this.getOptionIndex(panel, 'start');
			var endIndex = this.getOptionIndex(panel, 'end');
			
			if (startIndex > endIndex)
			{
				alert('Interval start date must be less or equal the interval end date.');
				return false;
			}

			startDate = this.getOptionValue(panel, 'start');
			endDate = this.getOptionValue(panel, 'end');
		}

		if (this.options.displayElement)
		{
			if (this.activeType == 'day')
			{
				var d1_text = this.picker.DatePickerGetDate(true)[0];
				var d2_text = this.picker.DatePickerGetDate(true)[1];
			
				var display_text = (d1_text == d2_text) ? d1_text : d1_text + ' - ' + d2_text;
				this.options.displayElement.innerHTML = display_text;
			
				if (this.options.typeDisplayElement)
				{
					var display_type = (d1_text == d2_text) ? 'Date' : 'Interval';
					this.options.typeDisplayElement.innerHTML = display_type;
				}
			} else {
				var display_text = startDate + ' - ' + endDate;
				this.options.displayElement.innerHTML = display_text;
				this.options.typeDisplayElement.innerHTML = 'Interval';
			}

			$(this.options.typeHiddenElement).value = this.activeType;
		}

		if (this.options.rangesHiddenElement)
		{
			var ranges_value = '';

			for (var key in this.types)
			{
				if (key != 'day' && this.types[key].range.length)
				{
					var panel = this.slide.getElement('.'+key+'_panel');
					var start = this.getOptionValue(panel, 'start');
					var end = this.getOptionValue(panel, 'end');

					ranges_value += key + ':' + start + '-' + end + ',';
				}
			}

			$(this.options.rangesHiddenElement).set('value', ranges_value);
		}
		
		if (this.activeType == 'day')
		{
			startDate = this.picker.DatePickerGetDate(true)[0];
			endDate = this.picker.DatePickerGetDate(true)[1];
		}
		
		this.options.inputs[0].set('value', startDate);
		this.options.inputs[1].set('value', endDate);
		
		this.fireEvent('setRange', [startDate, endDate]);
		this.cancel();
		return false;
	},
	
	getActivePanel: function()
	{
		return this.slide.getElement('.'+this.activeType+'_panel');
	},
	
	getOptionIndex: function(panel, controlClass)
	{
		var select = panel.getElement('select.'+controlClass)

		var indexFound = -1;
		$A(select.options).some(function(item, index){
			indexFound = index;
		    return item.selected ? true : false; 
		});

		return indexFound;
	},
	
	getOptionValue: function(panel, controlClass)
	{
		var select = panel.getElement('select.'+controlClass)
		return select.get('value');
	},

	show: function()
	{
		if (this.options.type == 'single')
			hideSelects();

		if (this.options.type == 'single')
			this.picker.DatePickerSetDate($(this.options.inputs[0]).get('value'), true);
		else
		{
			this.activeType = $(this.options.typeHiddenElement).value;
			
			var dates = [
				$(this.options.inputs[0]).value,
				$(this.options.inputs[1]).value
			];

			this.picker.DatePickerSetDate(dates, true);
			this.switchIntervalType(this.activeType);
		}

		this.container.setStyle('visibility', 'visible');
		
		if (!Browser.Engine.trident)
			this.slideFx.slideIn().chain(this.attachEvents.bind(this));
		else
		{
			this.slideFx.show();
			this.attachEvents();
		}

		window.fireEvent('datePickerDisplay');

		return false;
	},
	
	attachEvents: function()
	{
		if (this.options.type == 'single')
			$(document).addEvent('click', this.cancel);
		
		$(document).addEvent('keyescape', this.cancel);
	}
});

/*
 * Button menus
 */
window.slide_menus = [];
var modules_menuFx = null;
var settings_menuFx = null;

function backend_hide_slide_menus(ignore)
{
	if ($('module_tabs_wrapper'))
		$('module_tabs_wrapper').getFirst().removeClass('active-wrapper');

	document.body.removeClass('main-menu-visible');
	window.slide_menus.each(function(fx){
		if (!fx || fx != ignore)
			fx.slideOut();
	});
}

var ButtonMenu = new Class({
	menuFx: null,
	menu_element: null,
	wrapper: null,
	
	initialize: function(menu_element)
	{
		window.addEvent('domready', this.initMenu.pass($(menu_element), this));
	},
	
	initMenu: function(menu_element)
	{
		this.menu_element = menu_element;
		this.wrapper = menu_element.getElement('.wrapper');
		var size = menu_element.getSize();
		var menuFx = new Fx.Slide(menu_element.getElement('ul'), {'duration': 150, 'wrapper': this.wrapper}).hide();
		
		menu_element.getElement('ul').getElements('li').each(function(item){
			item.setStyle('width', size.x + 'px')
		})
		
		menuFx.addEvent('complete', this.slideComplete.bind(this));
		
		$(menu_element.getElement('a.trigger')).addEvent('click', function(){
			menuFx.toggle();

			backend_hide_slide_menus(menuFx);
			return false;
		});
		
		this.menuFx = menuFx;
		window.slide_menus.push(this.menuFx)
	},
	
	slideComplete: function()
	{
		if (this.wrapper.getSize().y > 0)
			this.menu_element.addClass('menu_visible');
		else
			this.menu_element.removeClass('menu_visible');
	}
});

/*
 * Light loading indicator
 */

var LightLoadingIndicator = new Class({
	active_request_num: 0,
	loading_indicator_element: null,
	Binds: ['show', 'hide'],

	show: function(message)
	{
		this.active_request_num++;
		this.create_loading_indicator(message);
	},
	
	hide: function()
	{
		this.active_request_num--;
		if (this.active_request_num == 0)
			this.remove_loading_indicator();
	},
	
	create_loading_indicator: function(message)
	{
		if (this.loading_indicator_element)
			return;

		this.loading_indicator_element = new Element('p', {'class': 'light_loading_indicator'}).inject(document.body, 'top');
		this.loading_indicator_element.innerHTML = '<span>'+message+'</span>';
	},
	
	remove_loading_indicator: function()
	{
		if (this.loading_indicator_element)
			this.loading_indicator_element.destroy();
			
		this.loading_indicator_element = null;
	}
});

LightLoadingIndicator = new LightLoadingIndicator();

/*
 * Navigation initialization
 */

window.addEvent('domready', function(){
	if ($('module_tabs_wrapper'))
	{
		modules_menuFx = new Fx.Slide('module_tabs', {
			'duration': 'short', 
			'mode': 'vertical', 
			'wrapper': $('module_tabs_wrapper'), 
			onComplete: backend_menu_complete}
		).hide();

		slide_menus.push(modules_menuFx);

		$('menu_trigger').addEvent('click', function(){
			$('main_menu_connector').setStyle('width', ($('menu_trigger').getSize().x - 1) + 'px');

			if (modules_menuFx.wrapper['offset' + modules_menuFx.layout.capitalize()] == 0)
				$('menu_trigger').addClass('active');

			if (top_menu_in) {
				$('module_tabs_wrapper').getFirst().removeClass('active-wrapper');
				document.body.removeClass('main-menu-visible');
			}

			modules_menuFx.toggle(); 
			backend_hide_slide_menus(modules_menuFx); 
			return false;
		});

		$('module_tabs_wrapper').removeClass('invisible');

		$('header_tabs').getElements('li div.submenu_wrapper').each(function(submenu_wrapper){
			submenu_wrapper.removeClass('invisible');
			var submenu = new Fx.Slide(submenu_wrapper.getElement('ul'), {
				'duration': 'short', 
				'mode': 'vertical', 
				'wrapper': $(submenu_wrapper)}
			).hide();

			window.slide_menus.push(submenu);
			var el = submenu_wrapper.getParent().getElement('a');
			$(el).addEvent('click', function(){ 
				backend_hide_slide_menus(submenu);
				submenu.toggle();
				return false;
			});
		});
	}

	$(document).addEvent('keydown', function(event) {
		if(event.key == 'esc') {
			backend_hide_slide_menus();
			event.stop();
		}
	});
	$(document).addEvent('click', backend_hide_slide_menus);

	if ($('user_menu_connector'))
		$('user_menu_connector').setStyle('width', ($('settings_link_container').getSize().x-2) + 'px');

	if ($('user_controls_wrapper'))
	{
		settings_menuFx = new Fx.Slide('user_controls', {'duration': 'short', 'mode': 'vertical', onComplete: backend_menu_complete, 'wrapper': $('user_controls_wrapper')}).hide();
		var 
			settings_link = $('settings_link'),
			user_controls_wrapper = $('user_controls_wrapper');

		user_controls_wrapper.removeClass('invisible');

		slide_menus.push(settings_menuFx);
		settings_link.addEvent('click', function(){
			if (settings_menuFx.wrapper['offset' + settings_menuFx.layout.capitalize()] == 0)
			{
				user_controls_wrapper.addClass('active');
				settings_link.addClass('active');
			}

			backend_hide_slide_menus(settings_menuFx);
			settings_menuFx.toggle();
			return false;
		});
	}
});

var top_menu_in = true;

function backend_menu_complete()
{
	if (settings_menuFx.wrapper['offset' + settings_menuFx.layout.capitalize()] == 0)
	{
		$('user_controls_wrapper').removeClass('active');
		$('settings_link').removeClass('active');
	}
	
	if (modules_menuFx && (modules_menuFx.wrapper['offset' + modules_menuFx.layout.capitalize()] == 0))
	{
		$('menu_trigger').removeClass('active');
		
		if (!top_menu_in)
		{
			top_menu_in = true;
			window.fireEvent('topMenuHide');
		}
	}
	else
	{
		if (top_menu_in)
		{
			top_menu_in = false;
			window.fireEvent('topMenuShow');
			if ($('module_tabs_wrapper'))
				$('module_tabs_wrapper').getFirst().addClass('active-wrapper');
			document.body.addClass('main-menu-visible');
		}
	}
}

/*
 * Input change tracker
 */

var InputChangeTracker = new Class({
	Implements: [Options, Events],
	
	options: {
		regexp_mask: '^*$',
		interval: 300
	},
	
	element: null,
	prev_value: null,
	timer_obj: null,
	regexp_obj: null,

	initialize: function(element, options)
	{
		this.setOptions(options);
		this.element = element;
		this.element.addEvent('keydown', this.onElementChange.bind(this));
		this.element.addEvent('keyup', this.onElementChange.bind(this));
		this.element.addEvent('keypress', this.onElementChange.bind(this));
		this.prev_value = element.value;
		this.regexp_obj = new RegExp(this.options.regexp_mask);
	},
	
	onElementChange: function(event)
	{
		if (this.prev_value == this.element.value.trim())
			return;

		this.prev_value = this.element.value.trim();
		$clear(this.timer_obj);

		if (this.regexp_obj.test(this.prev_value))
			this.timer_obj = this.fireChangeEvent.delay(this.options.interval, this);
		else
			this.fireEvent('invalid', this.element);
	}, 
	
	fireChangeEvent: function()
	{
		if (!this.regexp_obj.test(this.prev_value))
			return;

		this.fireEvent('change', [this.element]);
	}
});

/*
 * LemonStand Scroller controls
 */

var BackendVScroller = new Class({
	Implements: [Options, Events],
	element: null,
	knob: null,
	element_fx_scroll: null,
	slider_fx: null,
	slider: null,
	bound_mouse_up: null,
	
	options: {
		slider_height_tweak: 0,
		auto_hide_slider: false,
		position_threshold: 8
	},
	
	initialize: function(element, options)
	{
		this.setOptions(options);
		this.element = $(element);
		this.element_fx_scroll = new Fx.Scroll(this.element);

		/*
		 * Build the slider
		 */
		var element_size = this.element.getSize();
		
		var scroll_container = new Element('div', {'class': 'backend_scroller_container'}).inject(element, 'before');
		this.element.inject(scroll_container);
		
		var element_scroll_size = this.element.getScrollSize();
		this.slider = new Element('div', {'class': 'v_slider'}).inject(scroll_container);
		this.slider.setStyle('height', (element_size.y + this.options.slider_height_tweak) + 'px');

		var effective_scroll_size = element_scroll_size.y - element_size.y;
		var knob = new Element('div', {'class': 'knob'}).inject(this.slider);
		if (effective_scroll_size <= 0)
		{
			knob.addClass('invisible');
			this.element.removeClass('scroll_enabled');
			if (this.options.auto_hide_slider)
				this.slider.addClass('invisible');
		} else
			this.element.addClass('scroll_enabled');
			
		this.bound_mouse_up = this.mouse_release.bind(this);
		
		this.knob = knob;
		this.knob.addEvent('mousedown', function(){
			this.slider.addClass('active');
			window.addEvent('mouseup', this.bound_mouse_up);
			this.bound_mouse_up
		}.bind(this));
		
		this.init_slider_fx();
	},
	
	init_slider_fx: function()
	{
		this.slider_fx = new Slider(this.slider, this.knob, {
			steps: 100,
			range: [0, 100],
			mode: 'vertical',
			wheel: true,
			onChange: function(step){
				var effective_scroll_size = this.element.getScrollSize().y - this.element.getSize().y;
				this.element_fx_scroll.set(0, Math.ceil(effective_scroll_size*step/100));
			}.bind(this)
		});
		
		this.element.addEvent('mousewheel', this.mouse_scroll.bindWithEvent(this));
	},
	
	mouse_release: function()
	{
		this.slider.removeClass('active');
		window.removeEvent('mouseup', this.bound_mouse_up);
	},
	
	mouse_scroll: function(event)
	{
		var mode = (this.slider_fx.options.mode == 'horizontal') ? (event.wheel < 0) : (event.wheel > 0);
		
		var step_size = Math.round(30*100/this.element.getScrollSize().y);

		this.slider_fx.set(mode ? this.slider_fx.step - step_size : this.slider_fx.step + step_size);
		event.stop();
	},
	
	update: function()
	{
		var element_size = this.element.getSize();
		var element_scroll_size = this.element.getScrollSize();
		var effective_scroll_size = element_scroll_size.y - element_size.y;

		if (effective_scroll_size <= 0)
		{
			this.knob.addClass('invisible');
			this.element.removeClass('scroll_enabled');
			this.slider_fx.set(0);
			
			if (this.options.auto_hide_slider)
				this.slider.addClass('invisible');
		}
		else
		{
			this.knob.removeClass('invisible');
			this.element.addClass('scroll_enabled');
			if (this.options.auto_hide_slider)
				this.slider.removeClass('invisible');
		}

		if (this.element.scrollTop + element_size.y > element_scroll_size.y)
		{
			this.slider_fx.set(100);
			this.element_fx_scroll.toBottom();
		} else
		{
			if (this.slider_fx.step == 100 && (element_scroll_size.y > this.element.scrollTop + element_size.y))
			{
				this.slider_fx.set(100);
				this.element_fx_scroll.toBottom();
			}
		}
	},
	
	update_position: function()
	{
		var element_size = this.element.getSize();
		var element_scroll_size = this.element.getScrollSize();
		var max = element_scroll_size.y - element_size.y;

		if (this.element.scrollTop <= this.options.position_threshold)
			this.slider_fx.set(0);
		else if (max - this.element.scrollTop <= this.options.position_threshold)
			this.slider_fx.set(100);
		else
			this.slider_fx.set(Math.floor((this.element.scrollTop)/max*100));
	}
});

/*
 * Table row controls
 */

(function( $, $mt ){
	$.fn.tableRowMenu = function(options) {
		
		return this.each(function() {
			var $this = $(this);  
			var rows = $this.find('tbody tr');
			var visible_menu = null;
			var show_timer = null;

			rows.each(function(index, row){
				$row = $(row);
				$row.bind('click', {target_row: $row}, _handle_click);
				$row.bind('dblclick', {target_row: $row}, _handle_dbl_click);

				$row.children('a').each(function(index, link) {
					$(link).bind('click', {target_row: $row}, _handle_click);
				})
			});
			
			function _suppress_menu(target)
			{
				var $target = $(target);
				
				if ($target.hasClass('no_menu'))
					return true;

				if ($target.length)
				{
					if ($target[0].tagName == 'INPUT')
						return true;
						
					if ($target.children('.no_menu').length)
						return true;
						
					if ($target.parents('div.row_controls').length)
						return true;
				}
				
				return false;
			}
			
			function _handle_click(event) {
				if (visible_menu)
				{
					visible_menu.addClass('hidden');
					visible_menu = null;
				}

				if (_suppress_menu(event.target))
					return true;

				if (show_timer)
					$clear(show_timer);

				show_timer = _show_menu.delay(200, null, [event]);
				
				return false;
			}
			
			function _show_menu(event){
				var row_pos = event.data.target_row.position();

				var menu = event.data.target_row.find('div.row_controls');
				menu.css('left', (event.layerX + 10) + 'px');
				menu.css('top', (event.layerY - 15) + 'px');
				menu.removeClass('hidden');
				visible_menu = menu;
				$(document).bind('click.tableRowMenu', function(){
					if (visible_menu)
					{
						visible_menu.addClass('hidden');
						visible_menu = null;
					}
				});
			}
			
			function _handle_dbl_click(event) {
				if (_suppress_menu(event.target))
					return true;

				if (show_timer)
					$clear(show_timer);

				var $target = $(event.target);

				if ($target[0].tagName == 'A')
					window.location.href = $target.attr('href');
				else if ($target[0].tagName == 'IMG')
					window.location.href = $target.parent().attr('href');
				else 
					window.location.href = $target.children('a').attr('href');

				return false;
			}
		});
	}

	$(document).keydown(function(event){
		if(event.keyCode == 27) {
			jQuery.each($('div.row_controls'), function() {
				if(!this.hasClass('hidden'))
					this.addClass('hidden');
			});
		}
	});
})( jQuery, $ );

/*
 * Initialize tips
 */

window.addEvent('domready', function(){ 
	init_tooltips();
});

function init_tooltips()
{
	(function( $ ){
		if ($.fn.tipsy !== undefined) {
			$('a.tooltip, span.tooltip, li.tooltip').tipsy({
				live: true, 
				delayIn: 800,
				html: true,
				gravity: $.fn.tipsy.autoWE
			});
		}
	})(jQuery);
}

function update_tooltips()
{
	init_tooltips();
}

function hide_tooltips()
{
	(function( $ ){
		$('a.tooltip, span.tooltip').each(function(index, e){
			$(e).tipsy('hide');
		});
	})(jQuery);
}

/*
 * Add concurrency locking support
 */

function backend_ping_lock()
{
	$('form_lock_record_id').getForm().sendPhpr(
		'onPingLock', 
		{
			loadIndicator: {show: false}}
	);
}

window.addEvent('domready', function(){
	if ($('form_lock_record_id'))
		backend_ping_lock.periodical(10000);
});

// window.addEvent('unload', function(){
// 	if ($('form_lock_record_id'))
// 	{
// 		$('form_lock_record_id').getForm().sendPhpr('onReleaseRecordLock', {extraFields: {'lock_id': $('form_lock_record_id').value}});
// 	}
// });

/*
 * Scrollable toolbars
 */

var backend_scrollable_toolbars = [];
var backend_scrollable_toolbar_offsets = [];

var Backend_ScrollabeToolbar = new Class({
	toolbar: null,
	toolbar_scroll_area: null,
	toolbar_scroll_content: null,
	toolbar_scroll_controls: null,
	scrollable_content_width: null,
	extra_element: null,
	scroll_left: null,
	scroll_right: null,
	scroll_button_width: 18,
	scroll_interval_id: 0,
	scroll_offset: 15,
	offset_index: 0,

	Binds: ['scroll_toolbar_left', 'scroll_toolbar_right', 'stop_scrolling', 'resize_toolbar'],

	initialize: function(toolbar)
	{
		this.attach(toolbar);
		backend_scrollable_toolbars.push(this);
		this.offset_index = backend_scrollable_toolbars.length-1;

		var initial_offset = backend_scrollable_toolbar_offsets[this.offset_index] ? backend_scrollable_toolbar_offsets[this.offset_index] : 0;
		this.set_scroll(initial_offset);
	},

	attach: function(toolbar)
	{
		this.toolbar = $(toolbar);
		this.toolbar_scroll_area = this.toolbar.getElement('.scroll_area');
		this.toolbar_scroll_controls = this.toolbar.getElement('.scroll_controls');

		if (this.toolbar.getChildren().length > 1)
			this.extra_element = $(this.toolbar.getChildren()[1]);

		this.toolbar_scroll_content = this.toolbar.getElement('.toolbar');
		this.scrollable_content_width = this.toolbar_scroll_content.getSize().x;
		this.scroll_left = this.toolbar.getElement('.scroll_left');
		this.scroll_right = this.toolbar.getElement('.scroll_right');

		this.resize_toolbar();

		this.scroll_right.addEvent('mouseenter', this.scroll_toolbar_right);
		this.scroll_left.addEvent('mouseenter', this.scroll_toolbar_left);
		this.scroll_right.addEvent('mouseleave', this.stop_scrolling);
		this.scroll_left.addEvent('mouseleave', this.stop_scrolling);
		window.addEvent('resize', this.resize_toolbar);
	},

	detach: function()
	{
		this.scroll_right.removeEvent('mouseenter', this.scroll_toolbar_right);
		this.scroll_left.removeEvent('mouseenter', this.scroll_toolbar_left);
		this.scroll_right.removeEvent('mouseleave', this.stop_scrolling);
		this.scroll_left.removeEvent('mouseleave', this.stop_scrolling);
		window.removeEvent('resize', this.resize_toolbar);
	},

	resize_toolbar: function()
	{
		var full_width = this.toolbar.getSize().x;
		var extra_element_width = this.extra_element ? (this.extra_element.getSize().x + 20) : 0;

		var toolbar_no_buttons_width = full_width - extra_element_width;
		var scroll_buttons_visible = this.scrollable_content_width > toolbar_no_buttons_width;
		var buttons_width = scroll_buttons_visible ? this.scroll_button_width*2 : 0;

		var toolbar_width = toolbar_no_buttons_width - buttons_width;

		this.toolbar_scroll_area.setStyle('width', (toolbar_width - (scroll_buttons_visible ? 3 : 0)) + 'px');
		this.toolbar_scroll_controls.setStyle('width', toolbar_no_buttons_width + 'px');

		if (this.scrollable_content_width > toolbar_width)
		{
			this.toolbar.addClass('scroll_enabled');
			this.scroll_left.show();
			this.scroll_right.show();
		}
		else
		{
			this.toolbar.removeClass('scroll_enabled');
			this.scroll_left.hide();
			this.scroll_right.hide();
			this.toolbar_scroll_area.scrollLeft = 0;
		}

		this.update_scroll_buttons();
	},

	update_scroll_buttons: function()
	{
		if (this.scroll_right_visible())
			this.scroll_right.removeClass('disabled')
		else
			this.scroll_right.addClass('disabled');

		if (this.scroll_left_visible())
			this.scroll_left.removeClass('disabled')
		else
			this.scroll_left.addClass('disabled');
	},

	scroll_right_visible: function()
	{
		var toolbar_width = this.toolbar_scroll_content.getSize().x;
		var scrollarea_width = this.toolbar_scroll_area.getSize().x;
		var max_scroll_offset =  toolbar_width - scrollarea_width;

		return toolbar_width > scrollarea_width && this.toolbar_scroll_area.scrollLeft < max_scroll_offset;
	},

	scroll_left_visible: function()
	{
		return this.toolbar_scroll_area.scrollLeft > 0;
	},

	scroll_toolbar_right: function()
	{
		this.start_scrolling(this.scroll_offset);
	},

	scroll_toolbar_left: function()
	{
		this.start_scrolling(this.scroll_offset*-1);
	},

	start_scrolling: function(offset)
	{
		this.scroll_interval_id = this.scroll.periodical(30, this, offset);
	},

	scroll: function(offset)
	{
		this.toolbar_scroll_area.scrollLeft = this.toolbar_scroll_area.scrollLeft + offset;
		this.update_scroll_buttons();
		backend_scrollable_toolbar_offsets[this.offset_index] = this.toolbar_scroll_area.scrollLeft;
	},

	stop_scrolling: function()
	{
		if (this.scroll_interval_id)
		{
			$clear(this.scroll_interval_id);
			this.scroll_interval_id = 0;
		}
	},

	set_scroll: function(scroll)
	{
		this.toolbar_scroll_area.scrollLeft = scroll;
		this.update_scroll_buttons();
		backend_scrollable_toolbar_offsets[this.offset_index] = this.toolbar_scroll_area.scrollLeft;
	}
});

function init_srollable_toolbars()
{
	$$('.scrollable_control_panel').each(function(toolbar){
		new Backend_ScrollabeToolbar(toolbar);
	})
}

function update_scrollable_toolbars()
{
	backend_scrollable_toolbars.each(function(toolbar){
		toolbar.detach();
	});

	backend_scrollable_toolbars = []
	init_srollable_toolbars();
}

window.addEvent('domready', init_srollable_toolbars);

/*
 * Scrollable form tabs
 */

var Backend_ScrollabeTabbar = new Class({
	tabbar: null,
	scroll_offset: 15,
	
	Binds: ['scroll_tabs_right', 'scroll_tabs_left', 'stop_scrolling', 'resize_toolbar'],
	
	initialize: function(tabbar)
	{
		this.attach(tabbar);
	},

	attach: function(tabbar)
	{
		this.tabbar = $(tabbar);
		this.scroll_area = this.tabbar.getElement('ul');
		this.scroll_area.scrollLeft = 0;
		
		this.scroll_left = this.tabbar.getElement('.left');
		this.scroll_right = this.tabbar.getElement('.right');

		this.scroll_right.addEvent('mouseenter', this.scroll_tabs_right);
		this.scroll_left.addEvent('mouseenter', this.scroll_tabs_left);
		this.scroll_right.addEvent('mouseleave', this.stop_scrolling);
		this.scroll_left.addEvent('mouseleave', this.stop_scrolling);
		
		this._update();
		this._update.delay(1000, this);
		var self = this;
		
		jQuery(window).bind('onLayoutUpdated', function(){
			self._update();
		});
		
		window.addEvent('resize', this._update.bind(this));
	},

	detach: function()
	{
		this.scroll_right.removeEvent('mouseenter', this.scroll_tabs_right);
		this.scroll_left.removeEvent('mouseenter', this.scroll_tabs_left);
		this.scroll_right.removeEvent('mouseleave', this.stop_scrolling);
		this.scroll_left.removeEvent('mouseleave', this.stop_scrolling);
	},
	
	scroll_tabs_right: function()
	{
		this.start_scrolling(this.scroll_offset);
	},

	scroll_tabs_left: function()
	{
		this.start_scrolling(this.scroll_offset*-1);
	},

	start_scrolling: function(offset)
	{
		this.scroll_interval_id = this.scroll.periodical(30, this, offset);
		this.prev_offset = this.scroll_area.scrollLeft;
	},

	scroll: function(offset)
	{
		this.scroll_area.scrollLeft = this.scroll_area.scrollLeft + offset;
		if (this.scroll_area.scrollLeft === this.prev_offset) 
		{
			if (offset > 0)
				this.scroll_right.addClass('scroll-disabled');
			else
				this.scroll_left.addClass('scroll-disabled');
		} else {
			if (offset > 0)
				this.scroll_left.removeClass('scroll-disabled');
			else
				this.scroll_right.removeClass('scroll-disabled');
		}
		
		this.prev_offset = this.scroll_area.scrollLeft;
	},

	stop_scrolling: function()
	{
		if (this.scroll_interval_id)
		{
			$clear(this.scroll_interval_id);
			this.scroll_interval_id = 0;
		}
	},
	
	_update: function() 
	{
		var tabs_width = this._get_tabs_width();
		if (tabs_width > this.scroll_area.getSize().x)
		{
			this.tabbar.addClass('scroll-active');
		} else {
			this.tabbar.removeClass('scroll-active');
		}
	},
	
	_get_tabs_width: function()
	{
		var result = 0;
		this.scroll_area.getElements('li').each(function(li){
			if (!li.hasClass('hidden'))
			{
				result += li.getSize().x+1;
				var margin = parseInt(jQuery(li).css("margin-left"));
				if (margin < 0)
					result += margin;
			}
		})

		return result;
	}
});


/*
 * Fullscreen mode
 */

function backend_toggle_fullscreen()
{
	var fullscreen_mode = false;
	
	if ($('backend_fullscreen_enabled').value == 1)
	{
		$('header').show();
		$('footer').show();
		$('backend_fullscreen_enabled').value = 0;
		$('toggle_backend_fullscreen').removeClass('on');
		fullscreen_mode = 0;
	} else
	{
		$('header').hide();
		$('footer').hide();
		$('backend_fullscreen_enabled').value = 1;
		$('toggle_backend_fullscreen').addClass('on');
		fullscreen_mode = 1;
	}
	
	update_scrollable_toolbars();
	window.fireEvent('fullscreenUpdate');

	backend_trigger_layout_updated();

	return $('backend_header_form').sendPhpr('onFullscreen', {
		extraFields: {
			'fullscreen_mode': fullscreen_mode
		},
		loadIndicator: {show: false}
	});
	
	return false;
}

/*
 * Commmon functions
 */

function convert_text_to_url(text)
{
	var url_separator_char = '_';
	var url_ampersand_replace = 'and';
	if (typeof(url_separator) != 'undefined')
		url_separator_char = url_separator;
	
	var value = text.replace(/&/g, url_ampersand_replace);
	value = value.replace(/[^\s\-\._a-z0-9]/gi, ''); // remove everything except alphanumeric, slashes, underscores, spaces, dots

	value = value.replace(/[^a-z0-9\.]/gi, url_separator_char); // replace everything with dashes except alphanumeric and dots
	
	var p = new RegExp(url_separator_char+'+', 'g');
	value = value.replace(p, url_separator_char); // remove duplicate dashes
	
	p = new RegExp(url_separator_char+'$', 'g');
	
	if (value.match(p))
		value = value.substr(0, value.length-1);
	
	return value.toLowerCase();
}

function hide_tip(hint_name, close_element, hint_element)
{
	if (hint_element === undefined)
		hint_element = $(close_element).selectParent('div.hint');

	if (hint_element)
		hint_element.hide();

	var form = hint_element.getForm();

	return $(form).sendPhpr('onHideHint', {
		extraFields: {
			'name': hint_name
		},
		loadIndicator: {show: false}
	});
}

/*
 * Form styling
 */

function backend_style_forms()
{
	jQuery('select').each(function(){
		if (!this.hasClass('no-styling'))
		{
			var 
				options = {},
				self = this,
				select = jQuery(this);

			if (this.options.length > 0 && this.options[0].value == "")
			{
				var placeholder = jQuery(this.options[0]).text();
				placeholder = placeholder.replace('<', '- ').replace('>', ' -').replace("'", "\'");
				select.attr('data-placeholder', placeholder);
				jQuery(this.options[0]).text('');
				options.allow_single_deselect = true;
			}

			select.unbind('.chosen-handler');
			select.bind('change.chosen-handler', function(){
				$(this).fireEvent('change');
			});

			select.chosen(options);
		}
	});
	
	jQuery('input[type=checkbox]').each(function(){
		if (!this.styled)
		{
			this.styled = true;
			
			var 
				self = $(this),
				replacement = new Element('div', {'class': 'checkbox', 'tabindex': 0}),
				handle_click = function() {
					/* 
					 * Update the checkbox state and execute the checbox onclick and onchange handlers
					 */ 

					self.checked = !self.checked;
					if (self.onclick !== undefined && self.onclick)
						self.onclick();

					if (self.onchange !== undefined && self.onchange)
						self.onchange();

					/* 
					 * Fire MooTools events.
					 */

					self.fireEvent('click');
					self.fireEvent('change');
				},
				update_replacement_status = function() {
					if (self.checked)
						replacement.addClass('checked');
					else
						replacement.removeClass('checked');
				};
			
			self.addClass('hidden');
			if (this.checked)
				replacement.addClass('checked');

			if (this.disabled)
				replacement.addClass('disabled');
				
			replacement.addEvent('keydown', function(ev){
				if (!replacement.hasClass('disabled'))
				{
					var event = new Event(ev);

					if (event.code == 32 || event.code == 13)
					{
						if (!ev.control)
						{
							handle_click();
							ev.stopPropagation();
							return false;
						}
					}
				}
			});
			
			self.addEvent('change', function(){
				if (replacement.hasClass('disabled'))
					return;

				update_replacement_status();
			});

			self.addEvent('change_status', function(){
				update_replacement_status();
			});
			
			self.addEvent('enable', function(){
				self.disabled = false;
				replacement.removeClass('disabled');
			});

			self.addEvent('disable', function(){
				replacement.addClass('disabled');
			});
			
			replacement.addEvent('click', function(ev) {
				if (!replacement.hasClass('disabled'))
				{
					handle_click();

					var event = new Event(ev);
					ev.stopPropagation();
					return false;
				}
			});
			
			jQuery(replacement).bind('dblclick', function(ev){
				ev.stopPropagation();
				return false;
			});

			replacement.inject(self, 'before');
		}
	});
};

Element.implement({
	cb_check: function() {
		this.cb_update_state(true);
	},
	
	cb_uncheck: function() {
		this.cb_update_state(false);
	},

	cb_update_state: function(state) {
		this.checked = state;
		this.fireEvent('change_status');
		jQuery(this).trigger('change');
		
		/*
		 * Do not trigger the click handler, because it can 
		 * result in recursion. However it could be needed
		 * in some cases and can be solved with an optional 
		 * parameter.
		 */
	},
	
	cb_enable: function() {
		this.fireEvent('enable');
	},
	
	cb_disable: function() {
		this.fireEvent('disable');
	},
	
	cb_update_enabled_state: function(state) {
		if (state)
			this.cb_enable();
		else
			this.cb_disable();
	},
	
	select_update: function() {
		jQuery(this).trigger("liszt:updated");
	},
	
	select_focus: function() {
		var el = jQuery(this).parent().find('a.chzn-single');
		if (el.length > 0) el[0].focus();
	}
});

window.addEvent('domready', function(){
	backend_style_forms();
	window.addEvent('onAfterAjaxUpdateGlobal', backend_style_forms);
	
	var form = jQuery('div.form');
	if (form.length > 0 && $(form[0]).getElement('div.tabs') == undefined)
		backend_focus_first(form[0]);
	
	var content = jQuery('#content');
	if (content.length > 0)
		backend_bind_default_button(content[0]);
});

/*
 * Focus management and default button support
 */

function backend_focus_first(parent) {
	var el = jQuery(parent).find('input, textarea, a.chzn-single, div.checkbox').filter(":visible").filter(":first");
	if (el.length > 0) {
		if (el.parent().hasClass('searchControl'))
			el[0].fireEvent('click');

		if (el.closest('li').hasClass('code_editor')) {
			window.addEvent('phpr_codeeditor_initialized', function(id, editor){
				if (id == el.attr('id'))
					(function(){ editor.focus(); }).delay(50);
			});
			
			var textarea = el.closest('li').find('textarea.hidden');
			if (textarea.length) {
				var editor = find_code_editor(textarea.attr('id'));
				if (editor)
					editor.focus();
			}
		} else
			(function(){ el[0].focus(); }).delay(50);
	}
}

function backend_bind_default_button(parent) {
	var button = jQuery(parent).find('div.button.default a');

	if (button.length == 0)
		return;

	var form = button.closest('form');
	if (form.length == 0)
		return;
		
	form[0].bindKeys({'ctrl+enter': function(event) {
		button[0].onclick();
		return false;
	}});
	
	button.addClass('tooltip');
	var title = button.attr('title');
	title = title ? title += ' ' : '';
	title += '<strong>ctrl+enter</strong>';
	button.attr('title', title);
	update_tooltips();
}

function backend_trigger_layout_updated()
{
	jQuery(window).trigger('onLayoutUpdated');
}

window.addEvent('popupLoaded', function(element){

	backend_focus_first(element);
	backend_bind_default_button(element);
});

/*
 * Menu fix for Safari
 */

if (Browser.Engine.webkit) {
	window.addEvent('topMenuHide', function(){
		$$('object, embed').each(function(flash_element){
			flash_element.getParent().removeClass('invisible');
		})
	});

	window.addEvent('topMenuShow', function(){
		$$('object, embed').each(function(flash_element){
			flash_element.getParent().addClass('invisible');
		})
	});
}