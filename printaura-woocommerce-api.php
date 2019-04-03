<?php
/*
  Plugin Name: PrintAura WooCommerce API
  Plugin URI: https://printaura.com
  Description: PrintAura  WooCommerce Integration API
  Author: Print Aura
  Version: 4.0
  Author URI: http://printaura.com
*/
  // Turn on debugging?
if (!defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
if (!defined('WC_JSON_API_DEBUG')) {
    define('WC_JSON_API_DEBUG', false);
}

define('REDE_PLUGIN_BASE_PATH', plugin_dir_path(__FILE__));
if (!defined('REDENOTSET')) {
    define('REDENOTSET', '__RED_E_NOTSET__'); // because sometimes false, 0 etc are
  // exspected but consistently dealing with these situations is tiresome.
}
    

require_once(plugin_dir_path(__FILE__) . 'classes/class-rede-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'classes/class-pa-updater-config.php');
require_once(plugin_dir_path(__FILE__) . 'classes/class-pa-updater.php');
require_once(plugin_dir_path(__FILE__) . 'printaura-woocommerce-api-core.php');

/************add custom interval cron schedules***************/
function printaura_add_cron_schedules($schedules)
{
    if (!isset($schedules["2hourly"])) {
        $schedules["2hourly"] = array(
            'interval' => 7200,
            'display' => __('Every 2 Hourly'));
    }
    if (!isset($schedules["3min"])) {
        $schedules["3min"] = array(
            'interval' => 180,
            'display' => __('Once every 3 minutes'));
    }
    return $schedules;
}
add_filter('cron_schedules', 'printaura_add_cron_schedules');
/**************************************************************/
/******************* Creating Scheduled Event******************/
function printaura_cronstarter_activation()
{
    $active_plugins = (array) get_option('active_plugins', array());
    if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
        if (!wp_next_scheduled('wooc_schedule_send_orders')) {
            wp_schedule_event(time(), 'hourly', 'wooc_schedule_send_orders');
        }
    }
}
add_action('wp', 'printaura_cronstarter_activation');

add_action('wooc_schedule_send_orders', 'schedule_resend_orders');

function printaura_schedule_resend_orders()
{
    $active_plugins = (array) get_option('active_plugins', array());
    if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
        require_once(plugin_dir_path(__FILE__) .'../woocommerce/woocommerce.php');
        WC()->api->includes();
        WC()->api->register_resources(new WC_API_Server('/'));
        $args = get_args_shop_order();
        wc_register_order_type('shop_order', $args);
        $filter_hooks = array(
            'post_type'   => 'shop_webhook',
            'post_status' => 'publish',
        );
        $query_hooks = new WP_Query($filter_hooks);
        $webhooks = $query_hooks->posts;
        if ($webhooks) {
            foreach ($webhooks as $hook) {
                $webhook_id = $hook->ID;
                $webhook = new WC_Webhook($webhook_id);
                if ($webhook->get_topic() == 'order.updated' && $webhook->get_status() == 'active') {
                    $delivery_url = $webhook->delivery_url;
                    $host = parse_url($delivery_url);
                    if ($host['host'] == "printaura.com") {
                        $filter_orders = array(
                         'post_type' => 'shop_order',
                         'post_status' => 'wc-processing',
                         'date_query' => array(
                                array('hour' => (int)date("H"),'compare' => '>=',
                                ),
                                array('hour' => (int)date("H", time() + 60 * 60 * 2),'compare' => '<=',
                                )
                        ),
                         'posts_per_page' => '-1'
                        );
                        $query_orders = new WP_Query($filter_orders);
                        $orders = $query_orders->posts;
                        foreach ($orders as $order) {
                            if (in_array($order->post_status, array("wc-processing"))) {
                                $webhook->deliver($order->ID);
                            }
                        }
                    }
                }
            }
        }
    }
}

function printaura_debug_pa($data)
{
}

function printaura_get_args_shop_order()
{
    $args = array(
        'labels'              => array(
                        'name'                  => __('Orders', 'woocommerce'),
                        'singular_name'         => _x('Order', 'shop_order post type singular name', 'woocommerce'),
                        'add_new'               => __('Add Order', 'woocommerce'),
                        'add_new_item'          => __('Add New Order', 'woocommerce'),
                        'edit'                  => __('Edit', 'woocommerce'),
                        'edit_item'             => __('Edit Order', 'woocommerce'),
                        'new_item'              => __('New Order', 'woocommerce'),
                        'view'                  => __('View Order', 'woocommerce'),
                        'view_item'             => __('View Order', 'woocommerce'),
                        'search_items'          => __('Search Orders', 'woocommerce'),
                        'not_found'             => __('No Orders found', 'woocommerce'),
                        'not_found_in_trash'    => __('No Orders found in trash', 'woocommerce'),
                        'parent'                => __('Parent Orders', 'woocommerce'),
                        'menu_name'             => _x('Orders', 'Admin menu name', 'woocommerce'),
                        'filter_items_list'     => __('Filter orders', 'woocommerce'),
                        'items_list_navigation' => __('Orders navigation', 'woocommerce'),
                        'items_list'            => __('Orders list', 'woocommerce'),
                ),
        'description'         => __('This is where store orders are stored.', 'woocommerce'),
        'public'              => false,
        'show_ui'             => true,
        'capability_type'     => 'shop_order',
        'map_meta_cap'        => true,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_in_menu'        => current_user_can('manage_woocommerce') ? 'woocommerce' : true,
        'hierarchical'        => false,
        'show_in_nav_menus'   => false,
        'rewrite'             => false,
        'query_var'           => false,
        'supports'            => array( 'title', 'comments', 'custom-fields' ),
        'has_archive'         => false,
                );
    return $args;
}
/******************************************************************/

