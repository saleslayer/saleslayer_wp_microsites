<?php
/*
Plugin Name: Sales Layer WP Microsites
Plugin URI: https://github.com/saleslayer/Sales_Layer_Wordpress
Description: Sales Layer microsites connector.
Version: 1.6
Author: Sales Layer
Author URI: http://saleslayer.com/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if ( PHP_SESSION_NONE === session_status() ) {
    session_start();
}

if (!defined('SLYR__PLUGIN_DIR')) define('SLYR__PLUGIN_DIR', plugin_dir_path(__FILE__));

include_once(SLYR__PLUGIN_DIR.'settings.php');

if (!class_exists('Softclear_API')) {
    require_once SLYR__PLUGIN_DIR.'admin/api/api_sc.php';
}

function slyr_activate(){
//    Plugin activated, do not output anything here

}

//    Init
function slyr_plugin_init(){

    global $wpdb;

    $ver=get_option('SLYR_version');

    if ($ver<SLYR_version) {

        $exist_conf=$wpdb->get_results('SHOW TABLES LIKE \'slyr___api_config\'');

        $conns=($exist_conf ? $wpdb->get_results('select * where slyr___api_config') : array());

        // Delete any options starting with slyr
        $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'slyr%'");

        // Delete all saleslayer tables
        $deleteTables = array('slyr_catalogue', 'slyr_locations', 'slyr_products', 'slyr_product_formats', 'slyr___api_config');

        foreach ($deleteTables as $table) { $wpdb->query("DROP TABLE IF EXISTS $table"); }

        if (count($conns)) {

            // Refresh config
            $wpdb->query("CREATE TABLE `slyr___api_config` (".
                         "`cnf_id` int(11) NOT NULL AUTO_INCREMENT, ".
                         "`conn_code` varchar(32) NOT NULL, ".
                         "`conn_secret` varchar(32) NOT NULL, ".
                         "`comp_id` int(11) NOT NULL, ".
                         "`last_update` timestamp NOT NULL, ".
                         "`default_language` varchar(6) NOT NULL, ".
                         "`languages` varchar(512) NOT NULL, ".
                         "`conn_schema` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, ".
                         "`data_schema` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, ".
                         "`conn_extra` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL, ".
                         "`updater_version` varchar(10) NOT NULL, ".
                         "PRIMARY KEY (`cnf_id`)".
                         ") ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1");

            foreach ($conns as $c) {

                $wpdb->query('INSERT INTO slyr___api_config (conn_code, comp_id, default_language, languages, conn_schema, conn_extra, updater_version) '.
                             "VALUES ('{$c['conn_code']}', '{$c['comp_id']}', '{$c['default_language']}', '{$c['languages']}', '{$c['conn_schema']}', ".
                             "'{$c['conn_extra']}', '{$c['updater_version']}'");
            }
        }
    }

    // 1. Create menu options list and hook them to the stylesheets and scripts
    add_action( 'admin_menu', 'slyr_menu' );

    // 2. Load styles and scripts
    add_action( 'wp_enqueue_scripts', 'slyr_enqueue_scripts'     );

    update_option('SLYR_version', SLYR_version);

    global $wp,$wp_rewrite;

    $exploded_request_uri = explode('/', $_SERVER['REQUEST_URI']);
    if (!empty($exploded_request_uri) && isset($exploded_request_uri[2])){

        $check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_content like '%[saleslayer_catalog]%' AND post_type = 'page' LIMIT 1";
        $post_check = $wpdb->get_var( $wpdb->prepare( $check_sql, $exploded_request_uri[2]) );

        if (!empty($post_check)){

            add_action( 'wp_enqueue_scripts', 'slyr_enqueue_front_stylesheets');
            
            $wp->add_query_var('id');

            add_rewrite_rule(
              $exploded_request_uri[2].'/([^/]*)',
              'index.php?pagename='.$exploded_request_uri[2].'&id=$matches[1]',
              'top' );
            
        }

    }

    $wp_rewrite->flush_rules();

}

register_activation_hook( __FILE__, 'slyr_activate' );
add_action('init','slyr_plugin_init');

function slyr_enqueue_admin_stylesheets(){

    // Register Bootstrap and flat ui styles

    if (is_admin()){

        wp_register_style('mystyle', plugin_dir_url( __FILE__ ).'css/style_admin.css');
        wp_enqueue_style( 'mystyle');
    
    }   

}

function slyr_enqueue_front_stylesheets($type){

    // Register Bootstrap and flat ui styles

    if (!is_admin()){

        wp_register_style('fontawesome', plugin_dir_url( __FILE__ ).'css/fontawesome.min.css');
        wp_enqueue_style( 'fontawesome');

        wp_register_style('mystyle', plugin_dir_url( __FILE__ ).'css/style.css');
        wp_enqueue_style( 'mystyle');

        wp_register_style('shadowbox', plugin_dir_url( __FILE__ ).'css/shadowbox.css');
        wp_enqueue_style( 'shadowbox');

    }
    
}

function slyr_enqueue_scripts(){

    // 'jquery-1.8.3.min',
    $scripts= array('jquery-3.3.1.min',
                    'jquery-ui-1.10.3.custom.min',
                    'jquery.ui.touch-punch.min',
                    'bootstrap.min',
                    'bootstrap-select',
                    'bootstrap-switch',
                    'jquery.tagsinput',
                    'jquery.placeholder',
                    'typeahead',
                    //'i18next-1.7.3.min',
                    //'i18n',
                    'jquery.highlight.min',
                    'script');

    if(!is_admin()) {

        $scripts[]= 'catalog';
        $scripts[]= 'shadowbox';
    }

//  Activates jquery if is not activated yet
    wp_enqueue_script('jquery');

    foreach($scripts as $script ){
        wp_register_script('slyr_plugin_script_'.$script, plugin_dir_url( __FILE__ ).'js/'.$script.'.js',array('jquery'), null, true);
        wp_enqueue_script ('slyr_plugin_script_'.$script);
    }
}


function slyr_menu() {

    $menu_pages[]= add_menu_page( SLYR_name.' Options', SLYR_name, 'manage_options', 'slyr_menu', 'slyr_how_to_start',
                                  $icon_url=plugin_dir_url( __FILE__ ).'images/'.SLYR_name_icon);

    $menu_pages[]= add_submenu_page( 'slyr_menu', __('How to Start?'), __('How to Start?'), 'manage_options', 'slyr_menu',    'slyr_how_to_start');
    $menu_pages[]= add_submenu_page( 'slyr_menu', __('Configuration'), __('Configuration'), 'manage_options', 'slyr_config',  'slyr_config_page' );
    
    //  Adding style to each menu
    foreach($menu_pages as $page){
        add_action( 'admin_print_styles-' . $page, 'slyr_enqueue_admin_stylesheets');
        add_action( 'admin_print_scripts-'. $page, 'slyr_enqueue_scripts');
    } 
}

function slyr_how_to_start() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ob_start();
    include_once(SLYR__PLUGIN_DIR.'howto.php');
    $howto = ob_get_clean();
    echo '<div id="howto">'.$howto.'</div>'; 
}


function slyr_config_page(){
    if ( !current_user_can( 'manage_options' ) )  
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    else
        include_once(SLYR__PLUGIN_DIR.'config.php');
    
}

function slyr_catalog(){

    global $wpdb;

    $result               = $wpdb->query("SHOW TABLES LIKE 'slyr_catalogue'");
    if (!$result) $result = $wpdb->query("SHOW TABLES LIKE 'slyr_products'");

    if ($result){

        global $wp_rewrite;

        $relativeslug = str_replace(home_url(), '', get_permalink());
        $relativeurl = trim($relativeslug,"/");
        
        global $wp;

        $current_home_url = home_url($wp->request);
        $exploded_home_url = explode('/', $current_home_url);
        $current_home_url = $exploded_home_url[0].'/'.$exploded_home_url[1].'/'.$exploded_home_url[2].'/'.$exploded_home_url[3].'/'.$exploded_home_url[4];
        $slyr_page_home_url = $current_home_url.'/';

        $print_data = $preloaded_url = '';
        
        if ($exploded_home_url[4] == $relativeurl && isset($exploded_home_url[5]) && $exploded_home_url[5] != ''){

            $type = strtolower(substr($exploded_home_url[5], 0, 1));

            $item_id = '';
            $apiSC = new Softclear_API();

            if ($type == 'c'){

                $item_id = substr($exploded_home_url[5], 1, strlen($exploded_home_url[5]));
                $return = $apiSC->get_catalog($item_id, $slyr_page_home_url);
                
            }else if ($type == 'p'){

                $item_id = substr($exploded_home_url[5], 1, strlen($exploded_home_url[5]));
                $return = $apiSC->get_product_detail($item_id, $slyr_page_home_url);
                
            }

            $return = json_decode($return, true);

            if (is_array($return) && !empty($return)){

                if ($type == 'c'){

                    if (is_array($return) && !empty($return) && isset($return['breadcrumb']) && !empty($return['breadcrumb'])){

                        foreach ($return['breadcrumb'] as $keyBR => $breadcrumb) {
                            
                            if ($breadcrumb['ID'] == $item_id){

                                if (isset($breadcrumb['category_url']) && $breadcrumb['category_url'] != ''){

                                    $preloaded_url = $breadcrumb['category_url'];

                                }

                            }

                        }

                    }

                }else{

                    if (is_array($return) && !empty($return) && isset($return['products']) && !empty($return['products'])){

                        foreach ($return['products'] as $keyPR => $product) {
                            
                            if ($product['orig_ID'] == $item_id){

                                if (isset($product['product_url']) && $product['product_url'] != ''){

                                    $preloaded_url = $product['product_url'];
                                    break;

                                }

                            }

                        }

                    }

                }

                $print_data = prepare_html_data($type, $return);

            }

        }

        ob_start();
        include_once(SLYR__PLUGIN_DIR.'catalog.html');
        $catalog = ob_get_clean();
        
        return '<div id="catalog">'.$catalog.'</div>';

    }else{        

        return '<div id="catalog"><b>There are no products available right now.</b></div>'.
               '<script type="text/javascript"> var plugins_url = "'.plugins_url().'"; var plugin_name_dir = "'.PLUGIN_NAME_DIR.'"; </script>';

    }
   
}

add_shortcode(SLYR_short_code, 'slyr_catalog');


function prepare_html_data($type, $data){

    $baseURL = plugins_url().'/'.PLUGIN_NAME_DIR.'/';
    
    $return_data = array();

    if (isset($data['breadcrumb']) && !empty($data['breadcrumb'])){

        $breadcrumb_html = '<li><a href="#" onclick="loadCatalog(0); return false;">Start</a></li>';

        foreach ($data['breadcrumb'] as $keyBR => $breadcrumb) {

            (isset($breadcrumb['product_url']) && $breadcrumb['product_url'] != '') ? $breadcrumb_href = $breadcrumb['product_url'] : $breadcrumb_href = '#';

            ($breadcrumb == end($data['breadcrumb'])) ? $breadcrumb_class = ' class="active"' : $breadcrumb_class = '';
            
            $breadcrumb_html .= '<li'.$breadcrumb_class.'><a href="'.$breadcrumb_href.'" onclick="loadCatalog('.$breadcrumb['ID'].'); return false;">'.$breadcrumb['section_name'].'</a></li>';
            
        }

        $return_data['breadcrumb'] = $breadcrumb_html;
    
    }

    if ($type == 'c'){

        $no_elements = true;

        if (isset($data['categories']) && !empty($data['categories'])){

            $no_elements = false;

            $categories_html = '';

            foreach ($data['categories'] as $keyCAT => $category) {

                (isset($category['category_url']) && $category['category_url'] != '') ? $category_href = $category['category_url'] : $category_href = '#';
                
                $categories_html .= '<div class="box_elm not_thum"><div class="box_img img_on"><a href="'.$category_href.'" onclick="loadCatalog('.$category['ID'].'); return false;">';

                if (isset($category['section_image'])){

                    if (is_array($category['section_image']) && !empty($category['section_image'])){

                        $categories_html .= '<img src="'.$category['section_image'][0].'">';

                    }else if (!is_array($category['section_image']) && $category['section_image'] != '' && !is_null($category['section_image'])){

                        $categories_html .= '<img src="'.$category['section_image'].'">';

                    }

                }else{

                    $categories_html .= '<img src="'.$baseURL.'images/placeholder.gif">';

                }

                $categories_html .= '</a></div><div class="box_inf"><h7><a class="section" href="'.$category_href.'" onclick="loadCatalog('.$category['ID'].'); return false;">'.$category['section_name'].'</a></h7></div></div>';

            }

            $return_data['categories'] = $categories_html;

        }

        if (isset($data['products']) && !empty($data['products'])){

            $no_elements = false;

            $products_html = '';

            foreach ($data['products'] as $keyPROD => $product) {

                (isset($product['product_url']) && $product['product_url'] != '') ? $product_href = $product['product_url'] : $product_href = '#';
                
                $products_html .= '<div class="box_elm not_thum"><div class="box_img img_on"><a href="'.$product_href.'" onclick="loadProduct('.$product['ID'].'); return false;">';

                if (isset($product['product_image'])){

                    if (is_array($product['product_image']) && !empty($product['product_image'])){

                        $products_html .= '<img src="'.$product['product_image'][0].'">';

                    }else if (!is_array($product['product_image']) && $product['product_image'] != '' && !is_null($product['product_image'])){

                        $products_html .= '<img src="'.$product['product_image'].'">';

                    }

                }else{

                    $products_html .= '<img src="'.$baseURL.'images/placeholder.gif">';

                }

                (isset($product['product_name']) && $product['product_name'] && !is_null($product['product_name'])) ? $product_name = $product['product_name'] : $product_name = 'Product Undefined';

                $products_html .= '</a></div><div class="box_inf"><h7><a class="product" href="'.$product_href.'" onclick="loadProduct('.$product['ID'].'); return false;">'.$product_name.'</a></h7></div></div>';

            }

            $return_data['products'] = $products_html;

        }

        if ($no_elements) {

            $no_elements_html = '<div class="message"><h5>There are no products inside this category.</h5></div>';
            $return_data['no_elements'] = $no_elements_html;

        }

    }else{

        if (isset($data['products']) && !empty($data['products'])){

            $gallery_html = '<div id="div_image_preview" class="image-preview">';
            $div_image_preview_finished = false;

            $carousel_html = '';

            foreach ($data['products'] as $keyPROD => $product) {

                if (!isset($product['product_description']) || (isset($product['product_description']) && (is_null($product['product_description']) || $product['product_description']) == '')){

                    $return_data['product']['product_description'] = '';

                }else{

                    if (strlen($product['product_description']) > 0 && preg_match('/<\w+[^>]*>/', $product['product_description'])){

                        $product['product_description'] = str_replace(array("\n\r", "\r\n", "\n"), "</br>", $product['product_description']);        

                    }

                    $return_data['product']['product_description'] = $product['product_description'];
                    
                }

                $return_data['product']['product_name'] = $product['product_name'];
                $return_data['product']['p_characteristics'] = $product['characteristics'];
                $return_data['product']['p_formats'] = $product['formats'];
                $return_data['product']['catalogue_id'] = $product['catalogue_id'];

                if (isset($product['product_image']) && is_array($product['product_image']) && !empty($product['product_image'])){

                    $img_fmt = $product['IMG_FMT'];
                    $key_base = '';

                    $carousel_html = '<ul id="carousel" class="slide-list">';
                    $imo = 1;

                    foreach ($product['product_image'] as $keyPRODIMG => $image) {

                        if ($key_base == ''){ $key_base = $keyPRODIMG; }

                        if (count($product['product_image']) > 1){

                            if (isset($image[$img_fmt]) && $image[$img_fmt] != ''){
                                $change_image = $imo.",'".$image[$img_fmt]."', 'undefined'";

                                $carousel_html .= '<li class="imo'.$imo.'"><a href="#" onclick="return changeImage('.$change_image.')"><img src="'.$image[$img_fmt].'"></a></li>';

                            }else{

                                $change_image = $imo.",'".$image['THM']."', 'undefined'";
                                $carousel_html .= '<li class="imo'.$imo.'"><a href="#" onclick="return changeImage('.$change_image.')"><img src="'.$image['THM'].'"></a></li>';

                            }

                        }
                            
                        if (!$div_image_preview_finished){

                            if (isset($image[$img_fmt]) && $image[$img_fmt] != ''){
                                
                                $gallery_html .= '<img id="preview" src="'.$image[$img_fmt].'"></div>';

                            }else{

                                $gallery_html .= '<img id="preview" src="'.$image['THM'].'"></div>';

                            }

                            $div_image_preview_finished = true;

                        }

                        $imo++;
                        
                    }

                    $carousel_html .= '</ul>';
                    $return_data['product']['gallery'] = $gallery_html.$carousel_html;

                }else{

                    $return_data['product']['gallery'] = $gallery_html.'<img id="preview" src="'.$baseURL.'images/placeholder.gif"></div><ul id="carousel" class="elastislide-list"></ul>';
                    $div_image_preview_finished = true;
                    
                }

                break;

            }

        }

    }

    return $return_data;

}

function pluginUninstall() {

    global $wpdb;

    // Delete any options starting with slyr
    $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'slyr%'");

    // Delete all saleslayer tables
    $deleteTables = array('slyr_catalogue', 'slyr_locations', 'slyr_products', 'slyr_product_formats', 'slyr___api_config');

    foreach ($deleteTables as $table) { $wpdb->query("DROP TABLE IF EXISTS $table"); }
}

register_uninstall_hook(__FILE__, 'pluginUninstall');