<?php
/**
 *
 * Created by J. Martín Amarís.
 *
 * CreativeCommons License Attribution (By):
 * http://creativecommons.org/licenses/by/4.0/
 *
 * Softclear API class is a library for use SalesLayer API
 *
 * @modified 2018-10-01
 * @version 1.1
 *
 */

if (!class_exists('SalesLayer_Conn'))    require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'SalesLayer-Conn.php';
if (!class_exists('SalesLayer_Updater')) require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'SalesLayer-Updater.php';

class Softclear_API {

	const slyr_connector_id  = SLYR_connector_id;
	const slyr_connector_key = SLYR_connector_key;
	const slyr_catalog       = 'catalogue';
	const slyr_products      = 'products';
	public $debug_on         = false;
	public $debug_filepath;
	public $slyr_updater_table_prefix; 
	public $updater;

	public function __construct() {

		$this::_construct();
		$this->debug_filepath = dirname(__FILE__).DIRECTORY_SEPARATOR.'_debbug_'.date('Y-m-d_H-i-s').'.log';
	}

	protected function _construct() {

		$this->updater = new SalesLayer_Updater(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST);
		$this->slyr_updater_table_prefix = $this->updater->table_prefix;
	}

	public function debug ($string) {

		if ($this->debug_on) {

			file_put_contents($this->debug_filepath, '['.microtime ()."] $string\n", FILE_APPEND);
		}	
	}

	/**
	 * Connect to SalesLayer API Server. 
	 *
	 * @param  string $connectorId Sales Layer Connector Identification key
	 * @param  string $secretKey Sales Layer Secret key
	 * @return SalesLayer_Updater
	 */
	private function connect_saleslayer($connectorId = null, $secretKey = null) {

		if ($connectorId == null) {

            $connectorId = get_option(SLYR_connector_id);
            $secretKey   = get_option(SLYR_connector_key);

		}

		// Instantiate the class
		if (is_null($this->updater)){

			$this->updater = new SalesLayer_Updater(DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $connectorId, $secretKey);

	        $this->updater->set_URL_connection(SLYR_url_API);


		}else{

			if ($this->updater->get_identification_code() != $connectorId){

				$this->updater->set_identification($connectorId, $secretKey);

			}

		}

	}

	/**
	 * Build connection error information array. 
	 *
	 * @param obj SalesLayer connection
	 * @return array connection error information
	 */
	private function error_connect_saleslayer() {
		$error_array = array();
		$error_array['type']    = SLYR_name;
		$error_array['code']    = $this->updater->get_response_error();
		$error_array['message'] = $this->updater->get_response_error_message();
		return array('error' => $error_array);
	}

	/**
	 * Connect to SalesLayer and return the objects to be synchronized
	 *
	 * @param string $sl_connector_id Sales Layer Connector Identification key
	 * @param string $sl_secret_key Sales Layer Secret key
     * @param string $refresh_data refresh data
	 * @return array synchronization information
	 */
	public function get_sync_data ($sl_connector_id, $sl_secret_key, $refresh_data=1) {

		self::connect_saleslayer($sl_connector_id, $sl_secret_key);

        $url_push = preg_replace('/admin\/api\/$/i', '', plugin_dir_url(__FILE__)).'get_notices.php';

		if (!preg_match('/^http(s)?:\/\//i', $url_push)) $url_push = "http://$url_push";

        $this->updater->update(array('compression' => 1, 'url_push' => $url_push), 'CN_WP', $refresh_data);

		if ($this->updater->has_response_error()) { return self::error_connect_saleslayer(); }

        update_option(self::slyr_connector_id,  $sl_connector_id);
        update_option(self::slyr_connector_key, $sl_secret_key);

		$get_response_table_data = $this->updater->get_response_table_data();

		$sync_data = array();
		
		if (is_array($get_response_table_data)) {

			$indexes = array('modified', 'deleted');

			foreach($get_response_table_data as $table_name => $table_data) {

				foreach ($indexes as $index) {
					
					if (isset($table_data[$index]) && count($table_data[$index]) > 0){

						if (!isset($sync_data[$table_name])){ $sync_data[$table_name] = array(); }
						$sync_data[$table_name][$index] = count($table_data[$index]);

					}
				}			
			}
		}

        $this->updater->print_debug();

		return $sync_data;
	}

