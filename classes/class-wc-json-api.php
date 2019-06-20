<?php
if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
/**
 * Core JSON API
*/
// Error Codes are negative, Warning codes are positive
define('PRINTAURA_JSONAPI_EXPECTED_ARGUMENT', -1);
define('PRINTAURA_JSONAPI_NOT_IMPLEMENTED', -2);
define('PRINTAURA_JSONAPI_UNEXPECTED_ERROR', -3);
define('PRINTAURA_JSONAPI_INVALID_CREDENTIALS', -4);
define('PRINTAURA_JSONAPI_CANNOT_INSERT_RECORD', -6);
define('PRINTAURA_JSONAPI_BAD_ARGUMENT', -5);
define('PRINTAURA_JSONAPI_PERMSNOTSET', -7);
define('PRINTAURA_JSONAPI_PERMSINSUFF', -8);
define('PRINTAURA_JSONAPI_INTERNAL_ERROR', -9);

define('PRINTAURA_JSONAPI_PRODUCT_NOT_EXISTS', 1);
define('PRINTAURA_JSONAPI_ORDER_NOT_EXISTS', 2);
define('PRINTAURA_JSONAPI_NO_RESULTS_POSSIBLE', 3);
define('PRINTAURA_JSONAPI_MODEL_NOT_EXISTS', 1);

require_once(plugin_dir_path(__FILE__) . '/class-rede-helpers.php');
require_once(plugin_dir_path(__FILE__) . '/class-wc-json-api-result.php');
require_once(dirname(__FILE__) . '/WCAPI/includes.php');

use WCAPI as API;

if (!defined('PHP_VERSION_ID')) {
    $version = explode('.', PHP_VERSION);
    if (PHP_VERSION_ID < 50207) {
        define('PRINTAURA_PHP_MAJOR_VERSION', $version[0]);
        define('PRINTAURA_PHP_MINOR_VERSION', $version[1]);
        define('PRINTAURA_PHP_RELEASE_VERSION', $version[2]);
    }
}
class Printaura_WooCommerce_JSON_API extends Printaura_JSONAPIHelpers
{
    // Call this function to setup a new response
    public $helpers;
    public $result;
    public $return_type;
    public $the_user;
    public $provider;
    public static $implemented_methods;

    public function setOut($t)
    {
        $this->return_type = $t;
    }
    public function setUser($user)
    {
        $this->the_user = $user;
    }
    public function getUser()
    {
        return $this->the_user;
    }
    public static function getImplementedMethods()
    {
        self::$implemented_methods = array(
      'get_system_time',
      'get_supported_attributes',
      'get_products',
      'get_categories',
      'get_taxes',
      'get_shipping_methods',
      'get_payment_gateways',
      'get_tags',
      'get_shipping_class',
      'get_products_by_tags',
      'get_customers',
      'get_orders',
      'get_orders_from_trash',
      'get_products_from_trash',
      'get_store_settings',
      'get_site_settings',
      'get_api_methods',
      'get_coupons',
      'get_images',

      // Write capable methods

      'set_products',
      'delete_products',
      'delete_images',
      'set_categories',
      'set_orders',
      'updateTrackingOrder',
      'set_store_settings',
      'set_site_settings',
      'set_coupons',
      'update_orderitem_tracking',
      'add_product_image',

    );
        return self::$implemented_methods;
    }
    public function __construct()
    {
        //$this = new Printaura_JSONAPIHelpers();
        $this->result = null;
        $this->provider = null;
        parent::init();
    }

