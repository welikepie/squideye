<?

	class Backend_Events extends Phpr_Extensible
	{
		public $implement = 'Phpr_Events';
		
		function __destruct() 
		{
			$this->fireEvent('core:onUninitialize');
		}
	}

?>