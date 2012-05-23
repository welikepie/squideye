var GridControlManagerClass = new Class({
	controls: null,

	initialize: function(table)
	{
		this.controls = new Hash();
	},

	register_control: function(id, control_obj)
	{
		this.controls.set(id, control_obj);
	},
	
	get_control: function(id)
	{
		return this.controls.get(id);
	}
});

var GridControlManager = new GridControlManagerClass();

var GridControl = new Class({
	Implements: [Options, Events],
	table: null,
	table_id: null,
	width_set:false,
	grid_container: null,
	current_row: null,
	current_input: null,
	table_head_cells: null,
	scroller: null,
	sortables: null,
	mouse_move_timer: null,
	options: {
		autocomplete_config: [],
		no_toolbar: false,
		allow_adding_rows: true,
		allow_deleting_rows: true,
		no_sorting: false
	},
	
	initialize: function(table, options)
	{
		this.setOptions(options);

		this.table = $(table);
		var reference_control = this.table;
		
		if (!this.table)
			reference_control = $(table + '_editing_disabled');
		
		this.grid_container = reference_control.selectParent('div.grid_container');
		this.table_head_cells = this.grid_container.getElements('thead th');

		this.table_id = table;
		this.set_widths();
		this.assign_rows_events();
		
		if (this.table)
		{
			this.table.bindKeys({
				'ctrl+i': this.on_key_insert_row.bind(this),
				'ctrl+d': this.on_key_delete_row.bind(this)
			});
		}

		var scrollable_element = reference_control.selectParent('div.backend_scroller');
		this.scroller = new BackendVScroller(scrollable_element, {
			slider_height_tweak: -16,
			auto_hide_slider: true,
			position_threshold: 9
		});
		
		window.addEvent('mousemove', this.trigger_scroller.bind(this));
		scrollable_element.addEvent('mousewheel', this.trigger_scroller.bind(this));
		
		GridControlManager.register_control(this.table_id, this);
		this.init_sortables();
		this.autocompleter_visible = false;
	},
	
	trigger_scroller: function()
	{
		this.grid_container.addClass('mouse_move');
		$clear(this.mouse_move_timer);
		this.mouse_move_timer = this.mouse_stop.delay(500, this);
	},
	
	mouse_stop: function()
	{
		$clear(this.mouse_move_timer);
		this.grid_container.removeClass('mouse_move');
	},
	
	init_table: function()
	{
		this.table = $(this.table_id);
		this.width_set = false;
		
		if (this.table)
		{
			this.table.bindKeys({
				'ctrl+i': this.on_key_insert_row.bind(this),
				'ctrl+d': this.on_key_delete_row.bind(this)
			});
			this.set_widths();
			this.assign_rows_events();
			this.init_sortables();
			this.fireEvent('addEnabled', this);
		} else
		{
			this.fireEvent('addDisabled', this);
			this.fireEvent('deleteDisabled', this);
		}

		this.scroller.update();
		this.scroller.update_position();
	},
	
	assign_rows_events: function()
	{
		if (!this.table)
			return;

		var rows = this.table.getElements('tbody tr');
		var cnt = rows.length;
		rows.each(this.assign_row_events, this);
	},
	
	init_sortables: function()
	{
		if (!this.table)
			return;

		if (this.options.no_sorting)
			return;
		
		if (this.sortables)
			this.sortables.detach();

		this.sortables = new Sortables11(this.table.getElement('tbody'), {
			startDelay: 300,
			onDragStart: function(element, ghost){
				ghost.destroy();
				element.addClass('drag');
			},
			onDragComplete: function(element, ghost){
				this.trash.destroy();
				element.removeClass('drag');
			}
		})
	},

	set_widths: function()
	{
		if (!this.table)
			return;
		
		var rows = this.table.getElements('tbody tr');
		rows.each(this.set_width, this);
		window.fireEvent('grid_columns_adjusted', this.table_id);
	},
	
	set_width: function(row)
	{
		if(!this.width_set)
		{
			var cells = row.getChildren();
	
			var last_index = cells.length-1;
			this.table_head_cells.each(function(head_cell, index){
				if (cells.length > index && index < last_index)
				{
					var tweak = -1;
	
					if (index == 0 && !Browser.Engine.gecko)
						tweak = 0;
	
					cells[index].setStyle('width', (head_cell.getSize().x + tweak) + 'px');
				}
			});
			this.width_set = true;
		}
	},
	
	assign_row_events: function(row)
	{
		var cells = row.getElements('td');
		var last_index = cells.length-1;
		cells.each(function(cell, index){
			var input = cell.getElement('input');
			if ($(input))
			{
				cell.addEvent('click', function(){input.focus()});
				$(input).addEvent('focus', this.on_input_focus.bind(this, [row, $(input)]));

				if (!Browser.Engine.webkit)
					$(input).addEvent('keypress', this.on_input_keydownn.bindWithEvent(this, [row, $(input)]));
				else
					$(input).addEvent('keydown', this.on_input_keydownn.bindWithEvent(this, [row, $(input)]));

				this.options.autocomplete_config.each(function(ac_params){
					if (ac_params.index == index)
						this.init_autocompleter($(input), ac_params, last_index == index ? null : cells[index + 1]);
				}, this);
			}
		}, this);
	},
	
	init_autocompleter: function(input, config, next_cell)
	{
		if (config.type == 'local')
		{
			var width_tweak = 0;
			
			if (next_cell)
				width_tweak += next_cell.getSize().x;
			else
				width_tweak = 2;
			
			if (Browser.Engine.gecko)
				width_tweak += 1;
				
			var width = config.autowidth ? input.getSize().x + 8 + width_tweak : 300;

			var common_options = {
				'minLength': 1,
				'overflow': true,
				'className': 'autocompleter-choices grid',
				'selectMode': 'selection',
				'width': width,
				'displayByArrows': false,
				minLength: 0,
				delay: 100,
				onShow: function(){
					this.autocompleter_visible = true;
				}.bind(this),
				onHide: function(){
					this.autocompleter_visible = false;
				}.bind(this)
			};

			if (!config.depends_on)
				new Autocompleter.Local(input, config.tokens, common_options);
			else
			{
				new Autocompleter.Request.Local(input, new Hash(config.tokens), $merge(
					common_options,
					{
						requestData: function(request, autocompleter)
						{
							var row = input.findParent('tr');
							var key = this.get_row_column_value(row, config.depends_on).toUpperCase();
							if (autocompleter.tokens.has(key))
								return autocompleter.tokens.get(key);
							
							return [];
						}.bind(this)
					}
				));
			}
		}
	},

	get_row_column_value: function(row, column_name)
	{
		var regex = RegExp("\\["+column_name+"\\]$");

		var inputs = row.getElements('input').filter(function(input){
			if (regex.test(input.name))
				return true;
		})
		
		if (inputs.length)
			return inputs[0].value;
			
		return '';
	},
	
	on_input_focus: function(row, input)
	{
		if ($type(row) == 'array')
		{
			input = row[1];
			row = row[0];
		}
		
		if (this.options.allow_adding_rows || this.options.allow_deleting_rows)
		{
			this.table.getElements('tbody tr').each(function(current_row){
				if (row != current_row)
					current_row.removeClass('current');
			});

			$(row).addClass('current');
		}

		this.current_row = $(row);
		this.fireEvent('onRowSelected', [input, this]);
		this.current_input = $(input);
		this.check_delete_status();
	},
	
	on_input_keydownn: function(event, row, input)
	{
		switch (event.key)
		{
			case 'right' :
				if (pos = input.getCaretPosition() == input.value.length)
					this.move_right(row, input);
			break;
			case 'left' :
				if (input.getCaretPosition() == 0)
					this.move_left(row, input);
			break;
			case 'up' :
				if (!event.alt)
					this.move_up(row, input);
			break;
			case 'down' :
				if (!event.alt)
					this.move_down(row, input);
			break;
			case 'tab' :
				this.scroller.update_position();
			break;
		}
	},
	
	insert_row: function()
	{
		if (!this.table)
			return;
		
		var table_body = this.table.getElement('tbody');
		var first_row = table_body.getFirst();
		if (!first_row)
		{
			alert('Cannot add a row to the empty table');
			return;
		}
		
		if (!this.current_row)
			var row = new Element('tr').inject(table_body);
		else
			var row = new Element('tr').inject(this.current_row, 'after');

		var first_input = null;

		var row_index = table_body.getChildren().length;
		var row_field_name = this.table_id+'_'+row_index+'_';
		
		var column_regexp = new RegExp("^"+this.table_id+"_[0-9]+_");

		first_row.getElements('td').each(function(cell_element, index){
			var input_element = cell_element.getElement('input');
			var column_name = input_element.get('name');
			var column = input_element.get('id').replace(column_regexp, "");

			var input_id = this.table_id + '_' + row_index + '_' + column;
			var new_name = column_name.replace(/\[[0-9]+\]\[[^\]]+\]$/, "") + "["+row_index+"]["+column+"]";

			var input = new Element('input', 
				{'name': new_name, 'id': input_id, 'type': 'text', 'autocomplete': 'off'}).inject(
					new Element('div', {'class': 'container'}).inject(new Element('td', {'class': cell_element.className}).inject(row)));

			if (!first_input)
				first_input = input;
		}, this);

		this.set_width(row);
		this.assign_row_events(row);
		first_input.focus();
		this.scroller.update();
		this.check_delete_status();
		this.scroller.update_position.delay(30, this.scroller);
		this.init_sortables();
		this.trigger_scroller();

		return false;
	},

	on_key_insert_row: function()
	{
		if (this.options.allow_adding_rows)
			this.insert_row();
	},

	delete_row: function()
	{
		if (!this.table)
			return;

		var row = this.current_row;
		if (!$(row))
			return;

		var prev_row = row.getPrevious();
		if (!prev_row)
			prev_row = row.getNext();
			
		if (!prev_row)
			return false;

		var non_empty_inputs = row.getElements('input').filter(function(current_input){
			return current_input.value.trim().length > 0;
		});
		
		if (non_empty_inputs.length)
		{
			if (!confirm('Do you really want to delete the current row?'))
				return false;
		}

		var current_input = this.current_input;

		this.current_input = null;
		this.current_row = null;
		
		if (current_input)
			this.move_to_row(row, current_input, prev_row);

		row.destroy();
		
		this.scroller.update();
		this.scroller.update_position();
		this.check_delete_status();
		this.init_sortables();
		this.trigger_scroller();
		this.width_set = false;
		var rows = this.table.getElements('tbody tr');
		if(rows)
			this.set_width(rows[0]);
	},
	
	on_key_delete_row: function()
	{
		if (this.options.allow_deleting_rows)
			this.delete_row();
	},
	
	check_delete_status: function()
	{
		if (this.table.getElements('tr').length > 1)
			this.fireEvent('deleteEnabled', this);
		else
			this.fireEvent('deleteDisabled', this);
	},
	
	init_toolbar: function()
	{
		if (!this.table)
			this.fireEvent('addDisabled', this);
	},
	
	/*
	 * Navigation
	 */
	
	move_right: function(row, input)
	{
		var next_cell = input.findParent('td').getNext();
		if (next_cell)
		{
			input = next_cell.getElement('input');
			if (input)
				input.focus();

			return;
		} else
		{
			var next_row = input.findParent('tr').getNext();
			if (next_row)
			{
				var cells = next_row.getChildren();
				if (cells.length > 0)
				{
					input = cells[0].getElement('input');
					if (input)
					{
						input.focus();
						this.scroller.update_position();
					}
				}
			}
		}
	},
	
	move_left: function(row, input)
	{
		var prev_cell = input.findParent('td').getPrevious();
		if (prev_cell)
		{
			input = prev_cell.getElement('input');
			if (input)
				input.focus();

			return;
		} else
		{
			var prev_row = input.findParent('tr').getPrevious();
			if (prev_row)
			{
				var cells = prev_row.getChildren();
				if (cells.length > 0)
				{
					input = cells[cells.length-1].getElement('input');
					if (input)
					{
						input.focus();
						this.scroller.update_position();
					}
				}
			}
		}
	},
	
	move_up: function(row, input)
	{
		if (this.autocompleter_visible)
			return;
		
		var prev_row = input.findParent('tr').getPrevious();
		if (prev_row)
			this.move_to_row(row, input, prev_row);
	},
	
	move_down: function(row, input)
	{
		if (this.autocompleter_visible)
			return;

		var next_row = input.findParent('tr').getNext();
		if (next_row)
			this.move_to_row(row, input, next_row);
	},
	
	move_to_row: function(from_row, input, to_row)
	{
		var to_cells = to_row.getChildren();
		var from_cells = from_row.getChildren();
		var from_cell = input.findParent('td');
		var index = from_cells.indexOf(from_cell);
		
		if (index <= to_cells.length-1)
		{
			var input = $(to_cells[index]).getElement('input');
			if (input)
				input.focus();
		}
		
		this.scroller.update_position();
	}
});
