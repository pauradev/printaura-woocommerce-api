<?php
if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
/**
 * Add fields to the user profile that allow the addition
 * of a "token" that can be used by the API to log that
 * user into the system for performing actions.
 *
 * The API is not limited to admins, in fact, the general idea
 * is to limit the API by applying whatever limits apply
 * to the user.
 *
 * @param $user the user
*/
require_once(plugin_dir_path(__FILE__) . 'classes/class-wc-json-api.php');

/*
  Prevent template code from loading :)
*/
function printaura_api_template_redirect()
{
    global $wpdb;
    $helpers = new JSONAPIHelpers();

    $headers = printaura_api_parse_headers();
    if (isset($headers['Content-Type']) && $headers['Content-Type'] === 'application/json') {
        $fp = @fopen('php://input', 'r');
        $body = '';
        if ($fp) {
            while (!feof($fp)) {
                $buf = fread($fp, 1024);
                if (is_string($buf)) {
                    $body .= $buf;
                }
            }
            fclose($fp);
        }
        $hash = json_decode($body, true);
        foreach ($hash as $key => $value) {
            $_REQUEST[$key] = $value;
        }
    }
    if (!isset($_REQUEST['action']) || sanitize_text_field($_REQUEST['action']) != 'printaura_api') {
        return;
    }
    if (is_user_logged_in()) {
        return;
    }


    JSONAPIHelpers::debug(var_export($headers, true));
    if (isset($_REQUEST['action']) && 'printaura_api' == sanitize_text_field($_REQUEST['action'])) {
        $enabled = get_option($helpers->getPluginPrefix() . '_enabled');
        $require_https = get_option($helpers->getPluginPrefix() . '_require_https');
        if ($enabled != 'no') {
            if ($require_https == 'yes' && $helpers->isHTTPS() == false) {
                JSONAPIHelpers::debug("Cannot continue, HTTPS is required.");
                return;
            }
            if (defined('WC_JSON_API_DEBUG')) {
                JSONAPIHelpers::truncateDebug();
            }
            $api = new WooCommerce_JSON_API();
            $api->setOut('HTTP');
            $api->setUser(null);
            $params = array();
            // maybe we had to serialize some subarrays, so we'll have to unserialize them here
            foreach ($_REQUEST as $key=>$value) {
                $params[$key] = $value;
            }
            foreach (array('payload','arguments','model_filters','wordpress_filters') as $key) {
                if (isset($_REQUEST[$key]) && is_string($_REQUEST[$key])) {
                    $params[$key] = json_decode(stripslashes($_REQUEST[$key]), true);
                }
            }
            $api->route($params);
        } else {
            JSONAPIHelpers::debug("JSON API is not set to enabled.");
        }
    }
}

function printaura_admin_css()
{
    wp_enqueue_style('fixed-style', plugins_url('/assets/style.css', __FILE__));
}

function printaura_api_admin_menu()
{
    global $menu;

    add_menu_page(
        'Print Aura Woocommerce API',
        'Print Aura API',
        'manage_woocommerce',
        'api_settings_page',
        'printaura_api_settings_page'
    );
}

function printaura_save_new_zone($zones)
{
    $old_zone_title = array();
    $old_zone_country = array();
    foreach ($zones as $key => $zone) {
        extract($zone);
        $shipping_zones = be_get_zones();
        $check_exist = false;
        if ($shipping_zones) {
            foreach ($shipping_zones as $value) {
                $max_keys[] = $value['zone_order'];
                $old_zone_title[]   = wp_strip_all_tags($value['zone_title']);
                $old_zone_country[] = wp_strip_all_tags($value['zone_country']);
            }
            if (in_array(strtolower($zone_title), $old_zone_title) || in_array($zone_country, $old_zone_country)) {
                $check_exist = true;
            }
            $zone_order_max = max($max_keys);
            $zoneID = max(array_keys($shipping_zones))+1;
        } else {
            $zone_order_max = 0;
            $zoneID = 1;
        }
        if (!$check_exist) {
            $shipping_zones[$zoneID] = array(
                'zone_id' => $zoneID,
                'zone_enabled' => '1',
                'zone_title' => $zone_title,
                'zone_description' => '',
                'zone_type' => 'countries',
                'zone_country' => $zone_country,
                'zone_postal' => '',
                'zone_except' => $zone_except,
                'zone_order' => $zone_order_max + 1,
                );
        }
        update_option('be_woocommerce_shipping_zones', $shipping_zones);
    }
    return $shipping_zones;
}

