<?php

	class Backend_CsvImport extends Phpr_ControllerBehavior
	{
		public $csv_import_file_columns_header = '';
		public $csv_import_db_columns_header = '';
		public $csv_import_config_model_class = 'Backend_CsvImportModel';
		public $csv_import_data_model_class = '';
		public $csv_import_name = '';
		public $csv_import_short_name = '';
		public $csv_import_url = '';

		protected $model_obj = null;
		protected $data_model_obj = null;
		
		public function __construct($controller)
		{
			parent::__construct($controller);
			
			$this->_controller->addJavaScript('/modules/backend/behaviors/backend_csvimport/resources/javascript/csvimport.js?'.module_build('backend'));
			$this->_controller->addCss('/modules/backend/behaviors/backend_csvimport/resources/css/csvimport.css');
			
			$this->hideAction('csvImportRenderPartial');
			$this->hideAction('csvImportRenderColumnConfiguration');
			$this->hideAction('csvImportRenderCsvUploader');
			$this->hideAction('csvImportGetModelObj');
			$this->hideAction('csvImportGetViewColumnName');
			$this->hideAction('csvImportIsRequired');
			$this->hideAction('csvImportIsNameMatch');
			$this->hideAction('csvImportDbColumnPresented');
			$this->hideAction('csvImportGetCsvFileHandle');

			$this->addEventHandler('onCsvFileUploaded');
			$this->addEventHandler('onCsvShowColumnData');
			$this->addEventHandler('onCsvFirstRowUpdated');
			$this->addEventHandler('onCsvSaveConfiguration');
			$this->addEventHandler('onCsvShowLoadConfigForm');
			$this->addEventHandler('onCsvConfigFileUploaded');
			$this->addEventHandler('onCsvShowImportForm');
			$this->addEventHandler('onCsvImport');
			
			if (!$this->_controller->csv_import_name)
				throw new Phpr_SystemException("CSV import name (csv_import_name) value is not defined in the ".get_class($this->_controller).' class.');
				
			if (!$this->_controller->csv_import_short_name)
				throw new Phpr_SystemException("CSV import short name (csv_import_short_name) value is not defined in the ".get_class($this->_controller).' class.');

			$this->cleanup_export();
		}
		
		public function csvImportRenderPartial($view, $params = array())
		{
			$this->_controller->form_model_class = $this->_controller->csv_import_config_model_class;
			$this->renderPartial($view, $params);
		}
		
		public function csvImportRenderColumnConfiguration()
		{
			$this->renderPartial('column_configuration');
		}

		public function csvImportRenderCsvUploader()
		{
			$this->_controller->form_unique_prefix = 'csv_import';
			$this->_controller->form_model_class = $this->_controller->csv_import_config_model_class;
			$this->viewData['form_model'] = $this->_controller->formCreateModelObject();
			$this->viewData['form_session_key'] = $this->_controller->formGetEditSessionKey();
			$this->renderPartial('csv_uploader');
		}
		
		public function csvImportGetModelObj()
		{
			if ($this->model_obj)
				return $this->model_obj;
				
			$this->model_obj = new $this->_controller->csv_import_config_model_class();
			$this->model_obj->init_columns_info();
			$this->model_obj->define_form_fields();
			
			return $this->model_obj;
		}
		
		public function csvImportGetDataModelObj()
		{
			if ($this->data_model_obj)
				return $this->data_model_obj;
				
			$this->data_model_obj = new $this->_controller->csv_import_data_model_class();
			$this->data_model_obj->init_columns_info();
			$this->data_model_obj->define_form_fields();
			
			return $this->data_model_obj;
		}

		public function onCsvFileUploaded()
		{
			$file_message_id = 'file_custom_message_'.$this->_controller->csv_import_config_model_class.'_csv_file';
			
			try
			{
				$this->_controller->preparePartialRender('file_columns_scroller');
				$this->viewData['file_columns'] = $this->get_file_columns();
				$this->renderPartial('file_columns');
				
				$this->_controller->preparePartialRender('db_columns_scroller');
				$this->renderPartial('db_columns');
			}
			catch (Exception $ex)
			{
				$this->_controller->renderMultiple(array(
					$file_message_id=>'<span class="csv_file_error">'.$ex->getMessage().'</span>'
				));
			}
		}
		
		public function csvImportGetDbColumns()
		{
			$model = $this->csvImportGetDataModelObj();
			if (!method_exists($model, 'get_csv_import_columns'))
				throw new Phpr_SystemException('Method get_csv_import_columns() is not defined in the '.get_class($model).' class.');
			
			$columns = $model->get_csv_import_columns();
//			ksort($columns);
			
			return $columns;
		}

		public function csvImportGetViewColumnName($column)
		{
			if (!mb_strlen($column))
				return $column;
			
			$column = mb_strtoupper(mb_substr($column, 0, 1)).mb_substr($column, 1);
			return str_replace('_', ' ', $column);
		}

		public function csvImportIsRequired($column)
		{
			$model = $this->csvImportGetDataModelObj();
			$rules = $model->validation->getRule($column->dbName);
			if (!$rules)
				return false;

			return $rules->required;
		}

		public function onCsvShowColumnData()
		{
			$handle = null;

			try
			{
				$column_index = post('import_csv_preview_field_index');
				if (!strlen($column_index))
					throw new Phpr_ApplicationException('Unknown column index');

				$columns = $this->get_file_columns();
				if (!array_key_exists($column_index, $columns))
					throw new Phpr_ApplicationException('Unknown column');

				$file = $this->get_file_path();
				$delimeter = $this->get_delimiter($file);

				$handle = $this->get_file_handle($file);
				
				$column_data = array();
				$skip_first_row = post('first_row_titles');
				$counter = 0;

				while (($data = fgetcsv($handle, 10000, $delimeter)) !== FALSE && $counter < 20) 
				{
					if (count($data) > 0)
					{
						if ($skip_first_row)
							$skip_first_row = false;
						else
						{
							if (array_key_exists($column_index, $data))
							{
								$value = $data[$column_index];
								if ($value)
								{
									$column_data[] = Phpr_Html::strTrim($value, 100);
									$counter++;
								}
							}
						}
					}
				}

				$this->viewData['column_data'] = $column_data;
				$this->viewData['column_data_rows'] = count($column_data);
				$this->viewData['column_name'] = post('first_row_titles') ? $columns[$column_index] : 'Column #'.($column_index+1);
			}
			catch (Exception $ex)
			{
				if ($handle)
					@fclose($handle);

				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('data_preview_form');
		}
		
		public function onCsvFirstRowUpdated()
		{
			if (!$this->get_file_path(false))
				return;

			$this->onCsvFileUploaded();
		}

		public function onCsvSaveConfiguration()
		{
			try
			{
				$matches = post('column_match', array());
				if (!$matches)
					throw new Phpr_ApplicationException('Please define column matches first.');

				$this->check_required_fields();

				$hidden_columns = array();
				$all_hidden_columns = post('hidden_column', array());
				foreach ($all_hidden_columns as $key=>$value)
				{
					if ($value)
						$hidden_columns[] = $key;
				}

				$data = array();
				$data['first_row_titles'] = post('first_row_titles');
				$data['data_class'] = $this->_controller->csv_import_data_model_class;
				$data['import_name'] = $this->_controller->csv_import_name;
				$data['ignored_columns'] = $hidden_columns;
				$data['column_matches'] = $matches;

				$data = (object)$data;
				$key = time();
				$tmp_obj_name = 'csvimpcfg_'.mb_strtolower($this->_controller->csv_import_data_model_class).'_'.$key.'.exp';
				if (!@file_put_contents(PATH_APP.'/temp/'.$tmp_obj_name, serialize($data)))
					throw new Phpr_ApplicationException('Error creating data file');

				Phpr::$response->redirect($this->_controller->csv_import_url.'import_csv_get_config/'.$key);
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}

		public function import_csv_get_config($key)
		{
			try
			{
				$this->_controller->app_page_title = 'Download CSV Import configuration';
				
				if (!preg_match('/^[0-9]+$/', $key))
					throw new Phpr_ApplicationException('File not found');

				$tmp_obj_name = 'csvimpcfg_'.mb_strtolower($this->_controller->csv_import_data_model_class).'_'.$key.'.exp';
				$path = PATH_APP.'/temp/'.$tmp_obj_name;

				if (!file_exists($path))
					throw new Phpr_ApplicationException('File not found');

				header("Content-type: application/octet-stream");
				header('Content-Disposition: inline; filename="csv_import_config_'.mb_strtolower($this->_controller->csv_import_short_name).'.icf"');
				header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Cache-Control: pre-check=0, post-check=0, max-age=0');
				header('Accept-Ranges: bytes');
				header('Content-Length: '.filesize($path));
				header("Connection: close");

				Phpr_Files::readFile($path);
				@unlink($path);
			
				$this->_controller->suppressView();
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}
		}

		public function onCsvShowLoadConfigForm()
		{
			$this->_controller->form_unique_prefix = 'csv_import';
			$this->_controller->form_model_class = $this->_controller->csv_import_config_model_class;
			$model = $this->viewData['form_model'] = $this->_controller->formCreateModelObject();
			$session_key = $this->viewData['form_session_key'] = $this->_controller->formGetEditSessionKey();
			
			$files = $model->list_related_records_deferred('config_import', $session_key);
			foreach ($files as $existing_file)
				$model->config_import->delete($existing_file, $session_key);

			$this->renderPartial('load_configuration_form');
		}
		
		public function onCsvConfigFileUploaded()
		{
			try
			{
				$file_path = $this->get_file_path(true, 'config_import');
				$contents = @file_get_contents($file_path);
				if (!$contents)
					throw new Phpr_SystemException('Unable to load the uploaded file');

				$config_object = null;
				try
				{
					$config_object = @unserialize($contents);
				} catch (Exception $ex){}
				
				if (!$config_object || !is_object($config_object))
					throw new Phpr_ApplicationException('The uploaded file is not a valid column configuration file.');
				
				if ($config_object->data_class != $this->_controller->csv_import_data_model_class)
					throw new Phpr_ApplicationException('The uploaded file is not suitable for the '.mb_strtolower($this->_controller->csv_import_name).'.');

				if ($config_object->first_row_titles && !(post('first_row_titles')))
					throw new Phpr_ApplicationException('The uploaded configuration can be used only for CSV files with a first row containing column titles. According to your configuration, the first CSV file row contains data.');

				if (!$config_object->first_row_titles && (post('first_row_titles')))
					throw new Phpr_ApplicationException('The uploaded configuration can be used only for CSV files with a first row containing data. According to your  configuration, the first CSV file row contains column titles.');

				$this->viewData['hidden_columns'] = $config_object->ignored_columns;
				$this->viewData['column_matches'] = $config_object->column_matches;
				
				$this->_controller->preparePartialRender('file_columns_scroller');
				$this->viewData['file_columns'] = $this->get_file_columns();
				$this->renderPartial('file_columns');
				
				$this->_controller->preparePartialRender('db_columns_scroller');
				$this->renderPartial('db_columns');
			}
			catch (Exception $ex)
			{
				Phpr::$response->ajaxReportException($ex, true, true);
			}
		}
		
		public function csvImportIsNameMatch($column_name, $column_index, $value)
		{
			$value = explode('|', $value);
			if (count($value) != 2)
				return false;
				
			$value_index = $value[0];
			$value_name = $value[1];

			$titles = post('first_row_titles');
			if ($titles)
				return $value_name == $column_name;
				
			return $value_index == $column_index;
		}
		
		public function onCsvShowImportForm()
		{
			try
			{
				$this->check_required_fields();
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('import_form');
		}
		
		public function onCsvImport()
		{
			try
			{
				$data_model = $this->csvImportGetDataModelObj();
				$config_obj = $this->csvImportGetModelObj();
				$config_obj->set_data(post($this->_controller->csv_import_config_model_class, array()));
				$indexed_match = $this->column_index_match();
				$delimeter = $this->get_delimiter($this->get_file_path());
				$first_row_titles = post('first_row_titles');

				$import_data = $config_obj->import_csv_data($data_model, $this->_controller->formGetEditSessionKey(), $indexed_match, $this, $delimeter, $first_row_titles);
				$this->viewData['import_details'] = $import_data;
			}
			catch (Exception $ex)
			{
				$this->_controller->handlePageError($ex);
			}

			$this->renderPartial('import_result_form');
		}

		public function csvImportDbColumnPresented(&$matches, $column_db_name)
		{
			foreach ($matches as $column_names)
			{
				if (in_array($column_db_name, $column_names))
					return true;
			}
			
			return false;
		}

		public function csvImportGetCsvFileHandle()
		{
			$path = $this->get_file_path();
			return $this->get_file_handle($path);
		}

		public function csvImportBoolValue($value)
		{
			$value = mb_strtolower($value);

			if ($value == 1 || $value == 'enabled' || $value == 'y' || $value == 'yes' || $value == 'active' || $value == 'true')
				return true;
				
			return false;
		}

		public function csvImportFloatValue($value)
		{
			$value = str_replace(' ', '', $value);
			$value = str_replace(',', '', $value);

			return $value;
		}
		
		public function csvImportNumericValue($value)
		{
			$value = str_replace(' ', '', $value);
			$value = round(str_replace(',', '', $value));

			return $value;
		}

		/*
		 * Private functions
		 */
		
		protected function check_required_fields()
		{
			$matches = post('column_match', array());
			if (!$matches)
				throw new Phpr_ApplicationException('Please define column matches first.');
				
			$db_columns = $this->csvImportGetDbColumns();
			foreach ($db_columns as $column)
			{
				if ($this->csvImportIsRequired($column))
				{
					$found = false;
					foreach ($matches as $column_name=>$db_names)
					{
						if (in_array($column->dbName, $db_names))
						{
							$found = true;
							break;
						}
					}
					
					if (!$found)
						throw new Phpr_ApplicationException('Please specify a matching column for the "'.$column->displayName.'" required column.');
				}
			}
		}
		
		protected function cleanup_export()
		{
			$files = @glob(PATH_APP.'/temp/csvimpcfg_*.exp');
			if (is_array($files) && $files)
			{
				foreach ($files as $filename) 
				{
					$matches = array();
					if (preg_match('/([0-9]+)\.exp$/', $filename, $matches))
					{
						if ((time()-$matches[1]) > 60)
							@unlink($filename);
					}
				}
			}
		}

		protected function get_file_path($throw_exception = true, $field_name = 'csv_file')
		{
			$model = $this->csvImportGetModelObj();
			$files = $model->list_related_records_deferred($field_name, $this->_controller->formGetEditSessionKey());
			if (!$files->count)
			{
				if ($throw_exception)
					throw new Phpr_ApplicationException('File is not uploaded');
				else
					return null;
			}
				
			$file = PATH_APP.$files[0]->getPath();
			if (!file_exists($file))
			{
				if ($throw_exception)
					throw new Phpr_ApplicationException('Unable to open the uploaded file');
				else
					return null;
			}
				
			return $file;
		}
		
		protected function get_delimiter($file)
		{
			$delimeter = Phpr_Files::determineCsvDelimeter($file);
			if (!$delimeter)
				throw new Phpr_ApplicationException('Unable to detect a delimiter');
				
			return $delimeter;
		}
		
		protected function get_file_handle($file)
		{
			$handle = @fopen($file, "r");
			if (!$handle)
				throw new Phpr_ApplicationException('Unable to open the uploaded file');
				
			return $handle;
		}

		protected function get_file_columns()
		{
			$file = $this->get_file_path();

			$handle = null;

			try
			{
				$delimeter = $this->get_delimiter($file);

				$handle = $this->get_file_handle($file);
				$columns = array();
				$counter = 1;

				while (($data = fgetcsv($handle, 10000, $delimeter)) !== FALSE && $counter < 10) 
				{
					if (count($data) > 1)
					{
						$columns = $data;
						break;
					}

					$counter++;
				}

				if (!$columns || count($columns) == 1)
					throw new Phpr_ApplicationException('Uploaded file is not a valid CSV file');

				// if (post('first_row_titles'))
				// 	asort($columns);

				return $columns;
			} catch (Exception $ex)
			{
				if ($handle)
					@fclose($handle);
				
				throw $ex;
			}
		}

		protected function column_index_match()
		{
			$match_data = post('column_match', array());
			$result = array();

			foreach ($match_data as $index_name=>$db_columns)
			{
				$parts = explode('|', $index_name);
				if (count($parts) < 2)
					continue;
					
				$result[$parts[0]] = $db_columns;
			}
			
			return $result;
		}
	}

?>