function printaura_add_shipped_order_woocommerce_email($email_classes)
{

    // include our custom email class
    require_once(plugin_dir_path(__FILE__) .'classes/class-wc-shipped-order-email.php');

    // add the email class Printaura_to the list of email classes that WooCommerce loads
    $email_classes['WC_Shipped_Order_Email'] = new WC_Shipped_Order_Email();

    return $email_classes;
}
add_filter('woocommerce_email_classes', 'printaura_add_shipped_order_woocommerce_email');

function printaura_send_ship($order_id, $tracking_number="", $tracking_method="")
{
    $active_plugins = (array) get_option('active_plugins', array());
    if (in_array('woocommerce/woocommerce.php', $active_plugins)) {
        $email = new WC_Emails();
        $emails = $email->get_emails();
        $sh_email= $emails['WC_Shipped_Order_Email'];
        $sh_email->trigger($order_id, $tracking_number, $tracking_method);
    }
}

function printaura_initialisation()
{
    global $wpdb;
    // fix some server adding additionals params
    if (isset($_GET['page']) && !isset($_GET['noheader']) && !isset($_GET['consumer_key']) && !isset($_GET['consumer_secret'])) {
        $request_uri = explode("?", $_SERVER['REQUEST_URI']);
        $params_exp = explode("&", $request_uri[1]);
        foreach ($params_exp as $param) {
            $val = explode("=", $param);
            if (strpos($val[1], 'superstorefinder') === false) {
                $_GET[$val[0]] = $val[1];
            }
        }
    }
    if (isset($_GET['oauth_consumer_key']) && isset($_GET['oauth_signature']) && isset($_GET['oauth_signature_method'])) {
        unset($_GET['q']);
        unset($_GET['all_ids']);
    }
    // plugin updater section
    $args = array(
            'plugin_name' => 'Print Aura API',
            'plugin_slug' => 'printaura-woocommerce-api',
            'plugin_path' => plugin_basename(__FILE__),
            'plugin_url'  => WP_PLUGIN_URL . '/printaura-woocommerce-api',
            'version'     => '3.4.10',
            'remote_url'  => 'https://printaura.com/printaura-woocommerce-updater/',
            'time'        => 4230
        );
    $config            = new Printaura_Updater_Config($args);
    $namespace_updater = new Printaura_Updater($config);
    $namespace_updater->update_plugins();
}
add_action('init', 'printaura_initialisation');

function printaura_pa_disabled_notice()
{
    echo '<div id="error" class="error"><br /><strong>Print Aura Woocommere API Plugin requires PHP version 5.3('.phpversion().') or higher. Please click <a href="https://printaura.com/woocommerce-server-requirements/" target="_blank"> here</a> for more information about server requirements.</strong><br /><br /></div>';
}

