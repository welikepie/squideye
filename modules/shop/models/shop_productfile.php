<?

	class Shop_ProductFile extends Db_File
	{
		public static function create($values = null)
		{
			return new self();
		}
		
		public function __get($name)
		{
			if ($name == 'size_str')
				return Phpr_Files::fileSize($this->size);
				
			return parent::__get($name);
		}

		public function download_url($order, $mode = null)
		{
			if (!$mode || ($mode != 'inline' && $mode != 'attachment'))
				return root_url('download_product_file/'.$this->id.'/'.$order->order_hash.'/'.$this->name);
			else
				return root_url('download_product_file/'.$this->id.'/'.$order->order_hash.'/'.$mode.'/'.$this->name);
		}
	}

?>