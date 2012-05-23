(function( $, undefined ) {
	$.widget( "ui.fullHeightLayout", {
		
		options: {
		},
		
		_create: function() {
			this.header = $('#header_elements');
			this.footer = $('#footer');
			this.padding_top = this.element.padding().top;
			this.padding_bottom = this.element.padding().bottom;
			this.footer_padding_bottom = this.footer.padding().bottom;
			this.footer_border_top = this.footer.border().top;

			this.update();
			var self = this;
			
			$(window).bind('resize', function(){
				self.update();
			})
			
			window.addEvent('fullscreenUpdate', function(){
				self.update();
			})
		},
		
		update: function() {
			var 
				height = $(window).height() - this.padding_top - this.padding_bottom,
				header_height = this.header.height(),
				footer_height = this.footer.height() + this.footer_padding_bottom + this.footer_border_top;

			if (this.footer.is(':visible'))
				height -= footer_height;

			height -= header_height;
			this.element.css('min-height', height+'px');
		}
	});
})( jQuery );