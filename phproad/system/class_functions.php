<?php

	/*
	 * Common class functions
	 */

	function pass()
	{
		$args = func_get_args();
		return new Phpr_Closure( extractFunctionArg($args), $args );
	}

	function callFunction($function, $params)
	{
		if ( $function instanceof Phpr_Closure )
			return $function->call($params);

		return call_user_func_array($function, $params);
	}

	function extractFunctionArg(&$args, $offset = 0)
	{
		$cnt = count($args) - $offset;

		if ( $cnt == 0 ) return null;

		if ( is_string($args[$offset]) || is_array($args[$offset]) || (is_object($args[$offset]) && $args[$offset] instanceof Phpr_Closure) )
		{
			for ( $i = 0; $i <= $offset; $i++ )
				$lastObj = array_shift($args);

			return $lastObj;
		}

		if ( $cnt > 1 && is_object($args[$offset]) && is_string($args[$offset+1]) )
		{
			$lastObj = array($args[$offset], $args[$offset+1]);

			$newArgs = array();
			for ( $i = $offset+2; $i < count($args); $i++ )
				$newArgs[] = $args[$i];

			$args = $newArgs;
			return $lastObj;
		}

		return null;
	}

?>