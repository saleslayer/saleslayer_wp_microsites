<?php

define('SLYR__PLUGIN_DIR', dirname(__FILE__).'/');

include_once(SLYR__PLUGIN_DIR.'../../../wp-config.php');
include_once(SLYR__PLUGIN_DIR.'settings.php');

if (!class_exists('Softclear_API')) {
	require_once SLYR__PLUGIN_DIR.'admin/api/api_sc.php';
}

if(isset($_POST['endpoint'])) {

	$endpoint=addslashes($_POST['endpoint']);
	$return  =null;

	$page_permalink = '';
	
	if (isset($_POST['web_url'])){

		$url_params = explode('/', str_replace(home_url().'/', '', $_POST['web_url']));
			
		if (isset($url_params[0]) && !empty($url_params[0])){
		
			$page_permalink = $url_params[0];
		
		}

	}

	$page_url = '';

	if ($page_permalink != ''){

		$page_url = home_url().'/'.$page_permalink.'/';

	}

	$apiSC = new Softclear_API();
	
	switch ($endpoint) {

		case 'menu':

			$return = json_encode($apiSC->get_fast_menu(0, $page_url));

			break;

		case 'catalog':

			$id	= (isset($_POST['id'])) ? addslashes($_POST['id']) : 0;

			$return	= $apiSC->get_catalog($id, $page_url);

			break;

		case 'products':

			$id = (isset($_POST['id'])) ? addslashes($_POST['id']) : 0;

			$return = $apiSC->get_product_detail($id, $page_url);

			break;

		case 'refresh-data':

			$return = json_encode($apiSC->get_sync_data($_SESSION['slyr']['connector-id'], $_SESSION['slyr']['private-key']));

			break;

		case 'search_item':

			$search_value = (isset($_POST['search_value'])) ? addslashes($_POST['search_value']) : 0;
			
			$return = $apiSC->search_item($search_value, $page_url);

			break;

	}

	header('Content-Type: application/json');

	echo $return;

}