function printaura_check_printaura_version()
{
    if (version_compare(phpversion(), '5.3', '<')) {
        if (is_plugin_active(plugin_basename(__FILE__))) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', 'pa_disabled_notice');
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
}
//add_action( 'admin_init', 'check_printaura_version'  );

function printaura_woocommerce_api_activate()
{
    global $wpdb;
 
    $helpers = new JSONAPIHelpers();
    $current_user 	= wp_get_current_user();
    $user_id	= $current_user->ID;
    wp_insert_term('shipped', 'shop_order_status');
    wp_insert_term('partially shipped', 'shop_order_status');
    wp_insert_term('T-shirts', 'product_shipping_class');
    wp_insert_term('Sweatshirts', 'product_shipping_class');
    wp_insert_term('Bags', 'product_shipping_class');
    wp_insert_term('Mugs', 'product_shipping_class');
    wp_insert_term('Pillows', 'product_shipping_class');
    wp_insert_term('Snapback Hats', 'product_shipping_class');
    wp_insert_term('Small Posters', 'product_shipping_class');
    wp_insert_term('Large Posters', 'product_shipping_class');
    wp_insert_term('Jumbo Posters', 'product_shipping_class');
    wp_insert_term('Cases', 'product_shipping_class');
    wp_insert_term('Sunglasses', 'product_shipping_class');
    $key = $helpers->getPluginPrefix() . '_enabled';
    $key1 = $helpers->getPluginPrefix() . '_token';
    $key2 = $helpers->getPluginPrefix() . '_ips_allowed';
    $from = $helpers->getPluginPrefix() . '_mail_from';
    $subject = $helpers->getPluginPrefix() . '_mail_subject';
    $body = $helpers->getPluginPrefix() . '_mail_body';
    $from_default="From: {Sender} <{EMAIL}>";
    $subject_default = "Ship Notification: #{ORDERNUMBER}";
    $body_default  = '<p>Dear {CustomerFirstName},</p>
<p>The following items have shipped from order #{ORDERNUMBER}</p>

{CLIENT PRODUCT TITLE} - {COLOR} / {SIZE} (Qty: {QTY})
</p>
<p>
The order has been shipped to:
</p>
<p>
{SHIP_ADDRESS}
</p>
<p>via {SHIP_METHOD} (Tracking #: {TRACKINGNUMBER} )</p>
<p>Please note that it may take until the next business day before tracking becomes available.</p>

Thanks for your business.';
    $apiKey = wp_hash_password(date("YmdHis", time()) . rand(1000, 99999) . $_SERVER['REMOTE_ADDR'] . SECURE_AUTH_SALT . $user_id);

    update_option($key, 'yes');
    if (!get_option($key1)) {
        update_option($key1, $apiKey);
        update_user_meta($user_id, $key1, $apiKey);
    }
    update_option($key2, '162.209.60.177');
    update_option($from, $from_default);
    update_option($subject, $subject_default);
    update_option($body, $body_default);
}
register_activation_hook(__FILE__, 'printaura_woocommerce_api_activate');

function printaura_woocommerce_api_deactivate()
{
    global $wpdb;
    $status1=get_term_by('name', 'shipped', 'shop_order_status');
    $status2=get_term_by('name', 'partially shipped', 'shop_order_status');
    wp_delete_term(intval($status2->term_id), 'shop_order_status');
    wp_delete_term(intval($status1->term_id), 'shop_order_status');
    $timestamp = wp_next_scheduled('wooc_schedule_send_orders');
    wp_unschedule_event($timestamp, 'wooc_schedule_send_orders');
}
register_deactivation_hook(__FILE__, 'printaura_woocommerce_api_deactivate');

function printaura_woocommerce_api_initialize_plugin()
{
    $helpers = new JSONAPIHelpers();
}
add_action('init', 'printaura_woocommerce_api_initialize_plugin', 5000);

add_action('admin_menu', 'printaura_api_admin_menu', 10);
add_action('admin_head', 'printaura_admin_css');

function printaura_add_wc_api_route($endpoints)
{
    $endpoints['/shipping/zones/store']=array(array( 'create_shipping_zone', WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ));
    $endpoints['/shipping/zones/locations/update']=array(array( 'update_shipping_zone_locations', WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ));
    $endpoints['/shipping/zones/methods/update']=array(array( 'update_shipping_zone_methods',  WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ));
    $endpoints['/shipping/zones']=array(array( 'get_shipping_zone', WC_API_Server::READABLE ));
    $endpoints['/product/shippingclass']=array(array( 'get_pa_product_shipping_class', WC_API_Server::READABLE ));
    $endpoints['/products/tags']=array(array( 'get_pa_product_tags', WC_API_Server::READABLE ));
    $endpoints['/products/tags']=array(array( 'create_pa_product_tags', WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA  ));
    $endpoints['/products/edit_pa']=array(array( 'edit_product_pa', WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA  ));
    $endpoints['/products/delete_pa']=array(array( 'delete_product_pa', WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA  ));
    $endpoints['/store/infos']=array(array( 'get_pa_store_info', WC_API_Server::READABLE ));
    $endpoints['/orders/(?P<order_id>\d+)/shipment']=array(array( 'update_order_item_shipment',  WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ));
    $endpoints['/products/(?P<product_id>\d+)/images']=array(array( 'update_product_images', WC_API_SERVER::CREATABLE |  WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ));
    
    return $endpoints;
}
add_filter('woocommerce_api_endpoints', 'add_wc_api_route', 100, 1);

add_filter('woocommerce_rest_check_permissions', 'printaura_update_permissions', 10, 1);

function printaura_update_permissions($permission)
{
    if (!$permission) {
        $permission = true;
    }
    return $permission;
}

function printaura_get_shipping_zone()
{
    $server = new WC_API_Server($wp->query_vars['wc-api-route']);
    $shipping = new \WC_Shipping_Zones($server);
    return  $shipping->get_zones();
}

function printaura_create_shipping_zone($data)
{
    $zone = new WC_Shipping_Zone(null);
    if (! is_null($data['shipping']['name'])) {
        $zone->set_zone_name(sanitize_text_field($data['shipping']['name']));
        $zone_id = $zone->save();
        return array('zone_id'=>$zone_id);
    }
    return false;
}

function printaura_update_shipping_zone_locations($data)
{
    $server = new WC_API_Server($wp->query_vars['wc-api-route']);
    $shipping = new \WC_Shipping_Zones($server);

    $zone_id = $data['shipping']['zone_id'];
    $zone = $shipping->get_zone($zone_id);

    $raw_locations = $data['shipping']['locations'];
    $locations     = array();
    foreach ($raw_locations as $raw_location) {
        if (empty($raw_location['code'])) {
            continue;
        }
        $type = ! empty($raw_location['type']) ? $raw_location['type'] : 'country';
        if (! in_array($type, array( 'postcode', 'state', 'country', 'continent' ), true)) {
            continue;
        }
        $locations[] = array(
                'code' => $raw_location['code'],
                'type' => $type,
            );
    }
    $zone->set_locations($locations);
    $zone->save();
}

function printaura_update_shipping_zone_methods($data)
{
    $server = new WC_API_Server($wp->query_vars['wc-api-route']);
    $shipping = new \WC_Shipping_Zones($server);

    $zone_id = $data['shipping']['zone_id'];
    $method_id = $data['shipping']['method_id'];
    $zone = $shipping->get_zone($zone_id);

    $instance_id = $zone->add_shipping_method($method_id);
    $methods     = $zone->get_shipping_methods();
    $method      = false;
    foreach ($methods as $method_obj) {
        if ($instance_id === $method_obj->instance_id) {
            $method = $method_obj;
            break;
        }
    }
    if (false === $method) {
        return new WP_Error('woocommerce_rest_shipping_zone_not_created', __('Resource cannot be created.', 'woocommerce'), array( 'status' => 500 ));
    }

    return $method;
}

function printaura_edit_product_pa($data=array())
{
    $server = new WC_API_Server($wp->query_vars['wc-api-route']);
    $product_id = $data['product']['id'];
    $product = new \WC_API_Products($server);
    $return =  $product->edit_product($product_id, $data);
    return $return;
}

function printaura_delete_product_pa($data)
{
    $server = new WC_API_Server($wp->query_vars['wc-api-route']);
    $product = new \WC_API_Products($server);
    return $product->delete_product($data['product'], true);
}

function printaura_create_pa_product_tags($data = array())
{
    try {
        // Permissions check
        if (! current_user_can('manage_product_terms')) {
            throw new WC_API_Exception('woocommerce_api_user_cannot_read_product_tags', __('You do not have permission to read product tags', 'woocommerce'), 401);
        }
        $data = isset($data['tags']) ? $data['tags'] : array();
        $product_tags = array();
        if (empty($data)) {
        } else {
            foreach ($data as $tag) {
                $term = term_exists($tag, 'product_tag');
                if ($term !== 0 && $term !== null) {//term exists
                    $product_tags[] =  current(get_pa_product_tag($term['term_id']));
                } else {//term doesnt exists , create it
                    $term = wp_insert_term($tag, 'product_tag');
                    if (!is_wp_error($term)) {
                        $product_tags[] =  current(get_pa_product_tag($term['term_id']));
                    }
                }
            }
            return array( 'product_tags' => apply_filters('woocommerce_api_product_tags_response', $product_tags) );
        }
    } catch (WC_API_Exception $e) {
        return new WP_Error($e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ));
    }
}

function printaura_get_pa_product_tags($fields = null)
{
    try {
        // Permissions check
        if (! current_user_can('manage_product_terms')) {
            throw new WC_API_Exception('woocommerce_api_user_cannot_read_product_tags', __('You do not have permission to read product tags', 'woocommerce'), 401);
        }
        $product_tags = array();
        $terms = get_terms('product_tag', array( 'hide_empty' => false, 'fields' => 'ids' ));
        foreach ($terms as $term_id) {
            $product_tags[] = sanitize_text_field(current(get_pa_product_tag($term_id, $fields)));
        }
        return array( 'product_tags' => apply_filters('woocommerce_api_product_tags_response', $product_tags, $terms, $fields, $this) );
    } catch (WC_API_Exception $e) {
        return new WP_Error($e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ));
    }
}

