<?php
	class Phpr_Closure
	{
		private $_function;
		private $_params;

		public function __construct($function, $params)
		{
			$this->_function = $function;
			$this->_params = $params;
		}

		public function call($params)
		{
			$methodParams = $params;
			foreach ($this->_params as $param)
				array_push($methodParams, $param);

			call_user_func_array($this->_function, $methodParams);
		}
	}
?>