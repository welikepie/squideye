var charts_hidden = false;

function update_indicators_order()
{
	var indicator_list = $('visits_stats');
	var ind_number = indicator_list.getChildren().length;
	indicator_list.getChildren().each(function(indicator, index)
	{
		if (index == ind_number-1)
			indicator.addClass('last');
		else
			indicator.removeClass('last');
	});
	
	dashboard_set_visible_indicators();
}

function dashboard_set_visible_indicators()
{
	$('dashboard_indicators_form').sendPhpr('index_onSetIndicatorsOrder', {loadIndicator: {show: false}, lock: false});
}

function dashboard_set_visible_reports()
{
	$('dashboard_reports_form').sendPhpr('index_onSetReportsOrder', {loadIndicator: {show: false}, lock: false});
}

function dashboard_align_reports()
{
	var reports_container = $('dashboard_reports');
	if (!reports_container)
		return;

	var container_size = reports_container.getCoordinates();
	var gap = container_size.width - Math.round(container_size.width*0.49)*2 - 5;

	$('dashboard_reports').getChildren().each(function(element, index)
	{
		if (index % 2)
		{
			element.setStyle('margin-left', gap+'px');
		}
		else
			element.setStyle('margin-left', 0);
	});
	
	dashboard_set_visible_reports();
}

function dashboard_hide_indicator(hide_button)
{
	var indicator_element = $(hide_button).getParent();
	indicator_element.destroy();
	
	var indicator_list = $('visits_stats');
	var ind_number = indicator_list.getChildren().length;
	if (!ind_number)
	{
		var no_indicators_li = new Element('li', {'class': 'no_data'}).inject(indicator_list);
		no_indicators_li.innerHTML = '<p>&lt;no indicators added&gt;</p>';
	}

	update_indicators_order.delay(100);
	return false;
}

function dashboard_hide_report(hide_button)
{
	var report_element = $(hide_button).getParent();
	report_element.destroy();
	
	dashboard_align_reports.delay(100);
	return false;
}

function dashboard_init_interaction()
{
	dashboard_align_reports();
	
	new Sortables(
		[$('dashboard_reports')], 
		{
			clone: true, 
			opacity: 0.5, 
			revert: true,
			onComplete: dashboard_align_reports
		}
	);
	
	new Sortables(
		[$('visits_stats')], 
		{
			clone: true, 
			opacity: 0.5, 
			revert: true,
			onComplete: update_indicators_order
		}
	)
}

function show_chart_content()
{
	if (!$('amchart'))
		return;
	
	$('flashcontent').removeClass('invisible');
	$('flashcontent').getParent().removeClass('flash_overlay');
}

function hide_chart_content()
{
	if (!$('amchart'))
		return;

	$('flashcontent').addClass('invisible');
	$('flashcontent').getParent().addClass('flash_overlay');
}

window.addEvent('resize', dashboard_align_reports);
window.addEvent('domready', dashboard_init_interaction);

window.addEvent('topMenuHide', function(){
	show_chart_content();
});

window.addEvent('topMenuShow', function(){
	hide_chart_content();
});