	/**
	 * Returns the subcategories or products (as corresponds) array from 
	 * category or subcategory identifier provided
	 *
	 * @param string $idCategory Sales Layer category (or subcategory) identifier
	 * @param string $page_url page url to load the href
	 * @return array subcategories or products (as corresponds) array
	 */
	public function get_catalog ($idCategory = 0, $page_url = '') {

		self::connect_saleslayer();
		$field_cat_id        = $this->updater->get_db_field_ID       (self::slyr_catalog);
		$field_cat_parent_id = $this->updater->get_db_field_parent_ID(self::slyr_catalog);
        $language            = $this->updater->get_default_language();
		
		if ($idCategory != 0) {
			
			$fields     = array($field_cat_id, 'section_name');
			$conditions = array(
				array(
					'field'    => $field_cat_id,
					'condition'=> '=',
					'value'    => $idCategory
				),
			);
			$result = $this->updater->extract(self::slyr_catalog, $fields, $language, $conditions, 1);

			if ($this->debug_on) $this->debug("get_catalog($idCategory) DB catalog result: ".print_r($result, 1));

	        if ($this->updater->has_response_error()) {

				return self::error_connect_saleslayer();

			} else if (empty($result)) {

				return 0;
			}	
		}
		
		/*** CREAMOS EL BREADCRUMB ***/
		$breadcrumb = self::get_breadcrumb($idCategory);
		
		/*** BUSCAMOS LAS CATEGORIAS ***/
		//Buscamos los hijos de ese padre
		$fields     = array($field_cat_id, $field_cat_parent_id, 'section_name', 'section_image');
		$conditions = array(
			array(
				'field'     => $field_cat_parent_id,
				'condition' => '=',
				'value'     => $idCategory
			),
		);

		$arrayReturn               = array();
		$arrayReturn['breadcrumb'] = $breadcrumb;
		
		$result = $this->updater->extract(self::slyr_catalog, $fields, $language, $conditions, 1);

		if ($this->debug_on) $this->debug("get_catalog($idCategory) DB catalog childs result: ".print_r($result, 1));

		if ($this->updater->has_response_error()) {
			return self::error_connect_saleslayer();
		} else { 
			$catalog = array();
			if (count($result)) {
				foreach ($result as $row) {
					$distint_img_array = array();
					if (count($row['section_image'])) {
						foreach ($row['section_image'] as $imgs) {
							$distint_img_array[] = $imgs['THM'];
							break;
						}
					}
					$row['section_image'] = $distint_img_array;
					$catalog[]            = $row;
				}
				unset($row);
			}
			$arrayReturn["categories"] = $catalog;
		}

		/*** BUSCAMOS LOS PRODUCTOS ***/
		$field_prd_id = $this->updater->get_db_field_ID(self::slyr_products);
		$fields       = array($field_prd_id, 'product_name', 'product_image');
		$conditions   = array(
			array(
				'field'    => $field_cat_id,
				'condition'=> '=',
				'value'    => $idCategory
			),
		);
		
		$result = $this->updater->extract(self::slyr_products, $fields, $language, $conditions, 1);

		if ($this->debug_on) $this->debug("get_catalog($idCategory) DB product result: ".print_r($result, 1));

		if ($this->updater->has_response_error()) {
			return self::error_connect_saleslayer();
		} else { 
			$products = array();
			if (count($result)) {
				foreach ($result as &$row) {
					$distint_img_array = array();
					if(count($row['product_image'])){
						foreach ($row['product_image'] as $imgs) {
							$distint_img_array[]= $imgs['THM'];
							break;
						}
					}
                    $row['product_image'] = $distint_img_array;
					$products[]           = $row;
				}
				unset($row);
			}
			$arrayReturn['products'] = $products;
		}

		if ($page_url != '') {

			$arrayReturn['categories_display'] = array(0);
			
			if (!empty($arrayReturn["breadcrumb"])) {

				foreach ($arrayReturn["breadcrumb"] as $keyARB => $ar_breadcrumb) {
					
					$arrayReturn["breadcrumb"][$keyARB]['category_url'] = $page_url.'c'.$ar_breadcrumb['ID'].'/'.sanitize_title($ar_breadcrumb['section_name']).'/';
					
					if ($ar_breadcrumb[$field_cat_parent_id] == 0) {

						$arrayReturn["breadcrumb"][$keyARB]['category_path_display'] = array($ar_breadcrumb['ID']);
						if ($idCategory == $ar_breadcrumb['ID']){ 
							$arrayReturn['categories_display'] = array_unique(array_merge($arrayReturn['categories_display'],array($ar_breadcrumb['ID']))); 
						}
						$arrayReturn["breadcrumb"][$keyARB]['category_path'] = $page_url.$ar_breadcrumb['ID'].'/'.sanitize_title($ar_breadcrumb['section_name']);
						
					} else {

						$arrayReturn["breadcrumb"][$keyARB]['category_path'] = $page_url;
					
						$counter     = 0;
						$prev_parent = $ar_breadcrumb[$field_cat_parent_id];
						$path        = '';

						if (!empty($breadcrumb)) {

							do {

								foreach ($breadcrumb as $keyB => $bc) {
									
									if ($bc['ID'] == $ar_breadcrumb['ID']) continue;
									if ($bc['ID'] == $prev_parent) {

										$counter = 0;
										if ($path == '') {
											
											$path = $bc['ID'].'/'; 
											
										} else {

											$path = $bc['ID'].'/'.$path; 
										}
					
										if ($bc[$field_cat_parent_id] == 0){

											break 2;

										} else {

											$prev_parent = $bc[$field_cat_parent_id];
										}
									}
								}

								$counter++;

								if ($counter == 3){

									break;
								}

							} while ($prev_parent != 0);
						}

						$arrayReturn["breadcrumb"][$keyARB]['category_path_display'] = explode('/', $path.$ar_breadcrumb['ID']);
						if ($idCategory == $ar_breadcrumb['ID']){ 
							$arrayReturn['categories_display'] = array_unique(array_merge($arrayReturn['categories_display'],explode('/', $path.$ar_breadcrumb['ID']))); 
						}
						$arrayReturn["breadcrumb"][$keyARB]['category_path'] .= $path.$ar_breadcrumb['ID'].'/'.sanitize_title($ar_breadcrumb['section_name']);
					
					}	
				}
			}

			if (!empty($arrayReturn["categories"])) {

				foreach ($arrayReturn["categories"] as $keyARC => $category) {
					
					$arrayReturn["categories"][$keyARC]['category_url'] = $page_url.'c'.$category['ID'].'/'.sanitize_title($category['section_name']).'/';
				
					if ($category[$field_cat_parent_id] == 0){

						$arrayReturn["categories"][$keyARC]['category_path_display'] = array($category['ID']);
						if ($idCategory == $category['ID']){ 
							$arrayReturn['categories_display'] = array_unique(array_merge($arrayReturn['categories_display'],array($category['ID']))); 
						}
						$arrayReturn["categories"][$keyARC]['category_path'] = $page_url.$category['ID'].'/'.sanitize_title($category['section_name']);

					} else {

						$arrayReturn["categories"][$keyARC]['category_path'] = $page_url;

						$counter     = 0;
						$prev_parent = $category[$field_cat_parent_id];
						$path        = '';

						if (!empty($breadcrumb)) {
							
							do {

								foreach ($breadcrumb as $keyB => $bc) {
									
									if ($bc['ID'] == $prev_parent) {

										$counter = 0;

										if ($path == '') {
											
											$path = $bc['ID'].'/'; 
											
										} else {

											$path = $bc['ID'].'/'.$path; 
										}

										if ($bc['catalogue_parent_id'] == 0){

											break 2;

										} else {

											$prev_parent = $bc['catalogue_parent_id'];
										}
									}
								}

								$counter++;

								if ($counter == 3) break;

							} while ($prev_parent != 0);
						}

						$arrayReturn["categories"][$keyARC]['category_path_display'] = explode('/', $path.$category['ID']);
						if ($idCategory == $category['ID']){
							$arrayReturn['categories_display'] = array_unique(array_merge($arrayReturn['categories_display'],explode('/', $path.$category['ID'])));
						}
						$arrayReturn["categories"][$keyARC]['category_path'] .= $path.$category['ID'].'/'.sanitize_title($category['section_name']);

					}
				}
			}

			if (!empty($arrayReturn["products"])) {

				foreach ($arrayReturn["products"] as $keyARP => $product) {
					
					$arrayReturn["products"][$keyARP]['product_url'] = $page_url.'p'.$product['ID'].'/'.sanitize_title($product['product_name']).'/';
				}
			}
		}

		if ($this->debug_on) $this->debug("get_catalog($idCategory) return: ".print_r($arrayReturn, 1));

		return json_encode($arrayReturn);
	}