function printaura_get_pa_product_tag($id, $fields = null)
{
    try {
        $id = absint($id);
        // Validate ID
        if (empty($id)) {
            throw new WC_API_Exception('woocommerce_api_invalid_product_tag_id', __('Invalid product tag ID', 'woocommerce'), 400);
        }
        // Permissions check
        if (! current_user_can('manage_product_terms')) {
            throw new WC_API_Exception('woocommerce_api_user_cannot_read_product_tags', __('You do not have permission to read product tags', 'woocommerce'), 401);
        }
        $term = get_term($id, 'product_tag');
        if (is_wp_error($term) || is_null($term)) {
            throw new WC_API_Exception('woocommerce_api_invalid_product_tag_id', __('A product tag with the provided ID could not be found', 'woocommerce'), 404);
        }
        $term_id = intval($term->term_id);
        // Get category display type
        $display_type = get_woocommerce_term_meta($term_id, 'display_type');
        // Get category image
        $image = '';
        if ($image_id = get_woocommerce_term_meta($term_id, 'thumbnail_id')) {
            $image = wp_get_attachment_url($image_id);
        }
        $product_category = array(
                'id'          => $term_id,
                'name'        => $term->name,
                'slug'        => $term->slug,
                'parent'      => $term->parent,
                'description' => $term->description,
                'display'     => $display_type ? $display_type : 'default',
                'image'       => $image ? esc_url($image) : '',
                'count'       => intval($term->count)
            );
        return array( 'product_category' => apply_filters('woocommerce_api_product_category_response', $product_category, $id, $fields, $term, $this) );
    } catch (WC_API_Exception $e) {
        return new WP_Error($e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ));
    }
}
        
function printaura_get_pa_store_info()
{
    // General site data
    $available = array( 'store' => array(
            'name'        => sanitize_option('blogname',get_option('blogname')),
            'description' => sanitize_option('blogdescription',get_option('blogdescription')),
            'URL'         => sanitize_option('siteurl',get_option('siteurl')),
            'wc_version'  => WC()->version,
      'pa_version'  => '3.4.10'));
                
    return $available;
}

