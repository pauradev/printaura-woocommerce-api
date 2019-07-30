<?php
if (! defined('ABSPATH')) {
    exit;
} // Exit if accessed directly
if (! function_exists('printaura_rede_notset')) {
    // don't use _ for function prefixes outside classes. They are reserved.
    function printaura_rede_notset($mixed)
    {
        if (defined('PRINTAURA_REDENOTSET')) {
            if ($mixed == PRINTAURA_REDENOTSET) {
                return true;
            } else {
                return false;
            }
        } else {
            throw new Exception(__('PRINTAURA_REDENOTSET is not defined!', 'rede_plugins'));
        }
    }
}
/**
* This class needs to be instantiated as helpers, and provides all the helper
* functionality needed by the PHP side of the API
*/
class Printaura_JSONAPIHelpers
{
    public $plugin_name = 'printaura-woocommerce-api';
    private $path;
    private $css;
    private $js;
    private $templates;
  
    private $wp_template;
    private $wp_theme_root;

    // Later on, these will be configurable and can be
    // turned off completely from the controls in the UI.
    public static function warn($text)
    {
        if (! defined('PRINTAURA_WC_JSON_API_DEBUG')) {
            return;
        }
        $file = PRINTAURA_REDE_PLUGIN_BASE_PATH . "warnings.log";
        $fp = @fopen($file, 'a');
        if ($fp) {
            fwrite($fp, $text . "\n");
            self::debug("[Warn] " . $text);
            fclose($fp);
        }
    }
    public static function error($text)
    {
        $fp = @fopen(PRINTAURA_REDE_PLUGIN_BASE_PATH . "errors.log", 'a');
        if ($fp) {
            fwrite($fp, $text . "\n");
            self::debug("[Error] " . $text);
            fclose($fp);
        }
    }
    public static function truncateDebug()
    {
        if (! defined('PRINTAURA_WC_JSON_API_DEBUG')) {
            return;
        }
        $fp = @fopen(PRINTAURA_REDE_PLUGIN_BASE_PATH . "debug.log", 'w+');
        if ($fp) {
            fwrite($fp, "Truncated.");
            fclose($fp);
        }
    }
    public static function debug($text)
    {
        if (! defined('PRINTAURA_WC_JSON_API_DEBUG')) {
            return;
        }
        $fp = @fopen(PRINTAURA_REDE_PLUGIN_BASE_PATH . "debug.log", 'a');
        if ($fp) {
            fwrite($fp, $text . "\n");
            fclose($fp);
        }
    }
    public function __construct()
    {
        $this->init();
    }
    public function init()
    {
        // README
        // I wrote this file so that I could explore
        // the WP API a bit more and get an idea
        // about where stuff is, and what functions
        // are available
        // All paths should consistently end with /
        // This is not the case with all WP functions
        // as is the case with theme_root
    
        // All we are doing here is populating some helper variables
        // for use later
        $this->path             = PRINTAURA_REDE_PLUGIN_BASE_PATH; // Why do this? Maybe a plugin wants to override this later...
        $this->css              = PRINTAURA_REDE_PLUGIN_BASE_PATH . 'templates/css/';
        $this->js               = PRINTAURA_REDE_PLUGIN_BASE_PATH . 'assets/js/';
        $this->templates        = PRINTAURA_REDE_PLUGIN_BASE_PATH . 'templates/';
    
        $this->wp_template      = get_template(); //apparently sometimes this is null
        // Just calling get_theme_root doesn't seem to work...
        $this->wp_theme_root    = get_theme_root($this->wp_template);
        if (!empty($this->wp_template) && strpos($this->wp_theme_root, $this->wp_template) === false) {
            $test_path = $this->wp_theme_root . '/' . $this->wp_template;
            if (file_exists($test_path)) {
                $this->wp_theme_root = $test_path . "/";
            }
        } else {
            $this->wp_theme_root .= '/';
        }
    }
    public function missingArgument($name)
    {
        $this->result->addError(sprintf(__('Missing `%s` in `arguments`', 'Printaura_WooCommerce_JSON_API'), $name), PRINTAURA_JSONAPI_EXPECTED_ARGUMENT);
    }
    public function badArgument($name, $values='')
    {
        $this->result->addError(sprintf(__('The value of `%s` is not valid, only %s accepted.', 'Printaura_WooCommerce_JSON_API'), $name, $values), PRINTAUAR_JSONAPI_BAD_ARGUMENT);
    }
    // README
    // This function finds where a template is located in the system
    // and returns an absolute path, or throws an error when it
    // is not present on the system
    public function findTemplate($template_name)
    {
        $test_path = $this->wp_theme_root . 'templates/' . $template_name;
        if (file_exists($test_path)) {
            return $test_path;
        } else {
            $test_path = $this->path. 'templates/' . $template_name;
            if (file_exists($test_path)) {
                return $test_path;
            } else {
                throw new Exception(__('Core Template was not found: ') . ' ' . $template_name);
            }
        }
    }
    public function findClassFile($filename, $throw_error = false)
    {
        $test_path = $this->wp_theme_root . 'classes/' . $filename;
        if (file_exists($test_path)) {
            return $test_path;
        } else {
            Printaura_JSONAPIHelpers::debug("$test_path didn't exist");
            $test_path = $this->path . 'classes/' . $filename;
            if (file_exists($test_path)) {
                return $test_path;
            } else {
                Printaura_JSONAPIHelpers::debug("$test_path didn't exist");
                if ($throw_error) {
                    throw new Exception(__('Core Class File was not found: ') . ' ' . $filename);
                } else {
                    return false;
                }
            }
        }
    }
    /**
    * $vars_in_scope is an array like so: {'myvar' => 'some text'} which can
    * be accessed in the template withe $myvar
    *
    * @param string template path, relative to the plugin
    * @param array of key value pairs to put into scope
    * @return the rendered, filtered, executed content of the php template file
    */
    public function renderTemplate($template_name, $vars_in_scope = array())
    {
        global $woocommerce,$wpdb, $user_ID, $available_methods;
        $vars_in_scope['helpers'] = $this;
        $vars_in_scope['__VIEW__'] = $template_name; //could be user-files.php or somedir/user-files.php
                                                 
        // The filter will look like: woo_commerce_json_api_vars_in_scope_for_user_files if the
        // views name was user-files.php, if it was in a subdir, like dir/user-files.php it would be dir_user_files
        $vars_in_scope = apply_filters($this->getPluginPrefix() . '_vars_in_scope_for_' . basename(str_replace('/', '_', $template_name), ".php"), $vars_in_scope);
        foreach ($vars_in_scope as $name=>$value) {
            $$name = $value;
        }
        $template_path = $this->findTemplate($template_name);
        ob_start();
        try {
            include $template_path;
            $content = ob_get_contents();
            ob_end_clean();
            $content = apply_filters($this->getPluginPrefix() . '_template_rendered_' . basename(str_replace('/', '_', $template_name), ".php"), $content);
        } catch (Exception $err) {
            ob_end_clean();
            throw new Exception(__('Error while rendering template ' . $template_name . ' -- ' . $err->getMessage(), 'rede_plugins'));
        }
        return $content;
    }
    /**
    *  Return the plugin name.
    */
    public function getPluginName()
    {
        return $this->plugin_name;
    }
    /*
      Get the PluginPrefix, used for meta data keys to help avoid namespace collisions
      with other plugins.
    */
    public function getPluginPrefix()
    {
        return str_replace('-', '_', $this->plugin_name);
    }
    /*
      Does this plugin have a special text domain?
    */
    public function getPluginTextDomain()
    {
        return $this->getPluginName();
    }
    public static function getOrderStatuses()
    {
        $results = array();
        $statuses = (array) get_terms('shop_order_status', array( 'hide_empty' => 0, 'orderby' => 'id' ));
        foreach ($statuses as $status) {
            $results[] = $status->slug;
        }
        return $results;
    }
    /***************************************************************************/
    /*                    Checkers, validators                                 */
    /***************************************************************************/
    public function isHTTPS()
    {
        if (
      (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ||
      $_SERVER['port'] == 443
    ) {
            return true;
        } else {
            return true;
        }
    }
    /**
    * We want to avoid directly accessing Array keys, because
    * a) people have weird debug settings and
    * b) Some idiot thought it was a good idea to add in warnings when you access a null array key.
    *    Whoever that person is, they should be shot. Out of a cannon. Into the Sun.
    *
    * @param array to look in
    * @param string key
    * @param default value if not found (Default is i18n xlated to UnNamed
    */
    public function orEq($array, $key, $default = null, $valid_values_list = null)
    {
        if ($default === null) {
            $default = __('UnNamed', $this->getPluginName()) . ' - ' . $key;
        }
        if (isset($array[$key])) {
            $value = $array[$key];
        } else {
            $value = $default;
        }
        if ($valid_values_list) {
            foreach ($valid_values_list as $val) {
                if ($value == $val) {
                    return $value;
                }
            }
            Printaura_JSONAPIHelpers::warn("orEq was passed a valid_values_list, but inputs did not match, so returning default");
            return $default;
        } else {
            return $value;
        }
    }
    /**
    * PHP's array_search is clumsy and not helpful with simple searching where all we want
    * is a true or false value. It's just easier to do it our own way.
    */
    public function inArray($needle, $haystack)
    {
        foreach ($haystack as $value) {
            if ($needle === $value) {
                return true;
            }
        }
        return false;
    }
    /**
    * We pass in the params, usually $params['arguments'] by reference, as well as
    * a reference to the result object so that we can invalidate and add errors to it.
    */
    public function validateParameters(&$params, &$target)
    {
        $params = apply_filters('rede_pre_validate_parameters', $params, $target);
        foreach ($params as $key=>&$value) {
            $tmp_key = str_replace('_', '-', $key);
            $fname = "validators/class-{$tmp_key}-argument-validator.php";
            $tmp_key =  str_replace('-', ' ', $tmp_key);
            $tmp_key = ucwords($tmp_key);
            $tmp_key = str_replace(" ", '', $tmp_key);
            $class_name = "JSONAPI_{$tmp_key}_Argument_Validator";
            Printaura_JSONAPIHelpers::debug("validator class name to load is {$class_name}");
            Printaura_JSONAPIHelpers::debug("path to validator should be {$fname}");
            $path = $this->findClassFile($fname, false);
            if ($path) {
                require_once $path;
                if (class_exists($class_name)) {
                    $validator = new $class_name();
                    $validator->validate($this, $value, $target);
                } else {
                    Printaura_JSONAPIHelpers::debug("validator class {$class_name} does not exist?");
                }
            } else {
                Printaura_JSONAPIHelpers::debug("validator {$fname} does not exist");
            }
        }
        $params = apply_filters('rede_post_validate_parameters', $params, $target);
    }
    /***************************************************************************/
    /*                         HTML API Helpers                                */
    /***************************************************************************/
    public function labelTag($args)
    {
        $name = $this->orEq($args, 'name');
        $content = $this->orEq($args, 'label');
        $classes = $this->orEq($args, 'classes', '');
        return "<label for='" . esc_attr($name) . "' for='" . esc_attr($classes) . "'>" . esc_html($content) . "</label>";
    }
    public function inputTag($args)
    {
        $name = $this->orEq($args, 'name');
        $value = $this->orEq($args, 'value', '');
        $id = $this->orEq($args, 'id', '');
        $cls=$this->orEq($args, 'class', '');
        $class=($cls!='')?'class="'.$cls.'"':'';
        return "<input type='text' id='" . esc_attr($id) . "' name='" . esc_attr($name) . "' {$class} value='" . esc_html($value) . "' />";
    }
    public function checkboxTag($args)
    {
        $name = $this->orEq($args, 'name');
        $value = $this->orEq($args, 'value', '');
        $id = $this->orEq($args, 'id', '');
        return "<input type='text' id='" . esc_attr($id) . "' name='" . esc_attr($name) . "' value='" . esc_html($value) . "' />";
    }
    public function textAreaTag($args)
    {
        $name = $this->orEq($args, 'name');
        $value = $this->orEq($args, 'value', '');
        $id = $this->orEq($args, 'id', '');
        $rows = $this->orEq($args, 'rows', 3);
        $cls=$this->orEq($args, 'class', '');
        $class=($cls!='')?'class="'.$cls.'"':'';
        return "<textarea id='" . esc_attr($id) . "' name='" . esc_attr($name) . "' {$class} rows='" . esc_attr($rows) . "'>" . esc_html($value) . "</textarea>";
    }
    public function selectTag($args)
    {
        $name = $this->orEq($args, 'name');
        $value = $this->orEq($args, 'value', '');
        $id = $this->orEq($args, 'id', '');
        $options = $this->orEq($args, 'options', array());
        $content = "<select name='$name' id='$id'>\n";
        foreach ($options as $option) {
            $opt = "<option value='%s' %s> %s </option>";
            $selected = '';
            if ($option['value'] == $value) {
                $selected = " selected='selected'";
            }
            $opt = sprintf($opt, $option['value'], $selected, $option['content']);
            $content .= $opt;
        }
        $content .= "</select>\n";
        return $content;
    }
    public function hiddenFormFields($action)
    {
        $output = wp_nonce_field($action, '_wpnonce', true, false);
        return $output;
    }
  
    /***************************************************************************/
    /*                       WordPress API Helpers                             */
    /***************************************************************************/
  
    /**
    *  Convert a title into a slug
    */
    public function createSlug($text)
    {
        $text = sanitize_title($text);
        return $text;
    }
    /**
    *  We want to ease the creation of pages
    *
    *  @param $title - The title you want to use, will be converted to the slug
    *  @param $content - the contents of the page
    *  @param $publish - boolean
    *  @return Array of populated values to send to insert_post
    */
    public function newPage($title, $content, $publish = true)
    {
        $page = array(
            'post_status'         => $publish === true ? 'publish' : 'pending',
            'post_type'         => 'page',
            'post_author'         => 1,
            'post_name'         => $this->createSlug($title),
            'post_title'         => $title,
            'post_content'         => $content,
            'post_parent'         => 0,
            'comment_status'     => 'closed'
        );
        return $page;
    }
  
    /*

    */
    public function getTitleBySlug($slug, $default = '')
    {
        $page = get_page_by_path($slug);
        $title = get_the_title($page->ID);
        if (empty($title)) {
            $title = $default;
        }
        return $title;
    }
    public function getPermalinkBySlug($slug)
    {
        $page = get_page_by_path($slug);
        return get_permalink($page);
    }
    public function getCountrie($key)
    {
        $arr= array(
            'AF' => 'Afghanistan',
            'AX' => '&#197;land Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua and Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'PW' => 'Belau',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BQ' => 'Bonaire, Saint Eustatius and Saba',
            'BA' => 'Bosnia and Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'VG' => 'British Virgin Islands',
            'BN' => 'Brunei',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo (Brazzaville)',
            'CD' => 'Congo (Kinshasa)',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CW' => 'Cura&Ccedil;ao',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island and McDonald Islands',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran',
            'IQ' => 'Iraq',
            'IE' => 'Republic of Ireland',
            'IM' => 'Isle of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'CI' => 'Ivory Coast',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Laos',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao S.A.R., China',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'KP' => 'North Korea',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PS' => 'Palestinian Territory',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russia',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barth&eacute;lemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts and Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin (French part)',
            'SX' => 'Saint Martin (Dutch part)',
            'PM' => 'Saint Pierre and Miquelon',
            'VC' => 'Saint Vincent and the Grenadines',
            'SM' => 'San Marino',
            'ST' => 'S&atilde;o Tom&eacute; and Pr&iacute;ncipe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia/Sandwich Islands',
            'KR' => 'South Korea',
            'SS' => 'South Sudan',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard and Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syria',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad and Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks and Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VA' => 'Vatican',
            'VE' => 'Venezuela',
            'VN' => 'Vietnam',
            'WF' => 'Wallis and Futuna',
            'EH' => 'Western Sahara',
            'WS' => 'Western Samoa',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe'
        );
        return $arr[$key];
    }
}
