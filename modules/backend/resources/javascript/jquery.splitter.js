(function( $ ){
	$.fn.backendSplitter = function(options) {
		
		var
			defaults = {
				minWidth: 0,
				saveWidth: false
			},
			settings = $.extend(defaults, options);
			
		return this.each(function() {
			var $this = $(this);  
			var leftPanel = $this.find('.splitter-panel.left-panel');
			var rightPanel = $this.find('.splitter-panel.right-panel');
			var separator = $this.find('.splitter-separator');
			var initialLeftOffset = leftPanel.offset().left;
			var separatorWidth = separator.width();
			var splitterWidth = $this.width();
			
			if (settings.minWidth) {
				leftPanel.css('min-width', settings.minWidth+'px');
				rightPanel.css('min-width', settings.minWidth+'px');
			}
			
			var savedWidth = Cookie.read('splitter-widget-' + $this.attr('id'));
			if (savedWidth != null)
				leftPanel.css('width', savedWidth + 'px');
			else if (settings.minWidth) {
				var calculatedWidth = splitterWidth - separatorWidth - settings.minWidth;
				leftPanel.css('width', calculatedWidth + 'px');
			}
			
			fixWidth();

			separator.drag(function( ev, dd ){
				var width = evalWidth(dd.offsetX, initialLeftOffset);
				leftPanel.css('width', width + 'px');
			});
			
			separator.bind('draginit', function(){
				splitterWidth = $this.width();
				$('body').removeClass('no-drag');
				$('body').addClass('drag');
			});
			
			separator.bind('dragend', function(){
				$('body').addClass('no-drag');
				$('body').removeClass('drag');
				if (settings.saveWidth) 
				{
					var width = evalWidth(leftPanel.width(), initialLeftOffset);
					Cookie.write('splitter-widget-' + $this.attr('id'), width, {duration: 365, 'path': '/'});
				}
				separator.css('left', 0);
				window.fireEvent('phpr_layout_updated');
				backend_trigger_layout_updated();
			});
			
			function evalWidth(currentOffset, initialOffset) {
				var leftWidth = Math.round(currentOffset-initialOffset);
				var rightWidth = splitterWidth - separatorWidth - leftWidth;
				
				if (rightWidth < settings.minWidth)
					return splitterWidth - settings.minWidth - separatorWidth;

				if (leftWidth > settings.minWidth)
					return leftWidth;

				return settings.minWidth;
			}
			
			function fixWidth() {
				if (settings.minWidth && rightPanel.width() < settings.minWidth) {
					var calculatedWidth = $this.width() - $this.width() - settings.minWidth;
					leftPanel.css('width', calculatedWidth + 'px');
					
					if (settings.saveWidth)
						Cookie.write('splitter-widget-' + $this.attr('id'), calculatedWidth, {duration: 365, 'path': '/'});
				}
			}
			
			$(window).bind('resize', function(){
				fixWidth();
			});
		});
	}
})( jQuery );