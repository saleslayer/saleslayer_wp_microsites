<?php
/*
Plugin Name: Sales Layer WP Microsites
Plugin URI: https://github.com/saleslayer/Sales_Layer_Wordpress
Description: Sales Layer microsites connector.
Version: 1.7
Author: Sales Layer
Author URI: http://saleslayer.com/
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

if (! Defined('ABSPATH')) {
    exit;
}

if (PHP_SESSION_NONE === session_status()) {
    session_start();
}

if (!defined('SLYR__PLUGIN_DIR')) {
    define('SLYR__PLUGIN_DIR', plugin_dir_path(__FILE__));
}

require_once SLYR__PLUGIN_DIR.'settings.php';

if (!class_exists('Softclear_API')) {
    require_once SLYR__PLUGIN_DIR.'admin/api/api_sc.php';
}

function slyr_activate()
{
//    Plugin activated, do not output anything here
}

//    Init
function slyr_plugin_init()
{
    global $wpdb;

    $ver = get_option('SLYR_version');
    $token_sl = get_option('SLYR_unique_token');

    if (!$token_sl) {
        add_option('SLYR_unique_token', hash('adler32', time().microtime(1).rand(0, 100)).hash('crc32b', time().rand(50, 250)));
    }

    if ($ver < SLYR_version) {
        $exist_conf = $wpdb->get_results('SHOW TABLES LIKE \'slyr___api_config\'');

        $conns = ($exist_conf ? $wpdb->get_results('select * where slyr___api_config') : array());

        // Delete any options starting with slyr
        $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'slyr%'");

        // Delete all saleslayer tables
        $deleteTables = array(
            'slyr_catalogue',
            'slyr_locations',
            'slyr_products',
            'slyr_product_formats',
            'slyr___api_config',
        );

        foreach ($deleteTables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        if (count($conns)) {

            // Refresh config
            $wpdb->query('CREATE TABLE `slyr___api_config` ('.
                '`cnf_id` int(11) NOT NULL AUTO_INCREMENT, '.
                '`conn_code` varchar(32) NOT NULL, '.
                '`conn_secret` varchar(32) NOT NULL, '.
                '`comp_id` int(11) NOT NULL, '.
                '`last_update` timestamp NOT NULL, '.
                '`default_language` varchar(6) NOT NULL, '.
                '`languages` varchar(512) NOT NULL, '.
                '`conn_schema` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, '.
                '`data_schema` text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL, '.
                '`conn_extra` text CHARACTER SET utf8 COLLATE utf8_general_ci NULL, '.
                '`updater_version` varchar(10) NOT NULL, '.
                'PRIMARY KEY (`cnf_id`)'.
                ') ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1');

            foreach ($conns as $c) {
                $wpdb->query('INSERT INTO slyr___api_config (conn_code, comp_id, default_language, languages, conn_schema, conn_extra, updater_version) '.
                    "VALUES ('{$c['conn_code']}', '{$c['comp_id']}', '{$c['default_language']}', '{$c['languages']}', '{$c['conn_schema']}', ".
                    "'{$c['conn_extra']}', '{$c['updater_version']}'");
            }
        }
    }

    // 1. Create menu options list and hook them to the stylesheets and scripts
    add_action('admin_menu', 'slyr_menu', 1);

    update_option('SLYR_version', SLYR_version);

    global $wp, $wp_rewrite;

    $exploded_request_uri = explode('/', $_SERVER['REQUEST_URI']);
    if (!empty($exploded_request_uri) && isset($exploded_request_uri[2])) {
        $check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = 'page' AND post_content like '%[saleslayer_catalog]%'  LIMIT 1";
        $post_check = $wpdb->get_var($wpdb->prepare($check_sql, $exploded_request_uri[2]));

        if (!empty($post_check)) {

            // 2. Load styles and scripts
            add_action('wp_enqueue_scripts', 'slyr_enqueue_front_stylesheets', 1);
            add_action('wp_enqueue_scripts', 'slyr_enqueue_scripts', 1);
            
            $wp->add_query_var('id');

            add_rewrite_rule(
                $exploded_request_uri[2].'/([^/]*)',
                'index.php?pagename='.$exploded_request_uri[2].'&id=$matches[1]',
                'top'
            );
        }
    }

    $wp_rewrite->flush_rules();
}

register_activation_hook(__FILE__, 'slyr_activate');
add_action('init', 'slyr_plugin_init');


add_action('init', 'slreload_init');
function slreload_init()
{
    $token_sl = get_option('SLYR_unique_token');
    add_rewrite_rule('^'.$token_sl.'$', 'index.php?slreload_stats='.$token_sl, 'top');
}

add_action('query_vars', 'slreload_query_vars');
function slreload_query_vars($query_vars)
{
    $query_vars[] = 'slreload_stats';
    return $query_vars;
}

add_action('parse_request', 'slreload_parse_request');
function slreload_parse_request(&$wp)
{
    if (array_key_exists('slreload_stats', $wp->query_vars)) {
        require_once  dirname(__FILE__) . '/get_notices.php' ;
        exit();
    }
}

function slyr_enqueue_admin_stylesheets()
{
    if (is_admin()) {
        wp_register_style('mystyle', plugin_dir_url(__FILE__).'css/style_admin.css');
        wp_enqueue_style('mystyle');
    }
}

function slyr_enqueue_front_stylesheets()
{
    if (!is_admin()) {
        wp_register_style('mystyle', plugin_dir_url(__FILE__).'css/style.css', array(), 1);
        wp_enqueue_style('mystyle');
    }
}

function slyr_enqueue_scripts()
{
    $scripts = array(
        'script',
    );

    if (!is_admin()) {
        $scripts[] = 'catalog';
        $scripts[] = 'shadowbox';
    }

    //  Activates jquery if is not activated yet
    wp_enqueue_script('jquery');

    foreach ($scripts as $script) {
        wp_register_script(
            'slyr_plugin_script_'.$script,
            plugin_dir_url(__FILE__).'js/'.$script.'.js',
            array('jquery'),
            null,
            true
        );
        wp_enqueue_script(
            'slyr_plugin_script_'.$script
        );
    }

    sl_special_inline_script();

    wp_localize_script(
        'slyr_plugin_script_catalog',
        'sl_ajax_object', //call_api.php
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sales_layer_nonce')
        )
    );

    wp_localize_script(
        'slyr_plugin_script_script',
        'sl_ajax_object', //call_api.php
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('sales_layer_nonce')
        )
    );
}

function sl_catalog_control()
{
    if (! wp_verify_nonce($_REQUEST['_ajax_nonce'], 'sales_layer_nonce')) {
        wp_die("Error - Invalid nonce verification  ✋");
    }


    if (isset($_POST['endpoint'])) {
        $endpoint = sanitize_text_field($_POST['endpoint']);
        $return = null;

        $page_permalink = '';

        if (isset($_POST['web_url'])) {
            $url_params = explode('/', str_replace(home_url().'/', '', esc_url_raw($_POST['web_url'])));

            if (isset($url_params[0]) && !empty($url_params[0])) {
                $page_permalink = $url_params[0];
            }
        }

        $page_url = '';

        if ($page_permalink != '') {
            $page_url = home_url().'/'.$page_permalink.'/';
        }

        $apiSC = new Softclear_API();

        switch ($endpoint) {

            case 'menu':

                $return = $apiSC->get_fast_menu(0, $page_url);

                break;

            case 'catalog':

                $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : 0;

                $return = $apiSC->get_catalog($id, $page_url);

                break;

            case 'products':

                $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : 0;

                $return = $apiSC->get_product_detail($id, $page_url);

                break;

            case 'refresh-data':

                $return = $apiSC->get_sync_data(
                    $_SESSION['slyr']['connector-id'],
                    $_SESSION['slyr']['private-key']
                );

                break;

            case 'search_item':

                $search_value = isset($_POST['search_value']) ? sanitize_text_field($_POST['search_value']) : 0;

                $return = $apiSC->search_item($search_value);

                break;

            case 'tables_fields_ids':

                $return = $apiSC->get_tables_fields_ids();

                break;

        }

        wp_send_json($return);
    }
}

// Hook para usuarios no logueados
add_action('wp_ajax_nopriv_sl_catalog_control', 'sl_catalog_control');
// Hook para usuarios logueados
add_action('wp_ajax_sl_catalog_control', 'sl_catalog_control');

add_action('wp_ajax_nopriv_sl_refresh_connector', 'sl_refresh_connector');

function sl_refresh_connector()
{
    exit();
}

function slyr_menu()
{
    $menu_pages[] = add_menu_page(
        SLYR_name.' Options',
        SLYR_name,
        'manage_options',
        'slyr_menu',
        'slyr_how_to_start',
        $icon_url = plugin_dir_url(__FILE__).'images/'.SLYR_name_icon
    );

    $menu_pages[] = add_submenu_page(
        'slyr_menu',
        __('How to Start?'),
        __('How to Start?'),
        'manage_options',
        'slyr_menu',
        'slyr_how_to_start'
    );
    $menu_pages[] = add_submenu_page(
        'slyr_menu',
        __('Configuration'),
        __('Configuration'),
        'manage_options',
        'slyr_config',
        'slyr_config_page'
    );

    //  Adding style to each menu
    foreach ($menu_pages as $page) {
        add_action('admin_print_styles-'.$page, 'slyr_enqueue_admin_stylesheets', 1);
    }
}

function slyr_how_to_start()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    ob_start();
    require_once SLYR__PLUGIN_DIR.'howto.php';
    $howto = ob_get_clean();
    echo '<div id="howto">'.$howto.'</div>';
}


function slyr_config_page()
{
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    } else {
        require_once SLYR__PLUGIN_DIR.'config.php';
    }
}

function slyr_catalog()
{
    global $wpdb;

    $result = $wpdb->query("SHOW TABLES LIKE 'slyr_catalogue'");
    if (!$result) {
        $result = $wpdb->query("SHOW TABLES LIKE 'slyr_products'");
    }

    if ($result) {
        //global $wp_rewrite;

        $relativeslug = str_replace(home_url(), '', get_permalink());
        $relativeurl = trim($relativeslug, '/');

        global $wp;
        $return = [];
        $current_home_url = esc_url_raw(home_url($wp->request));
        $exploded_home_url = explode('/', $current_home_url);
        $current_home_url = $exploded_home_url[0].'/'.$exploded_home_url[1].'/'.$exploded_home_url[2].'/'.$exploded_home_url[3].'/'.$exploded_home_url[4];
        $slyr_page_home_url = $current_home_url.'/';

        $print_data = $preloaded_url = '';

        if ($exploded_home_url[4] == $relativeurl && isset($exploded_home_url[5]) && $exploded_home_url[5] != '') {
            $type = strtolower(substr($exploded_home_url[5], 0, 1));

            $item_id = '';
            $apiSC = new Softclear_API();

            if ($type == 'c') {
                $item_id = substr($exploded_home_url[5], 1, strlen($exploded_home_url[5]));
                $return = $apiSC->get_catalog($item_id, $slyr_page_home_url);
            } else {
                if ($type == 'p') {
                    $item_id = substr($exploded_home_url[5], 1, strlen($exploded_home_url[5]));
                    $return = $apiSC->get_product_detail($item_id, $slyr_page_home_url);
                }
            }

            if (is_array($return) && !empty($return)) {
                if ($type == 'c') {
                    if (isset($return['breadcrumb']) && !empty($return['breadcrumb'])) {
                        $field_cat_id = $apiSC->get_tables_fields_ids('field_cat_id');

                        foreach ($return['breadcrumb'] as $keyBR => $breadcrumb) {
                            if ($breadcrumb[$field_cat_id] == $item_id) {
                                if (isset($breadcrumb['category_url']) && $breadcrumb['category_url'] != '') {
                                    $preloaded_url = $breadcrumb['category_url'];
                                }
                            }
                        }
                    }
                } else {
                    if (isset($return['products']) && !empty($return['products'])) {
                        foreach ($return['products'] as $keyPR => $product) {
                            if ($product['orig_ID'] == $item_id) {
                                if (isset($product['product_url']) && $product['product_url'] != '') {
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

        $script = 'var plugins_url = \''.esc_attr(plugins_url()).'\';
                    var plugin_name_dir =  \''.esc_attr(PLUGIN_NAME_DIR).'\';
                    var slyr_page_home_url =  \''.esc_attr($slyr_page_home_url).'\';
                    var relativeUrl = \''.esc_attr($relativeurl).'\';
                    var preloaded_info =   \''.(is_array($print_data) && !empty($print_data) ? '1' : '0').'\';
                    var preloaded_url =   \''.esc_attr($preloaded_url).'\';
            ';

        wp_register_script('declarations', '');
        wp_enqueue_script('declarations');
        wp_add_inline_script('declarations', $script);
        ob_start();
        require_once SLYR__PLUGIN_DIR.'catalog.php';
        $catalog = ob_get_clean();

        return '<div id="catalog">'.$catalog.'</div>';
    } else {
        return '<div id="catalog"><b>There are no products available right now.</b></div>'.
            '<script type="text/javascript"> var plugins_url = "'.plugins_url().'"; var plugin_name_dir = "'.PLUGIN_NAME_DIR.'"; </script>';
    }
}

add_shortcode(SLYR_short_code, 'slyr_catalog');

function sl_special_inline_script()
{
    if (function_exists('wp_add_inline_script')) {
        // wp_add_inline_script is available from Wordpress 4.5
        wp_register_script('no-conflict', '');
        wp_enqueue_script('no-conflict');
        wp_add_inline_script('no-conflict', '$ = jQuery.noConflict();', 1);
    } else {
        echo '<script>$ = jQuery.noConflict();</script>';
    }
}

function prepare_html_data($type, $data)
{
    $apiSC = new Softclear_API();
    $tables_fields_ids = $apiSC->get_tables_fields_ids();
    $field_cat_id = $tables_fields_ids['field_cat_id'];
    $field_prd_id = $tables_fields_ids['field_prd_id'];

    $baseURL = plugins_url().'/'.PLUGIN_NAME_DIR.'/';

    $return_data = array();

    if (isset($data['breadcrumb']) && !empty($data['breadcrumb'])) {
        $breadcrumb_html = '<li><a href="#" onclick="loadCatalog(0); return false;">Start</a></li>';

        foreach ($data['breadcrumb'] as $keyBR => $breadcrumb) {
            (isset($breadcrumb['product_url']) && $breadcrumb['product_url'] != '') ? $breadcrumb_href = $breadcrumb['product_url'] : $breadcrumb_href = '#';

            ($breadcrumb == end($data['breadcrumb'])) ? $breadcrumb_class = ' class="active"' : $breadcrumb_class = '';

            $breadcrumb_html .= '<li'.$breadcrumb_class.'><a href="'.esc_attr($breadcrumb_href).'" onclick="loadCatalog('.esc_js($breadcrumb[$field_cat_id]).') return false;">'.esc_html($breadcrumb['section_name']).'</a></li>';
        }

        $return_data['breadcrumb'] = $breadcrumb_html;
    }

    if ($type == 'c') {
        $no_elements = true;

        if (isset($data['categories']) && !empty($data['categories'])) {
            $no_elements = false;

            $categories_html = '';

            foreach ($data['categories'] as $keyCAT => $category) {
                (isset($category['category_url']) && $category['category_url'] != '') ? $category_href = $category['category_url'] : $category_href = '#';

                $categories_html .= '<div class="box_elm not_thum"><div class="box_img img_on"><a href="'.esc_attr($category_href).'" onclick="loadCatalog('.esc_js($category[$field_cat_id]).') return false;">';

                if (isset($category['section_image'])) {
                    if (is_array($category['section_image']) && !empty($category['section_image'])) {
                        $categories_html .= '<img src="'.esc_attr($category['section_image'][0]).'" alt="'.esc_attr($category['section_name']).'" />';
                    } else {
                        if (!is_array($category['section_image']) && $category['section_image'] != '' && $category['section_image'] !== null) {
                            $categories_html .= '<img src="'.esc_attr($category['section_image']).'" alt="'.esc_attr($category['section_name']).'"/>';
                        }
                    }
                } else {
                    $categories_html .= '<img src="'.$baseURL.'images/placeholder.gif" alt="">';
                }

                $categories_html .= '</a></div><div class="box_inf"><h7><a class="section" href="'.esc_attr($category_href).'" onclick="loadCatalog('.esc_js($category[$field_cat_id]).') return false;">'.esc_html($category['section_name']).'</a></h7></div></div>';
            }

            $return_data['categories'] = $categories_html;
        }

        if (isset($data['products']) && !empty($data['products'])) {
            $no_elements = false;

            $products_html = '';

            foreach ($data['products'] as $keyPROD => $product) {
                (isset($product['product_url']) && $product['product_url'] != '') ? $product_href = $product['product_url'] : $product_href = '#';
                (isset($product['product_name']) && $product['product_name'] && $product['product_name'] !== null) ? $product_name = esc_html($product['product_name']) : $product_name = 'Product Undefined';
                $products_html .= '<div class="box_elm not_thum"><div class="box_img img_on"><a href="'.esc_attr($product_href).'" onclick="loadProduct('.esc_js($product[$field_prd_id]).') return false;">';

                if (isset($product['product_image'])) {
                    if (is_array($product['product_image']) && !empty($product['product_image'])) {
                        $products_html .= '<img src="'.esc_attr($product['product_image'][0]).'" alt="'.esc_html($product_name).'">';
                    } else {
                        if (!is_array($product['product_image']) && $product['product_image'] != '' && $product['product_image'] !== null) {
                            $products_html .= '<img src="'.esc_attr($product['product_image']).'" alt="'.esc_html($product_name).'">';
                        }
                    }
                } else {
                    $products_html .= '<img src="'.$baseURL.'images/placeholder.gif" alt="">';
                }

                $products_html .= '</a></div><div class="box_inf"><h7><a class="product" href="'.esc_attr($product_href).'" onclick="loadProduct('.esc_js($product[$field_prd_id]).') return false;">'.esc_html($product_name).'</a></h7></div></div>';
            }

            $return_data['products'] = $products_html;
        }

        if ($no_elements) {
            $no_elements_html = '<div class="message"><h5>There are no products inside this category.</h5></div>';
            $return_data['no_elements'] = $no_elements_html;
        }
    } else {
        if (isset($data['products']) && !empty($data['products'])) {
            $gallery_html = '<div id="div_image_preview" class="image-preview">';
            $div_image_preview_finished = false;

            $carousel_html = '';

            foreach ($data['products'] as $keyPROD => $product) {
                if (!isset($product['product_description']) || (isset($product['product_description']) && ($product['product_description'] === null || $product['product_description']) == '')) {
                    $return_data['product']['product_description'] = '';
                } else {
                    if ($product['product_description'] != '' && preg_match(
                        '/<\w+[^>]*>/',
                        $product['product_description']
                        )) {
                        $product['product_description'] = str_replace(
                            array("\n\r", "\r\n", "\n"),
                            '<br>',
                            $product['product_description']
                        );
                    }

                    $return_data['product']['product_description'] = $product['product_description'];
                }

                $return_data['product']['product_name'] = $product['product_name'];
                $return_data['product']['p_characteristics'] = $product['characteristics'];
                $return_data['product']['p_formats'] = $product['formats'];
                $return_data['product'][$field_cat_id] = $product[$field_cat_id];

                if (isset($product['product_image']) && is_array($product['product_image']) && !empty($product['product_image'])) {
                    $img_fmt = $product['IMG_FMT'];
                    $key_base = '';

                    $carousel_html = '<ul id="carousel" class="slide-list">';
                    $imo = 1;

                    foreach ($product['product_image'] as $keyPRODIMG => $image) {
                        if ($key_base == '') {
                            $key_base = $keyPRODIMG;
                        }

                        if (count($product['product_image']) > 1) {
                            if (isset($image[$img_fmt]) && $image[$img_fmt] != '') {
                                $change_image = $imo.",'".$image['THM']."','".$image[$img_fmt]."'";
                            } else {
                                $change_image = $imo.",'".$image['THM']."', 'undefined'";
                            }

                            $carousel_html .= '<li class="imo'.$imo.'"><a href="#" onclick="return changeImage('.$change_image.')"><img src="'.esc_attr($image['TH']).'" alt=""></a></li>';
                        }

                        if (!$div_image_preview_finished) {
                            if (isset($image[$img_fmt]) && $image[$img_fmt] != '') {
                                $gallery_html .= '<a id="apreview" rel="shadowbox" href='.esc_attr($image[$img_fmt]).'><img id="preview" class="vw_detl" src="'.esc_attr($image['THM']).'"></div></a>';
                            } else {
                                $gallery_html .= '<img id="preview" src="'.esc_attr($image['THM']).'" alt=""></div>';
                            }


                            $div_image_preview_finished = true;
                        }

                        $imo++;
                    }

                    $carousel_html .= '</ul>';
                    $return_data['product']['gallery'] = $gallery_html.$carousel_html;
                } else {
                    $return_data['product']['gallery'] = $gallery_html.'<img id="preview" src="'.esc_attr($baseURL).'images/placeholder.gif"></div><ul id="carousel" class="elastislide-list"></ul>';
                    $div_image_preview_finished = true;
                }

                break;
            }
        }
    }

    return $return_data;
}

function pluginUninstall()
{
    global $wpdb;

    // Delete any options starting with slyr
    $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'slyr%'");

    // Delete all saleslayer tables
    $deleteTables = array(
        'slyr_catalogue',
        'slyr_locations',
        'slyr_products',
        'slyr_product_formats',
        'slyr___api_config',
    );

    foreach ($deleteTables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
}

register_uninstall_hook(__FILE__, 'pluginUninstall');
