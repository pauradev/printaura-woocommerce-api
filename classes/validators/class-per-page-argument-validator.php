<?php
require_once( plugin_dir_path(__FILE__) . '/../class-rede-helpers.php' );
class JSONAPI_PerPage_Argument_Validator extends JSONAPIHelpers {
  public $result;
  public function validate( $source, &$value, &$result ) {
    $this->result = $result;
    if ( !is_numeric($value) ) {
      $this->badArgument('per_page',__('numbers','woocommerce_json_api') );
    }
  }
}