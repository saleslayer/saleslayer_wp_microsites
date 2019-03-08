<?php 

    ini_set('display_errors', 0);
    error_reporting(E_ALL ^ E_NOTICE);


    define('SLYR_version',       1.6);

    define('SLYR_name',          'Sales Layer');

    define('SLYR_name_icon',     'saleslayer_icon.png');
    define('SLYR_name_logo',     'logo_head_saleslayer.png');
/*
    define('SLYR_name_icon',     'connector_icon.png');
    define('SLYR_name_logo',     'logo_head_connector.png');
*/
	define('SLYR_short_code',    'saleslayer_catalog');

	define('SLYR_connector_id',  'slyr_connector_id');
	define('SLYR_connector_key', 'slyr_connector_key');

    define('SLYR_url_API',       'api.saleslayer.com/');

	// Avoids wordpress to ask for credentials when testing on localhost
	if (!defined('FS_METHOD')) define('FS_METHOD',	'direct');

	// Constructs plugin dirname:

	if (function_exists('plugin_dir_path')) {

		$dirname=explode('/', str_replace('\\', '/', plugin_dir_path( __FILE__ )));

		define('PLUGIN_NAME_DIR', $dirname[count($dirname)-2]);

	}
