<?php


	/*
	 * PHP Road application bootstrap script
	 */

	error_reporting( E_ALL );
	ini_set( 'display_errors', true );

	/*
	 * This variable contains a path to this file.
	 */

	$bootstrapPath = __FILE__;

	/*
	 * Specify the application directory root
	 *
	 * Leave this variable blank if application root directory matches the site root directory.
	 * Otherwise specify an absolute path to the application root, for example:
	 * $applicationRoot = realpath( dirname($bootstrapPath)."/../app" );
	 *
	 */

	$applicationRoot = "";

	/*
	 * Define a path to the Control Center and use this path in the Control Center address.
	 * For example, if you specify the "secretgate", use the http://www.your_cool_domain.com/secretgate.
	 */

	include 'config/config.php';
	
	/*
	 * Detect resource request
	 */
	
	if (array_key_exists('q', $_GET) && (strpos($_GET['q'], 'ls_javascript_combine/') !== false || strpos($_GET['q'], 'ls_css_combine/') !== false))
	{
		include( "phproad/system/combine_resources.php" );
		
		die();
	}

	/*
	 * Detect CLI
	 */

	function ls_detect_command_line_interface()
	{
		$sapi = php_sapi_name();
	
		if ($sapi == 'cli')
			return true;
	
		// if (array_key_exists('SHELL', $_SERVER) && strlen($_SERVER['SHELL']))
		// 	return true;
		
		if (!array_key_exists('DOCUMENT_ROOT', $_SERVER) || !strlen($_SERVER['DOCUMENT_ROOT']))
			return true;

		return false;
	}
	
	/*
	 * Detect the CLI update argument
	 */
	
	$ls_cli_update_flag = false;
	$ls_cli_force_update = false;
	$ls_cli_mode = ls_detect_command_line_interface();

	if ($ls_cli_mode)
	{
		if (isset($_SERVER["argv"]))
		{
			foreach ($_SERVER["argv"] as $argument)
			{
				if ($argument == '--update')
					$ls_cli_update_flag = true;
					
				if ($argument == '--force')
					$ls_cli_force_update = true;
			}
		}
	}
	
	if ($ls_cli_mode)
	{
		global $Phpr_NoSession;
		global $Phpr_InitOnly;
		
		$Phpr_NoSession = true;
		$Phpr_InitOnly = true;

		$APP_CONF = array();
		$APP_CONF['ERROR_LOG_FILE'] = dirname(__FILE__).'/logs/cli_errors.txt';
		$APP_CONF['NO_TRACELOG_CHECK'] = true;
	}

	/*
	 * Include the PHP Road library
	 *
	 * You may need to specify a full path to the phproad.php script, 
	 * in case if the PHP Road root directory is not specified in the PHP includes path.
	 *
	 */
	include( "phproad/system/phproad.php" );
	
	if ($ls_cli_update_flag)
	{
		Core_Cli::authenticate();
		Core_UpdateManager::create()->cli_update($ls_cli_force_update);
	}
?>