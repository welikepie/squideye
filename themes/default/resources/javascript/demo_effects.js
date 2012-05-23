/*
 * Image slider
 */

var ImageSlider = new Class({
  slider_element: null,
  thumbnails: [],
  fullsize: [],
  slider: null,
  
  initialize: function(slider_element, thumbnails, fullsize)
  {
    this.thumbnails = thumbnails;
    this.fullsize = fullsize;
    
    if (Browser.loaded)
      this.init_control.delay(30, this, slider_element);
    else
      window.addEvent('domready', this.init_control.bind(this, [slider_element]));
  },
  
  init_control: function(slider_element)
  {
    this.slider_element = $(slider_element);

    this.slider = new Slider(
      $(this.slider_element).getElement('div.slider'), 
      $(this.slider_element).getElement('.knob'), 
      {
        steps: this.thumbnails.length,
        snap: false,
        onChange: this.sliderChange.bind(this)
    });
    
    this.slider_element.getElement('a').addEvent('click', this.imageClick.bind(this));
  },
  
  imageClick: function()
  {
    var images = [];
    var current_index = 0;
    var current_href = this.slider_element.getElement('a').get('href');
    for  (var i=0; i < this.fullsize.length; i++)
    {
      images.push([this.fullsize[i]]);
      if (current_href == this.fullsize[i])
        current_index = i;
    }

    open_slimbox(images, current_index);
    return false;
  },
  
  sliderChange: function(index) 
  {
    if (index > this.thumbnails.length-1)
      return;

    this.slider_element.getElement('img').set('src', this.thumbnails[index]);
    this.slider_element.getElement('span').set('text', index+1);
    this.slider_element.getElement('a').set('href', this.fullsize[index]);
  }
});

function open_slimbox(images, current_index)
{
  Slimbox.open(images, current_index);
}

/*
 * Rating selector
 */

var RatingSelector = new Class({
  stars_element: null,
  rating_element: null,

  initialize: function(selector_element)
  {
    if (Browser.loaded)
      this.init_control.delay(30, this, selector_element);
    else
      window.addEvent('domready', this.init_control.bind(this, [selector_element]));
  },

  init_control: function(selector_element)
  {
    this.stars_element = $(selector_element).getElement('span.rating_stars');
    this.rating_element = $(selector_element).getElement('input');
    if (this.stars_element && this.rating_element)
      this.stars_element.addEvent('click', this.handle_click.bindWithEvent(this));
  },
  
  handle_click: function(event)
  {
    var stars_coords = this.stars_element.getCoordinates();
    var offset = event.page.x - stars_coords.left;
    var rating = Math.ceil(offset/(stars_coords.width/5));

    this.stars_element.className = 'rating_stars rating_'+rating;
    this.rating_element.value = rating;
  }
});
