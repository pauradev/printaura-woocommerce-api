<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once( plugin_dir_path(__FILE__) . '/../class-rede-helpers.php' );
class Printaura_JSONAPI_Ids_Argument_Validator extends JSONAPIHelpers {
  public $result;
  public function printaura_validate( $source, &$value, &$result ) {
    $this->result = $result;
    if ( !is_array($value) ) {
      $this->badArgument('ids',__('an array is','woocommerce_json_api') );
      return;
    }
    foreach ( $value as $v ) {
      if ( !is_numeric($v) ) {
        $this->badArgument('ids',__('an array of numbers is','woocommerce_json_api') );
        return;
      }
    }
  }
}