	/**
	 * Returns the product details
	 *
	 * @param string $idProduct Sales Layer product identifier
	 * @param string $page_url page url to load the href
	 * @return array product details
	 */
	public function get_product_detail ($idProduct, $page_url = '') {

		self::connect_saleslayer();

		$field_prd_id = $this->updater->get_db_field_ID(self::slyr_products);
		$field_cat_id = $this->updater->get_db_field_ID(self::slyr_catalog);

		$fields     = array($field_prd_id, 'product_name', 'product_image', 'product_description', 'characteristics', 'formats', 'ID_CATALOG' => $field_cat_id);
		$conditions = array(
			array(
				'field'    => $field_prd_id,
				'condition'=> '=',
				'value'    => $idProduct
			),
		);
		
        $language = $this->updater->get_default_language();
		$result   = $this->updater->extract(self::slyr_products, $fields, $language, $conditions, 1, null, null, null, false, false, true);

		if ($this->debug_on) $this->debug("get_product_detail($idProduct) DB result: ".print_r($result, 1));

    	if ($this->updater->has_response_error()) {

			return self::error_connect_saleslayer();

		} else {

			if (count($result)) {

				$products    = 
				$arrayReturn = array();
				
				foreach ($result as $product) {

					$breadcrumb = self::get_breadcrumb($product['ID_CATALOG']);

					if (!empty($breadcrumb)){

						if (empty($arrayReturn['breadcrumb'])) {

							$arrayReturn['breadcrumb'] = $breadcrumb;

						} else {

							foreach ($breadcrumb as $bc) {
				
								$bc_found = false;

								foreach ($arrayReturn['breadcrumb'] as $arbc) {

									if ($bc['ID'] == $arbc['ID']) {

										$bc_found = true;
										break;
									}
								}
								unset($arbc);

								if (!$bc_found) $arrayReturn['breadcrumb'][] = $bc;
							}
							unset($bc);
						}
					}
					
					$product['orig_ID'] = $product['ID'];
	                $product['ID']     .= '_'.$product['__conn_id__'];

					unset($product['__con_id__']);

	                $schema = $this->updater->get_database_table_schema(self::slyr_products);

					$DETAIL_ID = '';

					if (isset($schema['product_image']['image_sizes'])) {
						foreach (array_keys($schema['product_image']['image_sizes']) as $i) {
							if (!in_array($i, array('TH', 'THM'))) { $DETAIL_ID = $i; break; }
						}
					}

					$product['IMG_FMT'] = $DETAIL_ID;
                    $products[]         = $product;
				}
				
				if ($page_url != '') {

					foreach ($products as $keyProd => $product) {
							
						$products[$keyProd]['product_url'] = $page_url.'p'.$product['orig_ID'].'/'.sanitize_title($product['product_name']).'/';

					}
				}
                
				$arrayReturn['products'] = $products;
				
				if ($this->debug_on) $this->debug("get_product_detail($idProduct) DB return: ".print_r($arrayReturn, 1));

				return json_encode($arrayReturn);
			 } 
		}

		return 0;
	}

