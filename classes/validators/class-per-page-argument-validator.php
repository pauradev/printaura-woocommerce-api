<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
require_once( plugin_dir_path(__FILE__) . '/../class-rede-helpers.php' );
class Printaura_JSONAPI_PerPage_Argument_Validator extends JSONAPIHelpers {
  public $result;
  public function printaura_validate( $source, &$value, &$result ) {
    $this->result = $result;
    if ( !is_numeric($value) ) {
      $this->badArgument('per_page',__('numbers','woocommerce_json_api') );
    }
  }
}