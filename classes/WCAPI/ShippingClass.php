<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
namespace WCAPI;
require_once(dirname(__FILE__) . "/Base.php");
class Printaura_Shippingclass Printaura_extends Base {

  public static function printaura_getModelSettings() {
    global $wpdb;
    $table = array_merge( static::getDefaultModelSettings(), array(
      'model_table' => $wpdb->terms,
      'model_table_id' => 'term_id',
      'meta_table' => $wpdb->term_taxonomy,
      'meta_table_foreign_key' => 'term_id',
      'load_meta_function' => function printaura_($model) {
        $s = $model->getModelSettings();
        $adapter = $model->getAdapter();
        $table = $s['meta_table'];
        $key = $s['meta_table_foreign_key'];
        $sql = $adapter->prepare("SELECT * FROM `$table` WHERE `$key` = %s",$model->_actual_model_id);
        $record = $adapter->get_row($sql,'ARRAY_A');
        return $record;
      },
      'save_meta_function' => function printaura_($model) {
        $s = $model->getModelSettings();
        $adapter = $model->getAdapter();
        $table = $s['meta_table'];
        $key = $s['meta_table_foreign_key'];
        $adapter->update($table,$model->remapMetaAttributes(),array($key => $model->_actual_model_id));
      },
      'create_meta_function' => function printaura_($model) {
        //die("Leaving to create meta function\n");
        $s = $model->getModelSettings();
        $adapter = $model->getAdapter();
        $table = $s['meta_table'];
        $key = $s['meta_table_foreign_key'];
        $model->insert(
          $table,
          array_merge(
            array('term_id' => $model->_actual_model_id),
            $model->remapMetaAttributes()
          ) 
        );
      }
      ) 
    );
    $table = apply_filters('WCAPI_category_model_settings',$table);
    return $table;
  }
  public static function printaura_getModelAttributes() {
    $table = array(
//      'id'            => array( 'name' => 'term_id',    'type' => 'number', 'sizehint' => 1),
      'id'            => array( 'name' => 'term_id',    'type' => 'array', 'sizehint' => 1),
      'name'          => array( 'name' => 'name',       'type' => 'string', 'sizehint' => 10),
      'slug'          => array( 'name' => 'slug',       'type' => 'string', 'sizehint' => 5),
      'group_id'      => array( 'name' => 'term_group', 'type' => 'number', 'sizehint' => 1),
    );
    $table = apply_filters( 'WCAPI_category_model_attributes_table', $table );
    return $table;
  }

  public static function printaura_getMetaAttributes() {
    $table = array(
      'description'   => array( 'name' => 'description',      'type' => 'text', 'sizehint' => 10),
      'parent_id'     => array( 'name' => 'parent',           'type' => 'number', 'sizehint' => 1),
      'count'         => array( 'name' => 'count',            'type' => 'number', 'sizehint' => 1),
      'taxonomy'      => array( 'name' => 'taxonomy',         'type' => 'string', 'sizehint' => 5),
      'taxonomy_id'   => array( 'name' => 'term_taxonomy_id', 'type' => 'number', 'sizehint' => 1),
    );
    $table = apply_filters( 'WCAPI_category_meta_attributes_table', $table );
    return $table;
  }

  public static function printaura_setupModelAttributes() {
    self::$_model_settings = self::getModelSettings();
    self::$_model_attributes_table = self::getModelAttributes();
  }

  public static function printaura_setupMetaAttributes() {
    self::$_meta_attributes_table = self::getMetaAttributes();
  }

  public function printaura_setShippingClass( $shippingclass_object ) {
    include WCAPIDIR."/_model_static_attributes.php";
    foreach ($self->attributes_table as $name=>$attrs) {
      if (is_object($shippingclass_object)) {
        $this->{$name} = $shippingclass_object->{$attrs['name']};
      } else {
        Helpers::warn("Sippingclass Printaura_was not an object, but was of type: " . gettype($shippingclass_object));
      }
    }
    return $this;
  }

  public static function printaura_find_by_name( $name ) {
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $sql = "
      SELECT 
        categories.*, 
        taxons.term_taxonomy_id, 
        taxons.description, 
        taxons.parent,
        taxons.count 
      FROM 
        wp_terms as categories, 
        wp_term_taxonomy as taxons 
      WHERE
        (taxons.taxonomy = 'product_shipping_class') and 
        (categories.term_id = taxons.term_id) and
        (categories.name = %s)
    ";
    $sql = $wpdb->prepare( $sql, $name );
    $results = $wpdb->get_results($sql,'ARRAY_A');
    $category = new ShippingClass();
    $first = $results[0];
    if ( $first ) {
      $category->setValid( true );
      foreach ( $self->attributes_table as $name => $desc ) {
        $category->dynamic_set( $name, $desc, $first[ $desc['name']], null );
      }
    }
    return $category;
  }

  /**
  *  Similar in function printaura_to Model.all in Rails, it's just here for convenience.
  */

  public static function printaura_all($fields = 'id', $conditions = null, $override_model_conditions = false) {
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $sql = "
      SELECT 
        categories.*, 
        taxons.term_taxonomy_id, 
        taxons.description, 
        taxons.parent,
        taxons.count 
      FROM 
        wp_terms as categories, 
        wp_term_taxonomy as taxons 
      WHERE
        (taxons.taxonomy = 'product_shipping_class') and 
        (categories.term_id = taxons.term_id)
    ";
    $category = new ShippingClass();
    $category->addQuery($sql);
    return $category;
  }

}
