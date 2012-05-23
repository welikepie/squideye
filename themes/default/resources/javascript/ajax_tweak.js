window.addEvent('frontendready', function(){
  Request.Phpr.implement({
    frontend_create_loading_indicator: function()
    {
      if (this.loading_indicator_element)
        return;

      this.loading_indicator_element = new Element('p', {'class': 'ajax_loading_indicator'}).inject(document.body, 'top');
      this.loading_indicator_element.innerHTML = '<span>Message...</span>';
    }
  })
});