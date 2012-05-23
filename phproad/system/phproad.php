<?php

	/**
	 * PHP Road
	 *
	 * PHP application framework
	 *
	 * @package		PHPRoad
	 * @author		Aleksey Bobkov, Andy Chentsov
	 * @since		Version 1.0
	 * @filesource
	 */

	/**
	 * Application Front Controller
	 *
	 * This scripts initializes the application and processes the request.
	 *
	 * @package		PHPRoad
	 * @category	PHPRoad
	 * @author		Aleksey Bobkov
	 */

	include 'initialize.php';

	/*
	 * Execute the requested action
	 */

	if ( !isset($Phpr_InitOnly) || !$Phpr_InitOnly )
		Phpr::$response->open( Phpr::$request->getCurrentUri(true) );
?>