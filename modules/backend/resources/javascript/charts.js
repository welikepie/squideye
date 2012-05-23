var chart_objects = {};

var FlashChart = new Class({
	Implements: [Options, Events],
	
	options: {
		type: 'line',
		data_url: null
	},

	initialize: function(element, options) {
		this.setOptions(options);
		
		this.chart_id = $(element).id + '-chart';

		this.so = new SWFObject(ls_root_url("/modules/backend/resources/swf/am"+this.options.type+".swf"), this.chart_id, "100%", "100%", "8", "#FFFFFF");
		this.so.addVariable("path", escape(ls_root_url('/modules/backend/resources/swf/')));
		this.so.addVariable("settings_file", 
			encodeURIComponent(ls_root_url('/modules/backend/resources/xml/chart_common_settings.xml')) + ',' +
			encodeURIComponent(ls_root_url('/modules/backend/resources/xml/chart_'+this.options.type+'_settings.xml')) + ',' +
			encodeURIComponent(this.options.data_url)
		);
		this.so.addParam("wmode", "transparent");

		this.so.addVariable("loading_settings", "Loading chart settings...");
		this.so.addVariable("loading_data", "Loading data...");
		this.so.addVariable("chart_id", this.chart_id);

		this.so.write($(element).id);
	},
	
	reload: function() {
		var chart_id = this.chart_id;

		if (chart_objects[chart_id] !== undefined) {
			chart_objects[chart_id].reloadAll();
		}
	}
});

function amChartInited (chart_id) {
	chart_objects[chart_id] = document.getElementById(chart_id);
}