function printaura_api_settings_page()
{
    $helpers = new JSONAPIHelpers();
    $current_user=wp_get_current_user();

    $key5 = $helpers->getPluginPrefix() . '_enabled';
    $key3 = $helpers->getPluginPrefix() . '_token';
    $key4 = $helpers->getPluginPrefix() . '_ips_allowed';
    $params = $_POST;
    $nonce = $helpers->orEq($params, '_wpnonce', false);
    $key = $helpers->getPluginPrefix() . '_sitewide_settings';

    if ($nonce  && wp_verify_nonce($nonce, $helpers->getPluginPrefix() . '_sitewide_settings') && isset($params[$key])) {
        foreach ($params[$key] as $key2=>$value) {
            //maybe_serialize serializes serialized values so handling just in case cn 20190401
            update_option($helpers->getPluginPrefix() . '_' . $key2, (is_serialized($value) ? $value : maybe_serialize($value)));
        }
        if (isset($_POST[$key]['enabled'])) {
            $sanitized_token = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST[$key]['token']);
            update_option($key3, $sanitized_token);
            update_user_meta($current_user->ID, $key3, $sanitized_token);
        }
        if (isset($_POST[$key]['enabled'])) {
            update_option($key5, sanitize_text_field($_POST[$key]['enabled']));
        }
        if (isset($_POST[$key]['ips_allowed'])) {
            update_option($key4, sanitize_text_field($_POST[$key]['ips_allowed']));
        }

        if (is_plugin_active('woocommerce-table-rate-shipping/woocommerce-table-rate-shipping.php')) {
            deactivate_plugins('woocommerce-table-rate-shipping/woocommerce-table-rate-shipping.php');
        }

        $table_rate_shipping_enabled = get_option($helpers->getPluginPrefix() . '_table_rate_shipping');
        if ($table_rate_shipping_enabled === "yes") {
            update_option($helpers->getPluginPrefix().'_table_rate_shipping', 'yes');
            do_action('plugins_loaded');
            $zone1 = array(
            'zone_title'=>'Florida',
            'zone_country'=>'US:FL',
            'zone_except'=> array('states' => '','postals' => '')
            );
            $zone2 = array(
            'zone_title'=>'Quebec',
            'zone_country'=>'CA:QC',
            'zone_except'=> array('states' => '','postals' => '')
            );
            $zones = array($zone1,$zone2);
            $shipping_zones = printaura_save_new_zone($zones);
        }
    }

    $attrs = array(
      'json_api_sitewide_settings' => array(
          'title' => __('Print Aura Woocommerce API Settings', 'printaura_api'),
          'fields' => array(
                            array(
                                'name'          => $helpers->getPluginPrefix() . '_sitewide_settings[table_rate_shipping]',
                                'id'            => 'json_api_table_rate_shipping',
                                'visible'       => true,
                                'value'         => get_option($helpers->getPluginPrefix() . '_table_rate_shipping'),
                                'options'       => array(
                                   array( 'value' => 'no', 'content' => __('No', 'printaura_api')),
                                   array( 'value' => 'yes', 'content' => __('Yes', 'printaura_api')),
                                ),
                                'type'          => 'select',
                                'label'         => __('WooCommerce Table Rate Shipping Enabled?', 'printaura_api'),
                                'description'   => __('woocommerce calculate shipping costs and add one or more rates based on a table of rules', 'printaura_api'),
                                ),
                            array(
                                'name'          => $helpers->getPluginPrefix() . '_sitewide_settings[enabled]',
                                'id'            => 'json_api_enabled_id',
                                'visible'       => false,
                                'value'         => get_option($helpers->getPluginPrefix() . '_enabled'),
                                'options'       => array(
                                    array( 'value' => 'yes', 'content' => __('Yes', 'printaura_api')),
                                    array( 'value' => 'no', 'content' => __('No', 'printaura_api')),
                                ),
                                'type'          => 'select',
                                'label'         => __('API Enabled?', 'printaura_api'),
                                'description'   => __('Quickly enable/disable The API', 'printaura_api'),
                            ),
                            array(
                                'name'          => $helpers->getPluginPrefix() . '_sitewide_settings[token]',
                                'id'            => 'json_api_token_id',
                                'visible'       => false,
                                'value'         => get_option($helpers->getPluginPrefix() . '_token'),
                                'type'          => 'text',
                                'label'         => __('API Token', 'printaura_api'),
                                'description'   => __('You will need to enter this on<br />
                                    <a class="orange" href="https://printaura.com/woocommerce/" target="_blank">https://printaura.com/woocommerce</a> to setup
                                    the app', 'printaura_api')
                            )
                      ),
          ),
    );

    $attrs = apply_filters('printaura_api_sitewide_settings_fields', $attrs);

    echo $helpers->renderTemplate('admin-settings-page.php', array( 'attrs' => $attrs));
}

function printaura_api_parse_headers()
{
    $headers = array();
    foreach ($_SERVER as $key=>$value) {
        if (substr($key, 0, 5) == 'HTTP_') {
            continue;
        }
        $h = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower($key))));
        $headers[$h] = $value;
    }
    return $headers;
}

function printaura_unhook_those_pesky_emails($email_class)
{
    remove_action('woocommerce_order_status_completed_notification', array(&$email_class, 'customer_completed_order'));
}
