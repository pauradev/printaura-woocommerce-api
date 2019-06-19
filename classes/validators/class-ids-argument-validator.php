<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once( plugin_dir_path(__FILE__) . '/../class-rede-helpers.php' );
class Printaura_JSONAPI_Ids_Argument_Validator extends Printaura_JSONAPIHelpers {
  public $result;
  public function validate( $source, &$value, &$result ) {
    $this->result = $result;
    if ( !is_array($value) ) {
      $this->badArgument('ids',__('an array is','Printaura_WooCommerce_JSON_API') );
      return;
    }
    foreach ( $value as $v ) {
      if ( !is_numeric($v) ) {
        $this->badArgument('ids',__('an array of numbers is','Printaura_WooCommerce_JSON_API') );
        return;
      }
    }
  }
}
