(function( $ ){
	$.ui.anchorLayout = {
		bottomAnchored: [],
		
		init: function() {
			this.scan();
			var self = this;
			$(window).bind('resize onTabClick onLayoutUpdated phpr_layout_updated', function(){
				self.updateConditional();
			})
			
			this.footer = $('#footer');
			this.footerPaddingBottom = this.footer.padding().bottom;
			this.footerBorderTop = this.footer.border().top;
			
			var $content = $('#content');
			this.contentPadding = $content.padding().bottom;
			this.update();
		},
		
		scan: function() {
			this.bottomAnchored = [];
			var self = this;
			$('.ui-layout-anchor-window-bottom').each(function(index, element){
				var 
					$element = $(element),
					offsetBottom = self.offsetBottom($element),
					offsetTop = $element.margin().top + $element.padding().top + $element.border().top,
					tweak = 0,
					offsetAttr = /offset\-([0-9]+)/.exec($element.attr('class'));
					if (offsetAttr !== null && offsetAttr.length == 2)
						tweak = parseInt(offsetAttr[1]);
					
				$element.parents().each(function(index, parent) {
					$parent = $(parent);
					offsetBottom += self.offsetBottom($parent);
				});

				self.bottomAnchored.push({
					el: $element,
					'offsetBottom': offsetBottom,
					'offsetTop': offsetTop,
					'tweak': tweak
				});
			})
		},
		
		offsetBottom: function(element) {
			var offset = 0;
			if (offset = element.data('ui-layout-offset-bottom'))
			{
				return offset == -1 ? 0 : offset;
			}

		 	offset = element.margin().bottom + element.padding().bottom + element.border().bottom;
			element.data('ui-layout-offset-bottom', offset == 0 ? -1 : offset);
			
			return offset;
		},
		
		update: function() {
			$('#content').css('overflow', 'hidden');
			$.each(this.bottomAnchored, function(index, elementInfo){
				elementInfo.el.css('position', 'absolute');
			});

			var 
				windowHeight = $(window).height(),
				documentHeight = $(document).height(),
				footerHeight = 0,
				self = this,
				useDocumentHeight = windowHeight < documentHeight,
				parentHeight = useDocumentHeight ? documentHeight : windowHeight;

			if (self.footer.is(':visible'))
				footerHeight = this.footer.height() + this.footerPaddingBottom + this.footerBorderTop;

			$.each(this.bottomAnchored, function(index, elementInfo){
				var 
					height = parentHeight 
						- elementInfo.el.offset().top
						- elementInfo.offsetTop
						- elementInfo.offsetBottom
						- elementInfo.tweak
						- footerHeight;

				elementInfo.el.css('height', Math.round(height) + 'px');
			});
			
			$.each(this.bottomAnchored, function(index, elementInfo){
				elementInfo.el.css('position', 'static');
			});
			
			$('#content').css('overflow', 'visible');
		},
		
		updateConditional: function() {
			var 
				documentHeight = $(document).height(),
				self = this;

			this.update();
			
			var new_height = $(document).height();
			if (documentHeight != new_height) {
				window.setTimeout(function(){ self.updateConditional() }, 50);
			}
		},
		
		refresh: function() {
			this.scan();
		}
	}
	
	$(window).load(function(){
		$.ui.anchorLayout.init();
	})
})( jQuery );