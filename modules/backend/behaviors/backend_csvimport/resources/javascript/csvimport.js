var allow_onload = true;

function handle_unload()
{
	if (allow_onload)
		return;

	return 'The import process is in progress. Please do not leave the page until it is complete.';
}

window.onbeforeunload = handle_unload;

var Csv_Import_Manager = new Class({
	fc_scroller: null,
	dbc_scroller: null,
	list_sortables: null,
	
	initialize: function()
	{
		this.init_scrollers();
		this.init_dragdrop();
		
		window.addEvent('phpr_file_upload_complete', this.on_file_uploaded.bind(this));
	},
	
	init_scrollers: function()
	{
		this.fc_scroller = new BackendVScroller('file_columns_scroller');
		this.dbc_scroller = new BackendVScroller('db_columns_scroller');
	},

	init_dragdrop: function()
	{
		var lists = $('file_columns_scroller').getElements('ul.drop_container');
		lists.push($('db_columns_scroller').getElement('ul'));

		this.list_sortables = new Sortables(
			lists, 
			{
				clone: true,
				revert: true,
				constrain: false,
				onStart: function(element, clone)
				{
					clone.addClass('column_drag_clone');
				},
				onSort: function(element, clone)
				{
					this.update_column_lists();
				}.bind(this),
				onComplete: function(element, clone)
				{
					this.update_column_lists();
					this.update_match_data();
				}.bind(this)
			}
		);
	},
	
	update_dragdrop: function()
	{
		this.list_sortables.detach();
		
		var lists = $('file_columns_scroller').getElements('ul.drop_container');
		lists.push($('db_columns_scroller').getElement('ul'));

		this.list_sortables.addLists(lists);
	},
	
	update_column_lists: function()
	{
		$('file_columns_scroller').getElements('ul.drop_container').each(function(container_list){
			if (container_list.getFirst())
				container_list.getParent().addClass('match');
			else
				container_list.getParent().removeClass('match');
		});

		this.fc_scroller.update();
		this.dbc_scroller.update();
	},
	
	update_match_data: function()
	{
		$('file_columns_scroller').getElements('li.file_column').each(function(file_column){
			file_column.getElements('ul.drop_container li').each(function(db_column)
			{
				var column_index = file_column.getElement('.file_column_index').value;
				var column_name = file_column.getElement('.file_column_name').value;
		
				var column_match_field = db_column.getElement('.column_match');
				column_match_field.name = 'column_match['+column_index+'|'+column_name+'][]';
				column_match_field.value = db_column.getElement('.db_column_name').value;
			})
		});
		
		$('db_columns_scroller').getElements('li').each(function(db_column){
			var column_match_field = db_column.getElement('.column_match');
			column_match_field.name = '';
			column_match_field.value = '';
		});
	},
	
	get_handler_name: function(handler)
	{
		return $('csv_import_handler_name').value + handler;
	},
	
	csv_file_uploaded: function()
	{
		$('csv_import_columns').getForm().sendPhpr(this.get_handler_name('onCsvFileUploaded'), {
			update: 'multi',
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				element: $('csv_import_columns'),
				injectInElement: true
			},
			onAfterUpdate: function(){
				this.update_column_lists();
				this.update_dragdrop();
				this.update_toolbar();
			}.bind(this)
		});
	},
	
	on_file_uploaded: function(file_column_name)
	{
		if (file_column_name == 'csv_file')
		{
			this.csv_file_uploaded();
		} else if(file_column_name == 'config_import')
			this.config_file_uploaded();
	},
	
	show_column_data: function(column_index)
	{
		$('import_csv_preview_field_index').value = column_index;
		
		new PopupForm(
			this.get_handler_name('onCsvShowColumnData'), 
			{
				ajaxFields: $('csv_import_columns').getForm()
			}
		); 
		
		return false;
	},
	
	on_first_row_updated: function(cb)
	{
		$('csv_import_columns').getForm().sendPhpr(this.get_handler_name('onCsvFirstRowUpdated'), {
			update: 'multi',
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				element: $('csv_import_columns'),
				injectInElement: true
			},
			onAfterUpdate: function(){
				this.update_column_lists();
				this.update_dragdrop();
				this.update_toolbar();
			}.bind(this)
		});
	},
	
	hide_file_column: function(link)
	{
		var link_element = $(link);
		var li_element = link_element.findParent('li');
		var column_name = li_element.getElement('.file_column_display_name').value;
		var column_index = li_element.getElement('.file_column_index').value;

		if (li_element.getElement('ul.drop_container').getFirst())
		{
			alert('The "'+column_name+'" column is bound to a database column. Please remove the match first.');
			return false;
		}
		
		var message = 'Do you really want to ignore the "'+column_name+'" column?';
			
		if (!confirm(message))
			return false;
			
		li_element.getElement('.hidden_column').value = 1;
		li_element.addClass('hidden');
		this.fc_scroller.update();
		this.update_toolbar();

		return false;
	},
	
	update_toolbar: function()
	{
		this.track_ignored();
		
		if (!$('no_csv_flag'))
		{
			$('link_save_config').removeClass('disabled');
			$('link_load_config').removeClass('disabled');
		}
		else
		{
			$('link_save_config').addClass('disabled');
			$('link_load_config').addClass('disabled');
		}
	},

	track_ignored: function()
	{
		var ignored_found = false;
		
		$('file_columns_scroller').getElements('li.file_column').each(function(li_element){
			if (li_element.hasClass('hidden'))
				ignored_found = true;
		});
		
		if (ignored_found)
			$('link_display_ignored').removeClass('disabled');
		else
			$('link_display_ignored').addClass('disabled');
	},
	
	display_ignored: function()
	{
		if ($('link_display_ignored').hasClass('disabled'))
			return false;

		$('file_columns_scroller').getElements('li.file_column').each(function(li_element){
			li_element.getElement('.hidden_column').value = '';
			li_element.removeClass('hidden');
		});
		this.fc_scroller.update();

		$('link_display_ignored').addClass('disabled');
		return false;
	},
	
	save_configuration: function()
	{
		if ($('link_save_config').hasClass('disabled'))
			return false;

		$('csv_import_columns').getForm().sendPhpr(this.get_handler_name('onCsvSaveConfiguration'), {
			update: 'multi',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Saving...'), 
			onComplete: LightLoadingIndicator.hide,
			onFailure: popupAjaxError
		});

		return false;
	},
	
	load_configuration: function()
	{
		if ($('link_load_config').hasClass('disabled'))
			return false;
		
		new PopupForm(
			this.get_handler_name('onCsvShowLoadConfigForm'), 
			{
				ajaxFields: $('csv_import_columns').getForm()
			}
		); 
		
		return false;
	},
	
	config_file_uploaded: function()
	{
		$('csv_import_columns').getForm().sendPhpr(this.get_handler_name('onCsvConfigFileUploaded'), {
			update: 'multi',
			loadIndicator: {show: false},
			onBeforePost: LightLoadingIndicator.show.pass('Loading...'), 
			onComplete: LightLoadingIndicator.hide,
			onAfterUpdate: function(){
				this.update_column_lists();
				this.update_dragdrop();
				this.update_toolbar();
				cancelPopup();
			}.bind(this),
			onFailure: popupAjaxError
		});
	},
	
	import_data: function()
	{
		if ($('no_csv_flag'))
		{
			alert('Please upload a CSV file.');
			return false;
		}
		
		new PopupForm(
			this.get_handler_name('onCsvShowImportForm'), 
			{
				ajaxFields: $('csv_import_columns').getForm()
			}
		); 
		
		return false;
	},
	
	process_import: function()
	{
		allow_onload = false;

		$('csv_import_columns').getForm().sendPhpr(this.get_handler_name('onCsvImport'), {
			loadIndicator: {
				show: true,
				hideOnSuccess: true,
				element: $('import_info_container'),
				injectInElement: true
			},
			update: 'import_info_container',
			onComplete: function(){
				realignPopups();
				allow_onload = true;
				$('import_info_container').removeClass('import_progress');
			},
			onAfterUpdate: function()
			{
				if ($('import_result_tabs'))
				{
					new TabManager('import_result_tabs', 
					  	'import_result_pages', 
					  	{trackTab: false});
				}
			}
		});
	}
})

var import_manager = null;

window.addEvent('domready', function(){ 
	import_manager = new Csv_Import_Manager();
});