	/**
	 * Returns the breadcrumb for a category or subcategory provided
	 *
	 * @param string $idCategory Sales Layer category or subcategory identifier
	 * @return array breadcrumb array
	 */

	private function get_breadcrumb ($idCategory = 0) {

		self::connect_saleslayer();
		$language = $this->updater->get_default_language();

		$field_cat_id        = $this->updater->get_db_field_ID       (self::slyr_catalog);
		$field_cat_parent_id = $this->updater->get_db_field_parent_ID(self::slyr_catalog);

		$fields     = array($field_cat_id, 'section_name', $field_cat_parent_id);
		$conditions = array(
			array(
				'field'    => $field_cat_id,
				'condition'=> '=',
				'value'    => $idCategory
			),
		);
		
		$result = $this->updater->extract(self::slyr_catalog, $fields, $language, $conditions, 1);

		if ($this->debug_on) $this->debug("get_breadcrumb($idCategory) DB result: ".print_r($result, 1));

		if ($this->updater->has_response_error()) {
			return self::error_connect_saleslayer();
		} 

		$breadcrumb = array();
		if (is_array($result)) {
			foreach ($result as &$row) {
				$catalog_parent_id = intval($row['ID_PARENT']);
				if($catalog_parent_id > 0) {
					$breadcrumb_rec = self::get_breadcrumb($row['ID_PARENT']);
					foreach ($breadcrumb_rec as $row_rec) {
						$breadcrumb[] = $row_rec;
					}
				}
				$breadcrumb[] = $row;
			}
		}

		if ($this->debug_on) $this->debug("get_breadcrumb($idCategory) return: ".print_r($breadcrumb, 1));

		return $breadcrumb;
	}

