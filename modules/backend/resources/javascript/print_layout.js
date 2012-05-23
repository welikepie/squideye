var header_hide_effect = null;
var footer_hide_effect = null;
var print_layut_hide_timer;
var print_layout_stop = false;
var print_layout_lock = false;
var breadcrumbs = null;

function print_layout_mouse_move()
{
	if (print_layout_stop || print_layout_lock)
		return;
	
	$(document.body).removeClass('printLayout');
	header_hide_effect.slideIn();
	footer_hide_effect.slideIn();
	
	if (breadcrumbs)
		breadcrumbs.show();
	
	$clear(print_layut_hide_timer);
	print_layut_hide_timer = print_layout_mouse_stop.delay(1500);
}

function print_layout_mouse_stop()
{
	if (print_layout_lock)
		return;

	print_layout_stop = true;
	print_layout_continue.delay(700);

	$clear(print_layut_hide_timer);
	header_hide_effect.slideOut().chain(function(){
		$(document.body).addClass('printLayout');
	});
	footer_hide_effect.slideOut();
	backend_hide_slide_menus();
	if (breadcrumbs)
		breadcrumbs.hide();
}

function print_layout_continue()
{
	print_layout_stop = false;
}

function lock_print_layout()
{
	print_layout_lock = true;
}

window.addEvent('domready', function(){
	if ($('no_print_layout'))
		return;
	
	header_hide_effect = new Fx.Slide('header_elements', {duration: 500, wrapper_class: 'no_print'});
	footer_hide_effect = new Fx.Slide('footer', {duration: 500, wrapper_class: 'no_print'});
	
	breadcrumbs = $$('.breadcrumbs');
	if (breadcrumbs.length)
		breadcrumbs = breadcrumbs[0];
	else
		breadcrumbs = null;
	
	window.addEvent('mousemove', print_layout_mouse_move);
	print_layout_mouse_move();
});

window.onbeforeunload = lock_print_layout;