function printaura_update_order_item_shipment($order_id, $data)
{
    $data        = isset($data['order']) ? $data['order'] : array();
    $send_mail   = (isset($data['notify_customer']) && $data['notify_customer'] == true) ? $data['notify_customer'] : false;

    try {
        $id = validate_request_pa($order_id, 'shop_order', 'edit');
            
        if (is_wp_error($id)) {
            return $id;
        }
                        
        $order = wc_get_order($id);
                       
        $line_items = (!empty($data['line_items'])) ? $data['line_items'] : array();
                       
        if (!empty($line_items)) {
            foreach ($line_items as  $line_item) {
                if (! array_key_exists('id', $line_item)) {
                    throw new WC_API_Exception('woocommerce_invalid_item_id', __('Order item ID is required', 'woocommerce'), 400);
                }
                $line_item_id    = $line_item['id'];
                $tracking_number = sanitize_text_field($line_item['tracking']);
                wc_update_order_item_meta($line_item_id, 'tracking_number', $tracking_number);
            }
        }
                        
        $total_items     = count($order->get_items());
        $total_shipped   = get_total_shipped_items($order_id);
        $ship_method     = $order->get_shipping_methods() ;
        foreach ($ship_method as $shp_mtd) {
            $shipping_method = sanitize_text_field($shp_mtd['name']);
            break; //#KLUDGE this does not actually appear to handle the case of multiple ship methods or its absence. Is this correct? cn 20190403
        }

        if ($total_items == $total_shipped) {
            $order->update_status('shipped', '');
            if ($send_mail) {
                send_ship($order_id, $tracking_number, $shipping_method);
            }
            return array('line_items'=>$order->get_items());
        } else {
            $order->update_status('wc-partially-shipped', '');
            if ($send_mail) {
                send_ship($order_id, $tracking_number, $shipping_method);
            }
            return array('line_items'=>$order->get_items());
        }
    } catch (WC_API_Exception $e) {
        return new WP_Error($e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ));
    }
}
function printaura_update_product_images($product_id, $data)
{
    @ini_set('memory_limit', '600M');
    $images = $data['product'];
    $id = validate_request_pa($product_id, 'product', 'edit');

    if (is_wp_error($id)) {
        return $id;
    }
    $product = new WC_Product_Variable($product_id);

    $images_ids = array();
    if (is_array($images)) {
        $gallery = array();
        foreach ($images as $image) {
            if (isset($image['position']) && 0 == $image['position']) {
                $attachment_id = isset($image['id']) ? absint($image['id']) : 0;

                if (0 === $attachment_id && isset($image['src'])) {
                    $upload = pa_upload_product_image(esc_url_raw($image['src']));

                    if (is_wp_error($upload)) {
                        throw new WC_API_Exception('woocommerce_api_cannot_upload_product_image', $upload->get_error_message(), 400);
                    }
                    $attachment_id = pa_set_product_image_as_attachment($upload, $product->get_id());
                }
                if (method_exists($product, 'set_image_id')) {
                    $product->set_image_id($attachment_id);
                } else {
                    set_post_thumbnail($product_id, $attachment_id);
                }
            } else {
                $attachment_id = isset($image['id']) ? absint($image['id']) : 0;

                if (0 === $attachment_id && isset($image['src'])) {
                    $upload = pa_upload_product_image(esc_url_raw($image['src']));

                    if (is_wp_error($upload)) {
                        throw new WC_API_Exception('woocommerce_api_cannot_upload_product_image', $upload->get_error_message(), 400);
                    }
                    $gallery[] = pa_set_product_image_as_attachment($upload, $product_id);
                } else {
                    $gallery[] = $attachment_id;
                }
                $images_ids[] = $attachment_id;
            }
        }
        if (! empty($gallery)) {
            if (method_exists($product, 'set_gallery_image_ids')) {
                $product->set_gallery_image_ids($gallery);
            } else {
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery));
            }
        }
    } else {
        if (method_exists($product, 'set_gallery_image_ids')) {
            $product->set_image_id('');
            $product->set_gallery_image_ids(array());
        } else {
            delete_post_thumbnail($product_id);
            update_post_meta($product_id, '_product_image_gallery', '');
        }
    }
    if (method_exists($product, 'save')) {
        $product->save();
    }

    return array('images'=>pa_get_images($product));
}