	/**
	 * Returns the fast access menu (shorcuts)
	 *
	 * @param string $idParent Sales Layer category or subcategory identifier
	 * @param string $page_url page url to load the href
	 * @return array breadcrumb array
	 */
	public function get_fast_menu ($idParent = 0, $page_url = '') {

		self::connect_saleslayer();
		$field_cat_id        = $this->updater->get_db_field_ID       (self::slyr_catalog);
		$field_cat_parent_id = $this->updater->get_db_field_parent_ID(self::slyr_catalog);
		$language            = $this->updater->get_default_language();
				
		//Buscamos los hijos de ese padre
		$fields     = array($field_cat_id, 'section_name', $field_cat_parent_id);
		$conditions = array(
			'0'=>array(
				'field'    => $field_cat_parent_id,
				'condition'=> '=',
				'value'    => $idParent)
		);

		$result = $this->updater->extract(self::slyr_catalog, $fields, $language, $conditions, 1);

		if ($this->debug_on) $this->debug("get_fast_menu($idParent) DB result: ".print_r($result, 1));

		if ($this->updater->has_response_error()) {

			return self::error_connect_saleslayer();
		}

		$fast_menu = array();

		if (is_array($result)) {
			foreach ($result as $row) {
				$fast_menu_rec = self::get_fast_menu($row['ID'], $page_url);
				if (count($fast_menu_rec) > 0) { $row['submenu'] = $fast_menu_rec; }
				if ($page_url != ''){
					$row['category_url'] = $page_url.'c'.$row['ID'].'/'.sanitize_title($row['section_name']).'/';
				}
				$fast_menu[] = $row;
			}
		}

		if ($this->debug_on) $this->debug("get_fast_menu($idParent) Return: ".print_r($fast_menu, 1));

		return $fast_menu;
	 }


	/**
	 * Checks out the connector id uniqueness
	 *
	 * @param string $connectorId Sales Layer unique connector id
	 * @return boolean
	 */
	public function checkUserConnector($connectorId) {

		$connectorIdOpt = get_option(self::slyr_connector_id);
		if(($connectorIdOpt == null) || ($connectorIdOpt == $connectorId)) return true;

		return false;  	
	}

	/**
	 * Checks out if config table exists 
	 *
	 * @return boolean
	 */
	public function checkConfigTableExist () {
		
		global $wpdb;
		$table_api_config = $this->slyr_updater_table_prefix.$this->updater->table_config;
		if($wpdb->get_var("SHOW TABLES LIKE '$table_api_config'") == $table_api_config) return true;
	
		return false;
	}

	/**
	 * Search item in slyr products and categories tables
	 * 
	 * @param  string $search_value field to search
	 * @return array containing type of item and its info
	 */
	public function search_item ($search_value) {

		$return       = 0;
		self::connect_saleslayer();
		$language     = $this->updater->get_default_language();
		$field_prd_id = $this->updater->get_db_field_ID(self::slyr_products);
	
		$fields         = array($field_prd_id, 'product_name');
		$product_fields = array('product_name', 'product_description', 'characteristics');
		$conditions     = array();
		$search_values  = explode(' ', $search_value);
			
		foreach ($search_values as $search_val) {
			
			if ($search_val != '') {

				foreach ($product_fields as $product_field) {
					
					$conditions[] = array(
										'logic'  => 'or',
										'field'  => $product_field,
										'search' => $search_val
									);	
				}
			}
		}

		$result = $this->updater->extract(self::slyr_products, $fields, $language, $conditions, 1);

		if ($this->debug_on) $this->debug("search_item($search_value) DB products result: ".print_r($result, 1));

		if (!empty($result)) {

			$return = json_encode(array('type' => 'p', 'id' => $result[0]['ID'], 'name' => $result[0]['product_name']));

		} else {

			$field_cat_id    = $this->updater->get_db_field_ID(self::slyr_catalog);
			$fields          = array($field_cat_id, 'section_name');
			$category_fields = array('section_name', 'section_description');
			$conditions      = array();

			foreach ($search_values as $search_val) {
				
				if ($search_val != ''){

					foreach ($category_fields as $category_field) {
						
						$conditions[] = array(
											'logic' => 'or',
											'field' => $category_field,
											'search' => $search_val
										);		
					}
				}
			}

			$result = $this->updater->extract(self::slyr_catalog, $fields, $language, $conditions, 1);

			if ($this->debug_on) $this->debug("search_item($search_value) DB catalog result: ".print_r($result, 1));

			if (!empty($result)) {

				$return = json_encode(array('type' => 'c', 'id' => $result[0]['ID'], 'name' => $result[0]['section_name']));
			}
		}

		if ($this->debug_on) $this->debug("search_item($search_value) return: ".print_r($return, 1));

		return $return;
	}
}
