var charts_hidden = false;

function reloadChart()
{
	return $('chart_form').sendPhpr('index_onUpdateChart', 
		{
			update: $('report_chart'), 
			loadIndicator: {show: false}
		}
	);
}

function updateReportData(reload_chart_object)
{
	listReload(false);
	if (reload_chart_object)
		reloadChart();
	else
	{
		if (window.flashMovie)
			window.flashMovie.reloadAll();
		else
			reloadChart();
	}
	reloadTotals();
}

function reloadTotals()
{
	hideChartContent();
	return $('chart_form').sendPhpr('index_onUpdateTotals', 
		{
			update: $('report_totals'), 
			loadIndicator: {
				element: $('content'),
				hideOnSuccess: true,
				overlayOpacity: 0.8,
				overlayClass: 'whiteOverlay',
				hideElement: false,
				src: 'modules/backend/resources/images/loading_global.gif'
			},
			onComplete: showChartContent
		}
	);
}

function hideChart()
{
	charts_hidden = true;
	hideChartContent();
}

function hideChartContent()
{
	return;
	
	if (Browser.Platform.name == 'mac' && Browser.Engine.name != 'presto')
		return;

	$('report_chart_container').addClass('flash_overlay');
	$('report_chart').invisible();
}

function showChartSubstitution()
{
	$('flashcontent').invisible();
	$('chart_container').addClass('substitution');
}

function hideChartSubstitution()
{
	$('flashcontent').removeClass('invisible');
	$('chart_container').removeClass('substitution');
}

function showChart()
{
	showChartContent();
	charts_hidden = false;
}

function showChartContent()
{
	return;
	
	if (Browser.Platform.name == 'mac' && Browser.Engine.name != 'presto')
		return;
		
	$('report_chart').removeClass('invisible');
	$('report_chart_container').removeClass('flash_overlay');
}

function reportSetParameter(name, value)
{
	$('chart_form').sendPhpr('index_onSetReportParameter', {
		extraFields: {'param': name, 'value': value}, 
		loadIndicator: {show: false}, 
		onSuccess: updateReportData.pass(false)
	});
}

window.addEvent('popupDisplay', function(){
	if (!$('flashcontent'))
		return;
		
	hideChart();
});

window.addEvent('popupHide', function(){
	if (!$('flashcontent'))
		return;

	showChart();
});

window.addEvent('datePickerDisplay', function(){
	if (!$('flashcontent'))
		return;
		
	hideChart();
});

window.addEvent('datePickerHide', function(){
	if (!$('flashcontent'))
		return;

	showChart();
});

window.addEvent('topMenuHide', function(){
	if (!charts_hidden)
		showChartContent();
});

window.addEvent('topMenuShow', function(){
	if (!charts_hidden)
		hideChartContent();
});