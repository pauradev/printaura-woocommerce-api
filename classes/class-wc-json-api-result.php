<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class Printaura_WooCommerce_JSON_API_Result {
  public $params;
  public function printaura_status() {
    return $this->params['status'];
  }
  public function printaura_setStatus($bool) {
    $this->params['status'] = $bool;
  }
  public function printaura_setParams( $params ) {
    global $wpdb;
    if ( isset($params['arguments']['password']) ) {
      // We shouldn't pass back the password.
      //$params['arguments']['password'] = '[FILTERED]';
    }
    unset($params['uploads']); // we don't need this in the result
    //$this->params = $params;
    $this->params['status'] = true;
    $this->params['errors'] = array();
    $this->params['warnings'] = array();
    $this->params['notifications'] = array();
    $this->params['payload'] = array();
    //$this->params['arguments']['token'] = "";
    // Now we need to generate some stats for the call,
    // like how many products, order, categories there are
    // and so on.
    /*$this->params['statistics'] = array(
      'products' => $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status != 'trash'"),
      'products_in_trash' => $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'trash'"),
      'variations' => $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_status != 'trash'"),
      'orders' => $wpdb->get_var("SELECT count(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status != 'trash'"),
    );*/
    return $this;
  }
  public function printaura_getParams() {
    return $this->params;
  }
  public function printaura_setPayload( $collection ) {
    
      $this->params['payload'] = $collection;
    return $this;
  }
  public function printaura_setToken( $token ){
    //$this->params['arguments']['token'] = $token;
  }
  
  /**
  * This is useful when we are looping and grabbing bits, but don't
  * want to create our own array
  */
  public function printaura_addPayload( $hash ) {
    $this->params['payload'][] = $hash;
  }
  /*public function printaura_addPayload( $text, $merge = array()) {
    $payload = array( 'text' => $text);
    foreach ($merge as $k=>$v) {
      $payload[$k] = $v;
    }
    $this->params['payload'][] = $payload;
  }*/
  public function printaura_asJSON() {
    //$this->params['payload_length'] = count($this->params['payload']);
    if (PHP_MINOR_VERSION < 4) {
      //JSONAPIHelpers::warn("PHP 5.4 and above recommended for the API.");
      $text = json_encode($this->params);
            $text = json_encode($this->params);
    } else {
      $text = json_encode($this->params, JSON_PRETTY_PRINT);
    }
    $jsonp = false;
    if ( isset($this->params['callback']) ) {
      $jsonp = $this->params['callback'];
    }
    if ( isset($this->params['jsonp']) ) {
      $jsonp = $this->params['jsonp'];
    }
    if ( $jsonp ) {
      return "{$jsonp}({$text});";
    } else {
      return $text;
    }
  }
  public function printaura_addError( $text, $code, $merge = array() ) {
    $this->params['status'] = false;
    $error = array( 'text' => $text, 'code' => $code);
    foreach ($merge as $k=>$v) {
      $error[$k] = $v;
    }
    $this->params['errors'][] = $error;
    
  }
  public function printaura_addWarning( $text, $code , $merge = array()) {
    $warn = array( 'text' => $text, 'code' => $code);
    foreach ($merge as $k=>$v) {
      $warn[$k] = $v;
    }
    $this->params['warnings'][] = $warn;
  }
  public function printaura_addNotification( $text, $merge = array()) {
    $notice = array( 'text' => $text);
    foreach ($merge as $k=>$v) {
      $notice[$k] = $v;
    }
    $this->params['notifications'][] = $notice;
  }
  
}
