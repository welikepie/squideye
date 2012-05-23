<?

	class Db_CsvFileImport extends Db_ActiveRecord
	{
		public $table_name = 'db_files';

		public $has_many = array(
			'csv_file'=>array('class_name'=>'Db_File', 'foreign_key'=>'id', 'conditions'=>"master_object_class='Db_CsvFileImport'", 'order'=>'id', 'delete'=>true)
		);

		public function define_columns($context = null)
		{
			$this->define_multi_relation_column('csv_file', 'csv_file', 'CSV file ', '@name')->invisible();
		}

		public function define_form_fields($context = null)
		{
			$this->add_form_field('csv_file', 'left')->renderAs(frm_file_attachments)->renderFilesAs('single_file')->addDocumentLabel('Upload a file')->fileDownloadBaseUrl(url('ls_backend/files/get/'))->noAttachmentsLabel('');
		}

	}

?>