function printaura_pa_upload_product_image($image_url)
{
    $file_name  = basename(current(explode('?', $image_url)));
    $parsed_url = @parse_url($image_url);
    // Check parsed URL
    if (! $parsed_url || ! is_array($parsed_url)) {
        throw new WC_API_Exception('woocommerce_api_invalid_product_image', sprintf(__('Invalid URL %s.', 'woocommerce'), $image_url), 400);
    }
    // Ensure url is valid
    $image_url = str_replace(' ', '%20', $image_url);
    // Get the file
    $response = wp_safe_remote_get($image_url, array(
            'timeout' => 100,
        ));
    if (is_wp_error($response)) {
        throw new WC_API_Exception('woocommerce_api_invalid_remote_product_image', sprintf(__('Error getting remote image %s.', 'woocommerce'), $image_url) . ' ' . sprintf(__('Error: %s.', 'woocommerce'), $response->get_error_message()), 400);
    } elseif (200 !== wp_remote_retrieve_response_code($response)) {
        throw new WC_API_Exception('woocommerce_api_invalid_remote_product_image', sprintf(__('Error getting remote image %s.', 'woocommerce'), $image_url), 400);
    }
    // Ensure we have a file name and type
    $wp_filetype = wp_check_filetype($file_name, wc_rest_allowed_image_mime_types());
    if (! $wp_filetype['type']) {
        $headers = wp_remote_retrieve_headers($response);
        if (isset($headers['content-disposition']) && strstr($headers['content-disposition'], 'filename=')) {
            $disposition = end(explode('filename=', $headers['content-disposition']));
            $disposition = sanitize_file_name($disposition);
            $file_name   = $disposition;
        } elseif (isset($headers['content-type']) && strstr($headers['content-type'], 'image/')) {
            $file_name = 'image.' . str_replace('image/', '', $headers['content-type']);
        }
        unset($headers);
        // Recheck filetype
        $wp_filetype = wp_check_filetype($file_name, wc_rest_allowed_image_mime_types());
        if (! $wp_filetype['type']) {
            throw new WC_API_Exception('woocommerce_api_invalid_product_image', __('Invalid image type.', 'woocommerce'), 400);
        }
    }
    // Upload the file
    $upload = wp_upload_bits($file_name, '', wp_remote_retrieve_body($response));
    if ($upload['error']) {
        throw new WC_API_Exception('woocommerce_api_product_image_upload_error', $upload['error'], 400);
    }
    // Get filesize
    $filesize = filesize($upload['file']);
    if (0 == $filesize) {
        @unlink($upload['file']);
        unset($upload);
        throw new WC_API_Exception('woocommerce_api_product_image_upload_file_error', __('Zero size file downloaded.', 'woocommerce'), 400);
    }
    unset($response);
    return $upload;
}
function printaura_pa_set_product_image_as_attachment($upload, $id)
{
    $info    = wp_check_filetype($upload['file']);
    $title   = '';
    $content = '';
    if ($image_meta = @wp_read_image_metadata($upload['file'])) {
        if (trim($image_meta['title']) && ! is_numeric(sanitize_title($image_meta['title']))) {
            $title = wc_clean($image_meta['title']);
        }
        if (trim($image_meta['caption'])) {
            $content = wc_clean($image_meta['caption']);
        }
    }
    $attachment = array(
            'post_mime_type' => $info['type'],
            'guid'           => $upload['url'],
            'post_parent'    => $id,
            'post_title'     => $title,
            'post_content'   => $content,
        );
    $attachment_id = wp_insert_attachment($attachment, $upload['file'], $id);
    if (! is_wp_error($attachment_id)) {
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
    }
    return $attachment_id;
}

function printaura_pa_get_images($product)
{
    $images        = $attachment_ids = array();
    $product_image = $product->get_image_id();
    // Add featured image.
    if (! empty($product_image)) {
        $attachment_ids[] = $product_image;
    }
    // Add gallery images.
    if (method_exists($product, 'get_gallery_image_ids')) {
        $attachment_ids = array_merge($attachment_ids, $product->get_gallery_image_ids());
    } else {
        $attachment_ids = array_merge($attachment_ids, $product->get_gallery_attachment_ids());
    }
    // Build image data.
    foreach ($attachment_ids as $position => $attachment_id) {
        $attachment_post = get_post($attachment_id);
        if (is_null($attachment_post)) {
            continue;
        }
        $attachment = wp_get_attachment_image_src($attachment_id, 'full');
        if (! is_array($attachment)) {
            continue;
        }
        $images[] = array(
                'id'         => (int) $attachment_id,
                'src'        => current($attachment),
                'title'      => get_the_title($attachment_id),
                'alt'        => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                'position'   => (int) $position,
            );
    }
    // Set a placeholder image if the product has no images set.
    if (empty($images)) {
        $images[] = array(
                'id'         => 0,
                'src'        => wc_placeholder_img_src(),
                'title'      => __('Placeholder', 'woocommerce'),
                'alt'        => __('Placeholder', 'woocommerce'),
                'position'   => 0,
            );
    }
    return $images;
}
function printaura_get_total_shipped_items($order_id)
{
    $order          = wc_get_order($order_id);
    $line_items          = $order->get_items();
    $total_shipped  = 0;
    foreach ($line_items as $line_item) {
        $tracking  = "";
        if (isset($line_item['tracking_number']) && strlen($line_item['tracking_number'])>0) {
            $tracking = wc_clean($line_item['tracking_number']);
        }

        if ($tracking !='' && strlen($tracking) > 0) {
            $total_shipped++;
        }
    }

    return $total_shipped;
}

function printaura_get_pa_product_shipping_class($fields = null)
{
    // permissions check
    if (! current_user_can('manage_product_terms')) {
        return new WP_Error("woocommerce_api_user_cannot_read_product_categories", __('You do not have permission to read product categories', 'woocommerce'), array( 'status' => 401 ));
    }

    $product_categories = array();

    $terms = get_terms('product_shipping_class', array( 'hide_empty' => false, 'fields' => 'ids' ));

    foreach ($terms as $term_id) {
        $product_categories[] = current(get_pa_product_shipping_class_item($term_id, $fields));
    }
    
    $product_categories = wc_clean($product_categories);
    //return $product_categories;
    return array( 'product_shipping_class' => apply_filters('woocommerce_api_product_categories_response', $product_categories, $terms, $fields) );
    exit();
}

