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
function printaura_api_get_api_settings_array($user_id, $default_token='', $default_ips='')
{
    $helpers = new Printaura_JSONAPIHelpers();
    $key = $helpers->getPluginPrefix() . '_settings';
    $meta = maybe_unserialize(get_user_meta($user_id, $key, true));
    $attrs = array(
    'json_api_settings' => array(
      'title' => __('WooCommerce JSON API Settings', 'printaura_api'),
      'fields' => array(
          array(
            'name'          => $helpers->getPluginPrefix() . '_settings[token]',
            'id'            => 'json_api_token_id',
            'value'         => $helpers->orEq($meta, 'token', $default_token),
            'type'          => 'text',
            'label'         => __('API Token', 'printaura_api'),
            'description'   => __('A large string of letters and numbers, mixed case, that will be used to authenticate requests', 'printaura_api')
          ),
          array(
            'name'          => $helpers->getPluginPrefix() . '_settings[ips_allowed]',
            'id'            => 'json_api_ips_allowed_id',
            'value'         => $helpers->orEq($meta, 'ips_allowed', $default_ips),
            'type'          => 'textarea',
            'label'         => __('IPs Allowed', 'printaura_api'),
            'description'   => __('What ips are permitted to connect with this user...', 'printaura_api')
          ),
      ),
    ),
  );
    // Here we implement some permissions, a simple yes/no.
    $method = 'access_the_api';
    $can_access_api="no";
    if (current_user_can('manage_options')) {
        $can_access_api="yes";
    }
    $field = array(
      'name'          => $helpers->getPluginPrefix() . '_settings[can_' . $method . ']',
      'id'            => 'json_api_can_' . $method . '_id',
      'value'         => /*$helpers->orEq($meta,'can_' . $method, 'yes')*/$can_access_api,
      'type'          => 'select',
      'options'       => array(
          array( 'value' => 'yes', 'content' => __('Yes', 'printaura_api') ),
          array( 'value' => 'no', 'content' => __('No', 'printaura_api') ),
        ),
      'label'         => __('Can access ', 'printaura_api') . ucwords(str_replace('_', ' ', $method)),
      'description'   => __('Whether or not this user can access this method', 'printaura_api')
    );
    $attrs['json_api_settings']['fields'][] = $field;
    foreach (Printaura_WooCommerce_JSON_API::getImplementedMethods() as $method) {
        if (strpos($method, 'set_') !== false) {
            $default_value = 'yes';
        } else {
            $default_value = 'yes';
        }
        $field = array(
      'name'          => $helpers->getPluginPrefix() . '_settings[can_' . $method . ']',
      'id'            => 'json_api_can_' . $method . '_id',
      'value'         => $helpers->orEq($meta, 'can_' . $method, $default_value),
      'type'          => 'select',
      'options'       => array(
          array( 'value' => 'yes', 'content' => __('Yes', 'printaura_api') ),
          array( 'value' => 'no', 'content' => __('No', 'printaura_api') ),
        ),
      'label'         => __('Can access ', 'printaura_api') . ucwords(str_replace('_', ' ', $method)),
      'description'   => __('Whether or not this user can access this method', 'printaura_api')
    );
        $attrs['json_api_settings']['fields'][] = $field;
    }

    $attrs = apply_filters('printaura_api_settings_fields', $attrs);
    return $attrs;
}
function printaura_api_show_user_profile($user)
{
    if (current_user_can('manage_options')) {
        $helpers = new Printaura_JSONAPIHelpers();
        // We use PluginPrefic, which is just the plugin name
        // with - replaced with _, easier to type and more
        // extensible.
        $key = $helpers->getPluginPrefix() . '_settings';
        $meta = maybe_unserialize(get_user_meta($user->ID, $key, true));
  
        $attrs = printaura_api_get_api_settings_array($user->ID);
        // The second argument puts this var in scope, similar to a
        // "binding" in Ruby
        $content = $helpers->renderTemplate('user-fields.php', array( 'attrs' => $attrs));
        // At this point, content is being rendered in an output buffer for absolute control.
        // You can still overwrite the templates in the usual way, as the current theme is scanned
        // first. There are also hooks defined before and after, that will allow you to alter, replace,
        // or extend the content.
  
        echo $content;
    }
}

/**
  *  Here we just pass this off to the above function: printaura_api_show_user_profile( $user )
*/
function printaura_api_edit_user_profile($user)
{
    printaura_api_show_user_profile($user);
}
/**
 * Here we edit the key, which should only be printaura_api_settings
 * at this point, though more info and keys could be added.
 *
 * Here we are trying to simply use one key that is a serialized array
 * of all the little bits of info we need.
*/
function printaura_api_update_user_profile($user_id)
{
    if (current_user_can('manage_options')) {
        $helpers = new Printaura_JSONAPIHelpers();
        $key = $helpers->getPluginPrefix() . '_settings';
        $params = serialize($_POST[$key]);
        update_user_meta($user_id, $key, $params);
        // Need this for faster access to users.
        $key2 = $helpers->getPluginPrefix() . '_api_token';
        update_user_meta($user_id, $key2, $_POST[$key]['token']);
    }
}

