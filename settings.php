<?php
if (! Defined ('ABSPATH')) exit;

define('SLYR_version', 1.7);

define('SLYR_name', 'Sales Layer');

define('SLYR_name_icon', 'saleslayer_icon.png');
define('SLYR_name_logo', 'logo_head_saleslayer.png');

define('SLYR_short_code', 'saleslayer_catalog');

define('SLYR_connector_id', 'slyr_connector_id');
define('SLYR_connector_key', 'slyr_connector_key');

define('SLYR_url_API', 'api.saleslayer.com/');

// Avoids wordpress to ask for credentials when testing on localhost
if (!defined('FS_METHOD')) {
    define('FS_METHOD', 'direct');
}
// Constructs plugin dirname:

if (function_exists('plugin_dir_path')) {

    $dirname = explode('/', str_replace('\\', '/', plugin_dir_path(__FILE__)));
    define('PLUGIN_NAME_DIR', $dirname[count($dirname) - 2]);

}
