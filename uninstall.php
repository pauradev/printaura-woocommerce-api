<?php
if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
define('PRINTAURA_REDE_PLUGIN_BASE_PATH', plugin_dir_path(__FILE__));
require_once(plugin_dir_path(__FILE__) . 'classes/class-rede-helpers.php');
require_once(plugin_dir_path(__FILE__) . 'printaura-woocommerce-api-core.php');
function printaura_woocommerce_json_api_uninstall()
{
    global $wpdb;
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        exit;
    }
    $helpers = new Printaura_JSONAPIHelpers();

    $json_api_slug = get_option($helpers->getPluginPrefix() . '_slug');
    if (! $json_api_slug) {
        // Ooops, no api slug?
    } else {
        $found = $wpdb->get_var($wpdb->prepare("SELECT ID FROM " . $wpdb->posts . " WHERE post_name = %s LIMIT 1;", $page['post_name']));
        if ($found) {
            wp_delete_post($found, true);
        }
    }
} // end woocommerce_json_api_activate
printaura_woocommerce_json_api_uninstall();