/*
  Prevent template code from loading :)
*/
function printaura_api_template_redirect()
{
    global $wpdb;
    $helpers = new Printaura_JSONAPIHelpers();

    $headers = printaura_api_parse_headers();
    if (isset($headers['Content-Type']) && $headers['Content-Type'] == 'application/json') {
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
    if (!isset($_REQUEST['action']) || $_REQUEST['action'] != 'printaura_api') {
        //die("action not set");
        return;
    }
    if (is_user_logged_in()) {
        //die("user is logged in");
        return;
    }
  

    Printaura_JSONAPIHelpers::debug(var_export($headers, true));
    if (isset($_REQUEST['action']) && 'printaura_api' == $_REQUEST['action']) {
        $enabled = get_option($helpers->getPluginPrefix() . '_enabled');
        $require_https = get_option($helpers->getPluginPrefix() . '_require_https');
        if ($enabled != 'no') {
            if ($require_https == 'yes' && $helpers->isHTTPS() == false) {
                Printaura_JSONAPIHelpers::debug("Cannot continue, HTTPS is required.");
                return;
            }
            if (defined('PRINTAURA_WC_JSON_API_DEBUG')) {
                Printaura_JSONAPIHelpers::truncateDebug();
            }
            $api = new Printaura_WooCommerce_JSON_API();
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
            Printaura_JSONAPIHelpers::debug("JSON API is not set to enabled.");
        }
    }
}
function woocommerce_meta_boxes_order_tracking()
{
    add_meta_box('woocommerce-order-tracking', __('Order Traking', 'order-traking'), 'woocommerce_order_tracking', 'shop_order', 'side', 'low');
}
function woocommerce_order_tracking($post)
{
    wp_nonce_field(basename(__FILE__), 'woocommerce_tracking_nonce');
    $data = get_post_meta($post->ID); ?>
    	<div class="totals_group">
		<ul class="totals">

			<li class="wide">
				<label for="_tracking_number"><?php echo 'Tracking Number:'; ?></label>
	<input type="text" id="_tracking_number" name="_tracking_number" value="<?php if (isset($data['_tracking_number'])) {
        echo esc_attr($data['_tracking_number'][0]);
    } ?>" class="first" />
			</li>

			<li class="wide">
				<label for="_tracking_url"><?php echo 'Shipping Company Url:'; ?></label>
				<input type="text" id="_shipping_url" name="_tracking_url" value="<?php if (isset($data['_tracking_url'])) {
        echo esc_attr($data['_tracking_url'][0]);
    } ?>" class="first">
			</li>

		</ul>
		<div class="clear"></div>
	</div>
<?php
}
function order_traking_save($post_id)
{
    $is_autosave=wp_is_post_autosave($post_id);
    $is_revision=wp_is_post_revision($post_id);
    $is_valid_nonce=(isset($_POST['woocommerce_tracking_nonce']) && wp_verify_nonce($_POST['woocommerce_tracking_nonce'], basename(__FILE__)))?'true':'false';
    if ($is_autosave||$is_revision||!$is_valid_nonce) {
        return;
    }
    if (isset($_POST['_tracking_number'])) {
        update_post_meta($post_id, '_tracking_number', sanitize_text_field($_POST['_tracking_number']));
    }
    if (isset($_POST['_tracking_url'])) {
        update_post_meta($post_id, '_tracking_url', sanitize_text_field($_POST['_tracking_url']));
    }
}
function printaura_api_admin_menu()
{
    global $menu;
    /*$json_api_page = add_submenu_page(
                                        'woocommerce',
                                        __( 'JSON API', 'woocommerce' ),
                                        __( 'JSON API', 'woocommerce' ) ,
                                        'manage_woocommerce',
                                        'api_settings_page',
                                        'printaura_api_settings_page'
    );*/
    add_menu_page(
      __('Print Aura Woocommerce API', 'woocommerce'),
          __('Print Aura API', 'woocommerce'),
          'manage_woocommerce',
          'api_settings_page',
      'printaura_api_settings_page'
  );
}
function printaura_api_settings_page()
{
    $helpers = new Printaura_JSONAPIHelpers();
    $current_user=wp_get_current_user();
    $key5 = $helpers->getPluginPrefix() . '_api_enabled';
    $key3 = $helpers->getPluginPrefix() . '_api_token';
    $key4 = $helpers->getPluginPrefix() . '_ips_allowed';
    $params = $_POST;
    //var_dump($params);
    $nonce = $helpers->orEq($params, '_wpnonce', false);
    $key = $helpers->getPluginPrefix() . '_sitewide_settings';
    if ($nonce  && wp_verify_nonce($nonce, $helpers->getPluginPrefix() . '_sitewide_settings') && isset($params[$key])) {
        foreach ($params[$key] as $key2=>$value) {
            update_option($helpers->getPluginPrefix() . '_' . $key2, maybe_serialize($value));
        }
        $sanitized_token = preg_replace( '/[^a-zA-Z0-9_]/', '', $_POST[$key]['token'] );
        update_user_meta($current_user->ID, $key3, $sanitized_token);
        update_user_meta($current_user->ID, $key4, sanitize_text_field($_POST[$key]['ips_allowed']));
        update_user_meta($current_user->ID, $key5, sanitize_text_field($_POST[$key]['enabled']));
    }
    
    $attrs = array(
      'json_api_sitewide_settings' => array(
          'title' => __('Print Aura Woocommerce API Settings', 'printaura_api'),
          'fields' => array(
          array(
            'name'          => $helpers->getPluginPrefix() . '_sitewide_settings[enabled]',
            'id'            => 'json_api_enabled_id',
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
            'value'         => get_user_meta($current_user->ID, $key3, true),
            'type'          => 'text',
            'label'         => __('API Token', 'printaura_api'),
            'description'   => __('A large string of letters and numbers, mixed case, that will be used to authenticate requests', 'printaura_api')
          ),
          array(
            'name'          => $helpers->getPluginPrefix() . '_sitewide_settings[ips_allowed]',
            'id'            => 'json_api_ips_allowed_id',
            'value'         => get_user_meta($current_user->ID, $key4, true),
            'type'          => 'textarea',
            'label'         => __('IPs Allowed', 'printaura_api'),
            'description'   => __('What ips are permitted to connect with this user...', 'printaura_api')
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