    public function route($params)
    {
        global $wpdb;
        $method = $this->orEq($params, 'method', false);
        $proc = $this->orEq($params, 'proc', false);
        if (
          $method &&
          $proc  &&
           strpos($proc, 'get_')!==0&&
           strpos($proc, 'set_')!==0&&
           strpos($proc, 'delete_')!==0
       ) {
            switch (strtolower($method)) {
        case 'get':
          $proc = 'get_'.$proc;
          break;
        case 'put':
          $proc = 'set_'.$proc;
          break;
        case 'delete':
          $proc = 'delete_'.$proc;
          break;
      }
        }

        /*
         * The idea behind the provider is that there will be
         * several versions of the API in the future, and the
         * user can choose which one they are writing against.
         * This simplifies the provider files a bit and makes
         * the code more modular.
         */
        $version = intval($this->orEq($params, 'version', 1));
        if (! is_numeric($version)) {
            $version = 1;
        }
        if (file_exists(dirname(__FILE__) .'/API_VERSIONS/version'.$version.'.php')) {
            require_once(dirname(__FILE__) .'/API_VERSIONS/version'.$version.'.php');
            $class = "Printaura_WC_JSON_API_Provider_v{$version}";
            $class = str_replace('.', '_', $class);
            $this->provider = new $class($this);
        }

        // Reorganize any uploaded files and put them in
        // the params
        $files = array();


        $params['uploads'] = $files;

        $this->createNewResult($params);

        // Now we need to allow for people to add dynamic
        // filters to the models.
        if (isset($params['model_filters'])) {
            foreach ($params['model_filters'] as $filter_text=>$filter) {
                foreach ($filter as $key=>&$value) {
                    $value['name'] = substr($wpdb->prepare("%s", $value['name']), 1, strlen($value['name']));
                }
                $callback = function ($table) use ($filter) {
                    return array_merge($table, $filter);
                };
                add_filter($filter_text, $callback);
            }
        }
        $type=$this->return_type;
        Printaura_JSONAPIHelpers::debug("Beggining request");
        Printaura_JSONAPIHelpers::debug(var_export($params, true));

        if (! $this->isValidAPIUser($params)) {
            Printaura_JSONAPIHelpers::debug("Not a valid user");
            $this->result->addError(
        __('Not a valid API User ', 'printaura_api'),
        PRINTAURA_JSONAPI_INVALID_CREDENTIALS
      );
            return $this->done();
        }
        if (isset($params['proc'])) {
            if ($this->provider->isImplemented($proc)) {
                try {
                    // The arguments are passed by reference here
                    $this->validateParameters($params['arguments'], $this->result);

                    if ($this->result->status() == false) {
                        Printaura_JSONAPIHelpers::warn("Arguments did not pass validation");
                        $this->result->addError(
                        __('Not a valid API User Status', 'printaura_api'),
                        PRINTAURA_JSONAPI_INVALID_CREDENTIALS
                        );
                        return $this->done();
                    } else {
                        Printaura_JSONAPIHelpers::debug("Arguments have passed validation");
                    }
                    return $this->provider->{ $proc }($params);
                } catch (Exception $e) {
                    Printaura_JSONAPIHelpers::error($e->getMessage());
                    $this->unexpectedError($params, $e);
                }
            } else {
                Printaura_JSONAPIHelpers::warn("{$proc} is not implemented...");
                $this->notImplemented($params, $proc);
            }
        } else {
            $this->result->addError(
          __('Expected argument was not present', 'printaura_api') . ' `proc`',
           PRINTAURA_JSONAPI_EXPECTED_ARGUMENT
      );
            return $this->done();
        }
    }
    public function isValidAPIUser($params)
    {
        /*if(!current_user_can('manage_options')){
             Printaura_JSONAPIHelpers::debug( "no permission" );
            return false;
        } */
        if ($this->the_user) {
            return true;
        }
        if (! isset($params['arguments'])) {
            $this->result->addError(__('Missing `arguments` Data', 'printaura_api'), PRINTAURA_JSONAPI_EXPECTED_ARGUMENT);
            return false;
        }
        $by_token = true;
        if (! isset($params['arguments']['token'])) {
            return false;
        }
        Printaura_JSONAPIHelpers::debug("starting processing user ");

        API\Base::setBlogId($GLOBALS['blog_id']);
        $key = $this->getPluginPrefix() . '_token';
        $key1 = $this->getPluginPrefix() . '_enabled';

        if (! $by_token) {
            return false;
        }
        Printaura_JSONAPIHelpers::debug("Authentication by Token");
        $args = array(
      'blog_id' => $GLOBALS['blog_id'],
      'meta_key' => $key,

    );
        $users = get_users($args);
        Printaura_JSONAPIHelpers::debug("User info".var_export($users, true));
        foreach ($users as $user) {
            $api_token = maybe_unserialize(get_user_meta($user->ID, $key, true));
            $api_enabled = maybe_unserialize(get_user_meta($user->ID, $key1, true));

            if (isset($api_token) &&  $params['arguments']['token'] == $api_token) {
                if ($api_enabled == 'no') {
                    $this->result->addError(__('You have been banned.', 'printaura_api'), PRINTAURA_JSONAPI_PERMSINSUFF);

                    return false;
                }

                $this->logUserIn($user);
                $this->result->setToken($api_token);
                return true;
            }
        }

        return false;
    }
    public function logUserIn($user)
    {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, false, is_ssl());
        $this->setUser($user);
    }
    public function unexpectedError($params, $error)
    {
        $this->createNewResult($params);
        $trace = $error->getTrace();
        foreach ($trace as &$t) {
            if (isset($t['file'])) {
                $t['file'] = basename($t['file']);
            }
            if (!isset($t['class'])) {
                $t['class'] = 'GlobalScope';
            }
            if (!isset($t['file'])) {
                $t['file'] = 'Unknown';
            }
            if (!isset($t['line'])) {
                $t['line'] = 'GlobalScope';
            }

            $t = "{$t['file']}:{$t['line']}:{$t['class']}";
        }
        $this->result->addError(
      sprintf(__('An unexpected error has occured %s ', 'printaura_api'), $error->getMessage()),
      PRINTAURA_JSONAPI_UNEXPECTED_ERROR,
      array('trace' => $trace)
    );
        return $this->done();
    }
    public function createNewResult($params)
    {
        if (! $this->result) {
            $this->result = new Printaura_WooCommerce_JSON_API_Result();
            $this->result->setParams($params);
        }
    }
    public function done()
    {
        Printaura_JSONAPIHelpers::debug("Printaura_WooCommerce_JSON_API::done() called..");
        wp_logout();
        if ($this->return_type == 'HTTP') {
            header("Content-type: application/json");
            echo($this->result->asJSON());
            die;
        } elseif ($this->return_type == "ARRAY") {
            return $this->result->getParams();
        } elseif ($this->return_type == "JSON") {
            return $this->result->asJSON();
        } elseif ($this->return_type == "OBJECT") {
            return $this->result;
        }
    }
    public function notImplemented($params, $method)
    {
        $this->createNewResult($params);
        $this->result->addError(
      __("That API method ({$method}) has not been implemented", 'printaura_api'),
      JSONAPI_NOT_IMPLEMENTED
    );
        return $this->done();
    }
}
