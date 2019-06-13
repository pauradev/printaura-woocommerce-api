<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( ! defined('PRINTAURA_EVERYTHING') ) {
  define('PRINTAURA_EVERYTHING',true);
}
class Mapper {
  public $connection = null;
  public $errors;
  public $details;
  public $table_prfix;
  public $self;
  public function __construct( $active_connection ) {
    $this->connection = $active_connection;
    $this->reset(PRINTAURA_EVERYTHING);
  }

  public function reset($kind_of = null) {
    $this->errors = array();
    $this->details = array();
    if ( $kind_of == PRINTAURA_EVERYTHING ) {
      $this->table_prefix = '';
      $this->setSelf($this);
    }
  }
  public function setSelf(&$s) {
    $this->self = $s;
  }

  public function create( $resource, $attributes_map ) {

  }
  public function read( $resource, $attributes_map ) {

  }
  public function update( $resource, $attributes_map ) {

  }
  public function delete( $resource, $attributes_map ) {

  }
  public function join( $resource1, $resource2 ) {

  }
}
