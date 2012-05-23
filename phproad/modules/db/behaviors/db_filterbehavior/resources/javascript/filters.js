function filter_add_record(link_element)
{
	/*
	 * Find record ID and description
	 */
	
	link_element = $(link_element);
	var record_id = link_element.getParent().getElement('input.record_id').value;
	var cells = link_element.getParent().getParent().getChildren();
	
	var record_name = [];
	cells.each(function(cell){
		if (!cell.hasClass('iconCell') && !cell.hasClass('expandControl'))
			record_name.push(cell.innerHTML.trim());
	})
	
	record_name = record_name.join(', ');

	var table_body = $('added_filter_list');

	/*
	 * Check whether record exists
	 */
	
	var record_exists = table_body.getElements('tr td input.record_id').some(function(field){return field.value == record_id;})
	if (record_exists)
		return false;

	/*
	 * Create row in the added records list
	 */
	
	var icon_cell_content = '<a class="filter_control" href="#" onclick="return filter_delete_record(this)"><img src="'+ls_root_url('phproad/modules/db/behaviors/db_filterbehavior/resources/images/remove_record.gif')+'" alt="Remove record" title="Remove record" width="16" height="16"/></a>';
	
	var no_data_row = table_body.getElement('tr.noData');
	if (no_data_row)
		no_data_row.destroy();
	
	var row = new Element('tr').inject(table_body);
	var iconCell = new Element('td', {'class': 'iconCell'}).inject(row);
	iconCell.innerHTML = icon_cell_content;
	
	var name_cell = new Element('td', {'class': 'last'}).inject(row);
	name_cell.innerHTML = record_name;
	new Element('input', {'type': 'hidden', 'name': 'filter_ids[]', 'class': 'record_id', 'value': record_id}).inject(name_cell);
	
	if (!(table_body.getChildren().length % 2))
		row.addClass('even');
	
	return false;
}

function filter_delete_record(link_element)
{
	link_element = $(link_element);
	var table_body = $('added_filter_list');
	var row = link_element.getParent().getParent();
	row.destroy();
	
	table_body.getChildren().each(function(row, index){
		row.removeClass('even');
		if (index % 2)
			row.addClass('even');
	});
	
	if (!table_body.getChildren().length)
	{
		var row = new Element('tr', {'class': 'noData'}).inject(table_body);
		var el = new Element('td').inject(row);
		el.innerHTML = 'No records added';
	}
	
	return false;
}