var MenuManager = new Class({
	tab_pages: null,
	pages_lists: [],
	pages: [],
	tabs: [],
	
	initialize: function()
	{
		this.tab_pages = new Hash();
		
		$('module_tabs').getElements('li').each(function(element){
			var index = element.get('id').substr(11);
			element.addEvent('mouseenter', this.onModuleTabEnter.bind(this, [index, element]));
			this.tabs.push(element);

			var page_list = $('module_pages_'+index);
			this.tab_pages.set(index, page_list);
			if (page_list)
				page_list.getElements('li').each(function(page_element){
					page_element.addEvent('mouseenter', this.onPageEnter.bind(this, page_element));
					this.pages.push(page_element);
				}, this);
		}, this);
		
		$('page_tabs').getElements('ul').each(function(element){this.pages_lists.push(element)}, this);
		$('tabs_container').addEvent('mouseleave', this.onTabsLeave.bind(this));
	},
	
	onTabsLeave: function()
	{
		this.unHoverPageLists();
		this.unHoverPages();
		this.unHoverTabs();
		$('tabs_container').removeClass('hover_mode');
	},
	
	onModuleTabEnter: function(index, tab_element)
	{
		this.unHoverPageLists();
		this.unHoverPages();
		this.unHoverTabs();

		$('tabs_container').addClass('hover_mode');
		tab_element.addClass('hover');
		
		var page_list = this.tab_pages.get(index);
		if (page_list)
			page_list.addClass('hover');
	},
	
	unHoverPageLists: function()
	{
		this.pages_lists.each(function(element){element.removeClass('hover')});
	},
	
	unHoverPages: function()
	{
		this.pages.each(function(element){element.removeClass('hover')});
	},
	
	unHoverTabs: function()
	{
		this.tabs.each(function(element){element.removeClass('hover')});
	},
	
	onPageEnter: function(page_element)
	{
		if (!this.isHoverMode())
			return;

		this.unHoverPages();
		page_element.addClass('hover');
	},
	
	isHoverMode: function()
	{
		return $('tabs_container').hasClass('hover_mode');
	}
});

//window.addEvent('domready', function(){new MenuManager()});