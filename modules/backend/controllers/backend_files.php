<?

	class Backend_Files extends Backend_Controller
	{
		public function get($id)
		{
			$this->suppressView();
			try
			{
				$file = Db_File::create()->find($id);
				if ($file)
					$file->output();
			} catch (exception $ex)
			{
				echo $ex->getMessage();
			}
		}
	}

?>