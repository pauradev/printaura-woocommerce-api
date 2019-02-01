<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined('EVERYTHING') ) {
  define('EVERYTHING',true);
}
class Printaura_Mapper {
  public $connection = null;
  public $errors;
  public $details;
  public $table_prfix;
  public $self;
  public function printaura_construct( $active_connection ) {
    $this->connection = $active_connection;
    $this->reset(EVERYTHING);
  }

  public function printaura_reset($kind_of = null) {
    $this->errors = array();
    $this->details = array();
    if ( $kind_of == EVERYTHING ) {
      $this->table_prefix = '';
      $this->setSelf($this);
    }
  }
  public function printaura_setSelf(&$s) {
    $this->self = $s;
  }

  public function printaura_create( $resource, $attributes_map ) {

  }
  public function printaura_read( $resource, $attributes_map ) {

  }
  public function printaura_update( $resource, $attributes_map ) {

  }
  public function printaura_delete( $resource, $attributes_map ) {

  }
  public function printaura_join( $resource1, $resource2 ) {

  }
}