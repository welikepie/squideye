<?

	class Backend_CsvImportModel extends Db_ActiveRecord
	{
		public $table_name = 'core_configuration_records';

		public $has_many = array(
			'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Backend_CsvImportModel'", 'order'=>'id', 'delete'=>true),
			'config_import'=>array('class_name'=>'Db_File', 'foreign_key'=>'master_object_id', 'conditions'=>"master_object_class='Backend_CsvImportModel'", 'order'=>'id', 'delete'=>true)
		);

		public function define_columns($context = null)
		{
			$this->define_multi_relation_column('csv_file', 'csv_file', 'CSV File', '@name')->invisible();
			$this->define_multi_relation_column('config_import', 'config_import', 'Column configuration ', '@name')->invisible();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('csv_file', 'left')->renderAs(frm_file_attachments)->renderFilesAs('single_file')->addDocumentLabel('Upload a file')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noAttachmentsLabel('');
			$this->add_form_field('config_import', 'left')->renderAs(frm_file_attachments)->renderFilesAs('single_file')->addDocumentLabel('Upload a file')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noAttachmentsLabel('');
		}
		
		public function import_csv_data($data_model, $session_key, $column_map, $import_manager, $delimeter, $first_row_titles)
		{
		}
	}

?>