function printaura_get_pa_product_shipping_class_item($id, $fields = null)
{
    $id = absint($id);

    // validate ID
    if (empty($id)) {
        return new WP_Error('woocommerce_api_invalid_product_category_id', __('Invalid product category ID', 'woocommerce'), array( 'status' => 400 ));
    }

    // permissions check
    if (! current_user_can('manage_product_terms')) {
        return new WP_Error('woocommerce_api_user_cannot_read_product_categories', __('You do not have permission to read product categories', 'woocommerce'), array( 'status' => 401 ));
    }

    $term = get_term($id, 'product_shipping_class');

    if (is_wp_error($term) || is_null($term)) {
        return new WP_Error('woocommerce_api_invalid_product_category_id', __('A product category with the provided ID could not be found', 'woocommerce'), array( 'status' => 404 ));
    }

    $product_category = array(
            'id'          => intval($term->term_id),
            'name'        => $term->name,
            'slug'        => $term->slug,
            'parent'      => $term->parent,
            'description' => $term->description,
            'count'       => intval($term->count),
        );

    return array( 'product_shipping_class' => apply_filters('woocommerce_api_product_category_response', $product_category, $id, $fields, $term) );
}
         
function printaura_validate_request_pa($id, $type, $context)
{
    if ('shop_order' === $type || 'shop_coupon' === $type || 'shop_webhook' === $type) {
        $resource_name = str_replace('shop_', '', $type);
    } else {
        $resource_name = $type;
    }

    $id = absint($id);

    // validate ID
    if (empty($id)) {
        return new WP_Error("woocommerce_api_invalid_{$resource_name}_id", sprintf(__('Invalid %s ID', 'woocommerce'), $type), array( 'status' => 404 ));
    }

    // only custom post types have per-post type/permission checks
    if ('customer' !== $type) {
        $post = get_post($id);

        // for checking permissions, product variations are the same as the product post type
        $post_type = ('product_variation' === $post->post_type) ? 'product' : $post->post_type;

        // validate post type
        if ($type !== $post_type) {
            return new WP_Error("woocommerce_api_invalid_{$resource_name}", sprintf(__('Invalid %s', 'woocommerce'), $resource_name), array( 'status' => 404 ));
        }

        // validate permissions
        switch ($context) {

                case 'read':
                    if (! is_pa_readable($post)) {
                        return new WP_Error("woocommerce_api_user_cannot_read_{$resource_name}", sprintf(__('You do not have permission to read this %s', 'woocommerce'), $resource_name), array( 'status' => 401 ));
                    }
                    break;

                case 'edit':
                    if (! is_pa_editable($post)) {
                        return new WP_Error("woocommerce_api_user_cannot_edit_{$resource_name}", sprintf(__('You do not have permission to edit this %s', 'woocommerce'), $resource_name), array( 'status' => 401 ));
                    }
                    break;

                case 'delete':
                    if (! is_pa_deletable($post)) {
                        return new WP_Error("woocommerce_api_user_cannot_delete_{$resource_name}", sprintf(__('You do not have permission to delete this %s', 'woocommerce'), $resource_name), array( 'status' => 401 ));
                    }
                    break;
            }
    }

    return $id;
}
        
function printaura_check_permission_pa($post, $context)
{
    if (! is_a($post, 'WP_Post')) {
        $post = get_post($post);
    }

    if (is_null($post)) {
        return false;
    }

    $post_type = get_post_type_object($post->post_type);

    if ('read' === $context) {
        return current_user_can($post_type->cap->read_private_posts, $post->ID);
    } elseif ('edit' === $context) {
        return current_user_can($post_type->cap->edit_post, $post->ID);
    } elseif ('delete' === $context) {
        return current_user_can($post_type->cap->delete_post, $post->ID);
    } else {
        return false;
    }
}
        
function printaura_is_pa_readable($post)
{
    return check_permission_pa($post, 'read');
}
        
function printaura_is_pa_editable($post)
{
    return check_permission_pa($post, 'edit');
}
        
function printaura_is_pa_deletable($post)
{
    return check_permission_pa($post, 'delete');
}

function printaura_add_pa_order_statuses($order_statuses)
{
    $order_statuses['wc-shipped'] = _x('Shipped', 'Order status', 'woocommerce');
    $order_statuses['wc-parially-shipped'] = _x('Partially Shipped', 'Order status', 'woocommerce');
    
    return $order_statuses;
}
add_filter('wc_order_statuses', 'printaura_add_pa_order_statuses');

function printaura_pa_attach_tags($product_id, $terms, $type='cat')
{
    if (!empty($terms)) {
        $product_terms= array();
        foreach ($terms as $ter) {
            $term = term_exists($ter, 'product_'.$type);
                   
            if ($term !== 0 && $term !== null) {//term exists
                $product_terms[] = (int)  $term['term_id'] ;
            } else {//term doesnt exists , create it
                $term = wp_insert_term($ter, 'product_'.$type);

                if (!is_wp_error($term)) {
                    $product_terms[] = (int) $term['term_id'];
                }
            }
        }
        
        if (!empty($product_terms)) {
            wp_set_object_terms($product_id, $product_terms, 'product_'.$type);
        }
    }
}

function printaura_pa_after_add_product($id, $data)
{
    pa_attach_tags($id, $data['tags'], 'tag');
}
add_action('woocommerce_api_create_product', 'pa_after_add_product', 2, 2);

