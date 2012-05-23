<?

	class Shop_TransactionUpdate
	{
		public $transaction_status_code;
		public $transaction_status_name;
		
		public function __construct($transaction_status_code, $transaction_status_name)
		{
			$this->transaction_status_code = $transaction_status_code;
			$this->transaction_status_name = $transaction_status_name;
		}
	}

?>