function printaura_pa_after_edit_product($id, $data)
{
    $product        = new WC_Product_Variable($id);
    $variations     = $product->get_available_variations();
    $all_attributes = array();
    foreach ($data['attributes'] as $attributes) {
        if (is_array($attributes['options'])) {
            $all_attributes = array_merge($all_attributes, $attributes['options']);
        } else {
            $all_attributes = array_merge($all_attributes, explode(' | ', $attributes['options']));
        }
    }
         
    foreach ($all_attributes as $key=>$value) {
        $all_attributes[] = sanitize_title(strtolower($value));
    }

    foreach ($variations as $variation) {
        foreach ($variation['attributes'] as $attr=>$attribute_value) {
            if (in_array($attr, array('attribute_color','attribute_size','attribute_pa_color','attribute_pa_size','attribute_pa_color-g','attribute_pa_size-g'))) {
                //$attribute_value = implode(" ",explode('-',$attribute_value));
                       
                if (!in_array($attribute_value, $all_attributes)) {
                    wp_delete_post($variation['variation_id'], true) ;
                    break;
                }
            }
        }
    }
     
    pa_attach_tags($id, $data['tags'], 'tag');
}
add_action('woocommerce_api_edit_product', 'pa_after_edit_product', 2, 2);

function printaura_append_woocommerce_key_to_payload($payload, $resource = "", $resource_id ="", $webhook_id = "")
{
    global $wpdb;
    $keys1 = $wpdb->get_col("SELECT  consumer_key FROM {$wpdb->prefix}woocommerce_api_keys");
    $consumer_key = $wpdb->get_col('select meta_value from '.$wpdb->prefix.'usermeta where meta_key="woocommerce_api_consumer_key"');
    if (is_array($payload)) {
        $payload['key']   = array_merge($consumer_key, $keys1);
    }
    return $payload;
}
add_filter('woocommerce_webhook_payload', 'printaura_append_woocommerce_key_to_payload');

function printaura_increase_max_webhook_failure($count)
{
    return $count+1000000000000;
}
add_filter('woocommerce_max_webhook_delivery_failures', 'increase_max_webhook_failure');

function printaura_fix_woocommrce_description($id, $data)
{
    //global $wpdb;
    $description       = stripslashes(wp_filter_post_kses(addslashes($data['description'])));
    $short_description = stripslashes(wp_filter_post_kses(addslashes($data['short_description'])));
    $my_post = array(
      'ID'           => $id,
      'post_content' => $description,
      'post_excerpt' => $short_description
  );

    // Update the post into the database
    wp_update_post($my_post);
}
add_action('woocommerce_api_create_product', 'fix_woocommrce_description', 10, 2);
add_action('woocommerce_api_edit_product', 'fix_woocommrce_description', 10, 2);

function printaura_fix_order_hook($topic_hooks)
{
    $topic_hooks['order.created'][]  = 'woocommerce_order_status_processed';
    $topic_hooks['order.updated'][]  = 'woocommerce_order_status_processed';
  
    return $topic_hooks;
}
add_filter('woocommerce_webhook_topic_hooks', 'printaura_fix_order_hook');

function printaura_send_woo_processed($id)
{
    $query_args = array(
            'fields'      => 'ids',
            'post_type'   => 'shop_webhook',
        );
    $webhooks = new WP_Query($query_args);
    if ($webhooks) {
        foreach ($webhooks->posts as $webhook_id) {
            $webhook = new WC_Webhook($webhook_id);
            if ($webhook->get_topic() == 'order.created') {
                $webhook->process($id);
                break;
            }
        }
    }
}
add_action('woocommerce_order_status_processing', 'printaura_send_woo_processed');

function printaura_register_pa_custom_order_status()
{
    @ini_set('upload_max_size', '200M');
    @ini_set('post_max_size', '200M');
    @ini_set('max_execution_time', '800');
    register_post_status('wc-shipped', array(
        'label'                     => 'Shipped',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Shipped <span class="count">(%s)</span>', 'Shipped <span class="count">(%s)</span>')
    ));
    register_post_status('wc-partially-shipped', array(
        'label'                     => 'Partially Shipped',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Partially Shipped <span class="count">(%s)</span>', 'Partially Shipped <span class="count">(%s)</span>')
    ));
}
add_action('init', 'printaura_register_pa_custom_order_status');

function printaura_add_pa_custom_order_statuses($order_statuses)
{
    $new_order_statuses = array();
 
    // add new order status after processing
    foreach ($order_statuses as $key => $status) {
        $new_order_statuses[ $key ] = $status;
 
        if ('wc-processing' === $key) {
            $new_order_statuses['wc-shipped'] = 'Shipped';
            $new_order_statuses['wc-partially-shipped'] = 'Patially Shipped';
        }
    }
 
    return $new_order_statuses;
}
add_filter('wc_order_statuses', 'printaura_add_pa_custom_order_statuses');

function printaura_fix_password_length_webhook($webhook_data)
{
    $webhook_data['post_password'] = substr($webhook_data['post_password'], 0, 19);
    return $webhook_data;
}
add_filter('woocommerce_new_webhook_data', 'fix_password_length_webhook');

function printaura_wcs_maybe_force_webhook_delivery($schedule_delivery, $webhook, $subscription = null)
{
    if (! is_object($webhook)) {
        $webhook = new WC_Webhook($webhook);
    }
    $topic = $webhook->get_topic();
    if ($topic=='order.updated' || $topic == "order.created") {
        return false;
    }
    return true;
}

add_filter('woocommerce_webhook_deliver_async', 'printaura_wcs_maybe_force_webhook_delivery', 10, 3);
