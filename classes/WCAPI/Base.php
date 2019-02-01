<?php
namespace WCAPI;
require_once dirname( __FILE__ ) . '/BaseHelpers.php';
if (!defined('WCAPIDIR')) {
  define('WCAPIDIR', dirname(__FILE__) );
}
if (!defined('EVERYTHING_IM_SURE')) {
  define('EVERYTHING_IM_SURE', true );
}
if (!defined('THIS_IM_SURE')) {
  define('THIS_IM_SURE', true );
}
class Base extends Helpers {
  // We want to be able to update the product in one go, as quickly
  // as possible because it is not unrealistic for us to want to
  // update hundres of products in one API call. We don't want to
  // impose an arbitrary limit, instead leaving that up to the
  // host user and as a configuration variable
  public $_queries_to_run;
  public $_actual_model_id;
  // We need to know if this record exists in the database?
  // if not, then update should fail.
  public $_new_record;
  public $_valid;
  public $_page;
  public $_per_page;
  public $td = 'WCAPI';

  public $_result; // so we can add errors
  public $__order;
  
  // This is actually unecessary and is being 
  // moved to $actuals, eventually this will
  // be a static function that returns an array.
  public static $_meta_attributes_table; 
  public static $_model_attributes_table;
  
  public $_meta_attributes;
  public $_model_attributes;

  public $actual_meta_attributes_table;
  public $actual_model_attributes_table;
  public $actual_model_settings;

  public static $_model_settings;
  public static $adapter;
  public $mapper;
  public static $blog_id;

  public $__delete__ = false;
  public $__delete__everything = false;
  public $__disconnect__ = false;
  public $__disconnected__ = false;


  /**
  * We want to establish a "fluid" API for the objects.
  * which is why most of these methods return $this.
  * 
  * ( new Object() )->setup()->doCalculation()->update()->done);
  */
  
  public function __construct($mapper=null) {
    parent::init();
    static::setupMetaAttributes();
    static::setupModelAttributes();
    $this->actual_model_attributes_table = static::$_model_attributes_table;
    $this->actual_meta_attributes_table = static::$_meta_attributes_table;
    $this->actual_model_settings = static::$_model_settings;
    $this->mapper = $mapper;
    Helpers::debug(get_called_class() ."::__construct");
  }
  public static function setAdapter( &$a ) {
    static::$adapter = $a;
  }
  public static function setMapper( &$a ) {
    static::$mapper = $a;
  }
  public static function setBlogId( $id ) {
    static::$blog_id = $id;
  }
  public static function getModelSettings() {
    // This is kind of important, late static binding
    // can be really wonky sometimes. especially
    // with values that exist on the base model.
    static::setupModelAttributes();
    static::setupMetaAttributes();
    Helpers::debug(get_called_class() . '::getModelSettings');
    return static::$_model_settings;
  }
  public static function getDefaultModelSettings() {
   global $wpdb;
    // Here we have all the default settings
    // for a model.
    Helpers::debug(get_called_class() . "::getDefaultModelSettings");
    return array(
      'model_table'               => $wpdb->posts,
      'meta_table'                => $wpdb->postmeta,
      'model_table_id'            => 'id',
      'meta_table_foreign_key'    => 'post_id',
      'meta_function'             => 'get_post_meta',
      'update_meta_function'      => 'update_post_meta',
      'load_meta_function'        => null,
      'save_meta_function'        => null,
      'trigger_actions'           => true, // i.e should we trigger woocommerce actions?
      'trigger_filters'           => true, // i.e should we trigger woocommerce filters when loading/setting values.
      );
  }
  public function setNewRecord( $bool ) {
    $this->_new_record = $bool;
  }
  public function isNewRecord() {
    return $this->_new_record;
  }
  public function isValid() {
    return $this->_valid;
  }
  public function setValid( $bool ) {
    $this->_valid = $bool;
    return $this;
  }
  public function showSQL() {
    $sql = "";
    foreach ($this->_queries_to_run as $key => $query) {
      $sql .= "$key => [[[ $query ]]]\n";
    }
    echo $sql; 
  }
  public function getModelId() {
    return $this->_actual_model_id;
  }
  public function setModelId( $id ) {
    $this->_actual_model_id = $id;
  }

  /**
  *  You will need to define an all and where method on the child model.
  */
  public function done() {
   global $wpdb;
    if ( ! is_array($this->_queries_to_run) )
        return $this;
    foreach ( $this->_queries_to_run as $key=>$query ) {
      $ret = $wpdb->query($query);
      static::maybe_throw_wp_error( $ret );
      unset($this->_queries_to_run[$key]);
    }
  }

  public function addQuery($sql) {
    $this->_queries_to_run[] = $sql;
  }

  public function page( $num = 0 ) {
    $num = intval($num);
    $tnum = $num - 1;
    if ( $tnum <= 0 ) {
      $this->_page = $num;
    } else {
      $num = ($num * $this->_per_page);
      $this->_page = $num;
    }
    return $this;
  }

  public function per( $num = 25 ) {
    $num = intval($num);
    $this->_per_page = $num;
    return $this;
  }
  public function order($o) {
    $this->__order = $o;
    return $this;
  }
  public function getAdapter() { return static::$adapter;}
  // converts the meta attribs the other way around, from friendly name to unfriendly name.l
  public function remapMetaAttributes() {
    Helpers::debug(get_called_class()."::remapMetaAttributes");
    $attrs = array();
    foreach ( static::$_meta_attributes_table as $name => $desc ) {
       $value = $this->dynamic_get($name, $desc);
      if ( empty( $value ) && isset( $desc['default'] ) ) {
        $value = $desc['default'];
      }

      $attrs[ $desc['name'] ] = $value;
    }
    return $attrs;
  }
  public function loadMetaAttributes() {
    static::setupMetaAttributes();
    static::setupModelAttributes();
    Helpers::debug(get_called_class() ."::loadMetaAttributes called");
    $s = static::getModelSettings();
    $meta_function = $s['meta_function'];
    $load_meta_function = $s['load_meta_function'];
    $id = $this->_actual_model_id;
    if ( $load_meta_function !== null ) {
      Helpers::debug("Using load_meta_function!");
      $attrs = call_user_func($load_meta_function, $this);
      foreach ( static::$_meta_attributes_table as $name => $desc ) {
        $value = $attrs[ $desc['name'] ];
        $value = maybe_unserialize($value);
        $this->dynamic_set( $name, $desc, $value, $this->getModelId() );
      }
    } else {
      foreach ( static::$_meta_attributes_table as $name => $desc ) {
        Helpers::debug("\tLoading meta_attribute $name");
        $value = call_user_func($meta_function, $id, $desc['name'], true );
        $value = maybe_unserialize($value);
        Helpers::debug("\tresult from db is: " . var_export($value,true));
        $this->dynamic_set( $name, $desc, $value, $this->getModelId() );
      }
    }
    return $this;
  }
  public function getIdentString() {
    return get_called_class() . "({$this->_actual_model_id}): ";
  }
  public function saveMetaAttributes() {
    Helpers::debug("Base::saveMetaAttributes " . get_called_class() . "({$this->_actual_model_id}) called");
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $meta_table              = $this->orEq( $self->settings, 'meta_table', $wpdb->postmeta ); 
    $meta_table_foreign_key  = $this->orEq( $self->settings, 'meta_table_foreign_key', 'post_id' );
    $save_meta_function = $self->settings['save_meta_function'];
    Helpers::debug($this->getIdentString() ."meta_table is $meta_table fkey is $meta_table_foreign_key");
    if ( $save_meta_function) {
      Helpers::debug($this->getIdentString() ."calling save_meta_function");
      $meta_sql = call_user_func($save_meta_function, $this);
    } else {
      $hits = 0;
      $attribute_names = array();
      $meta_sql = "
        UPDATE {$meta_table}
          SET `meta_value` = CASE `meta_key`
            ";
            foreach ($self->meta_attributes_table  as $attr => $desc) {
              Helpers::debug($this->getIdentString() ."METASQL: $attr => {$desc['name']}");
              if ( isset( $this->_meta_attributes[$attr] ) ) {
                Helpers::debug($this->getIdentString() ."Dynamic Searching meta_attribute for $attr");
                $value = $this->_meta_attributes[$attr];
                if ( empty( $value ) && isset( $desc['default'] ) ) {
                  Helpers::debug($this->getIdentString() .'The value was empty but a default was set.');
                  $value = $desc['default'];
                }
                if ( ! empty($value) ) {
                  if ( isset( $desc['updater'] ) ) {
                    Helpers::debug($this->getIdentString() ."METASQL:Calling updater! for attr $attr");
                    Helpers::debug($this->getIdentString() ."METASQL:Calling updater! for value $value");
                    $this->apply_updater($value,$desc,'::saveMetaAttributes');
                  } else {
                    Helpers::debug($this->getIdentString() ."METASQL:No updater set for $attr for value $value");
                    //$meta_keys[] = $wpdb->prepare("%s",$desc['name']);
                    $attribute_names[] = $wpdb->prepare( "%s", $desc['name']);
                    $meta_sql .= $wpdb->prepare( "\tWHEN '{$desc['name']}' THEN %s\n ", $value);
                    $hits++;
                  }
                } else {
                  Helpers::debug($this->getIdentString() ."METASQL: The value was empty");
                }
              } else {
                Helpers::debug($this->getIdentString() ."METASQL: Not set in this->_meta_attributes");
                if ( isset( $desc['updater'] ) ) {
                  Helpers::debug($this->getIdentString() ."METASQL:Calling updater! for $attr");
                  $this->apply_updater($value,$desc,'::saveMetaAttributes');
                }
              }
            }
            $meta_sql .= "
            ELSE `meta_value`
          END 
        WHERE `{$meta_table_foreign_key}` = '{$this->_actual_model_id}' AND `meta_key` IN (".join(",",$attribute_names).")
      ";
    }
    if ( is_string($meta_sql) && count($attribute_names) > 0) {
      Helpers::debug($this->getIdentString() ."METASQL: is a string!");
      Helpers::debug($this->getIdentString() ."METASQL: ". $meta_sql);
      $ret = $wpdb->query($meta_sql);
      static::maybe_throw_wp_error( $ret );
    } else {
      Helpers::debug($this->getIdentString() ."METASQL: was not a string");
    }
  }
  public function createMetaAttributes() {
    Helpers::debug("Base::createMetaAttributes Posts " . get_called_class() . "({$this->_actual_model_id}) called");
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $meta_table              = $this->orEq( $self->settings, 'meta_table', $wpdb->postmeta ); 
    $meta_table_foreign_key  = $this->orEq( $self->settings, 'meta_table_foreign_key', 'post_id' );
    $save_meta_function = $self->settings['save_meta_function'];
    Helpers::debug($this->getIdentString() ."meta_table is $meta_table fkey is $meta_table_foreign_key");
    if ( $save_meta_function) {
      Helpers::debug($this->getIdentString() ."calling save_meta_function");
      $meta_sql = call_user_func($save_meta_function, $this);
    } else {
      $hits = 0;
      $meta_sql = array();
      $attribute_names = array();
      foreach ($self->meta_attributes_table  as $attr => $desc) {
        Helpers::debug($this->getIdentString() ."METASQL: $attr => {$desc['name']}");
        if ( isset( $this->_meta_attributes[$attr] ) ) {
          Helpers::debug($this->getIdentString() ."Searching meta_attribute for $attr");
          $value = $this->_meta_attributes[$attr];
          if ( empty( $value ) && isset( $desc['default'] ) ) {
            Helpers::debug($this->getIdentString() .'The value was empty but a default was set.');
            $value = $desc['default'];
          }
          if ( ! empty($value) ) {
            if ( isset( $desc['updater'] ) ) {
              Helpers::debug($this->getIdentString() ."METASQL:Calling updater! for $attr");
              $this->apply_updater($value,$desc,'::saveMetaAttributes');
            } else {
              Helpers::debug($this->getIdentString() ."METASQL:No updater set for $attr for value $value");
              //$meta_keys[] = $wpdb->prepare("%s",$desc['name']);
              $attribute_names[] = $wpdb->prepare( "%s", $desc['name']);
              $meta_sql[] = $wpdb->prepare( "INSERT INTO {$wpdb->postmeta} (post_id,meta_key,meta_value) VALUES ({$this->_actual_model_id},'{$desc['name']}', %s)", $value);
              $hits++;
            }
          } else {
            Helpers::debug($this->getIdentString() ."METASQL: The value was empty");
          }
        } else {
          Helpers::debug($this->getIdentString() ."METASQL: Not set in this->_meta_attributes");
          if ( isset( $desc['updater'] ) ) {
            Helpers::debug($this->getIdentString() ."METASQL:Calling updater! for $attr");
            $this->apply_updater($value,$desc,'::saveMetaAttributes');
          }
        }
      }
    }
    if ( is_array($meta_sql) && count($attribute_names) > 0 && count($meta_sql) > 0) {

      Helpers::debug($this->getIdentString() ."METASQL: ". var_export($meta_sql,true));
      foreach ( $meta_sql as $stmt) {
        $ret = $wpdb->query($stmt);
        static::maybe_throw_wp_error( $ret );
      }
    } else {
      Helpers::debug($this->getIdentString() ."METASQL: was not valid");
    }
  }
  // We need an easier interface to fetching items
  public function fetch( $callback = null ) {
    Helpers::debug( "Base::fetch called");
   global $wpdb;
    $sql = $this->_queries_to_run[count($this->_queries_to_run) - 1];
    if ( ! empty($sql) ) {
      if ( $this->__order && !empty($this->__order) ) {
        $sql .= " ORDER BY {$this->__order} ";
      } 
      if ( $this->_per_page && $this->_page ) {
        $page = $this->_page - 1;
        $sql .= " LIMIT {$page},{$this->_per_page}";
      }

      $results = $wpdb->get_results($sql,'ARRAY_A');
      Helpers::debug("in function fetch: WPDB returned " . count($results) . " results using $sql");
      if ($callback) {
        foreach ( $results as &$result ) {
          if ( $callback ) {
            $result = call_user_func($callback,$result);
          }
        }
      } else {
        $klass = get_called_class();
        $models = array();
        $s = static::getModelSettings();
        $meta_function = $s['meta_function'];
        foreach ( $results as $record ) {
          $model = new $klass();
          if ( isset( $record['id']) ) {
            $id = $record['id'];
          } else if ( isset( $record['ID']) ) {
            $id = $record['ID'];
          } else if ( isset( $record[ $s['model_table_id'] ] ) ) {
            $id = $record[ $s['model_table_id'] ];
          }
          $model->setModelId( $id );
          foreach ( static::$_model_attributes_table as $name => $desc ) {
            $model->dynamic_set( $name, $desc,$record[ $desc['name'] ] );
            //$model->{$name} = $record[$desc['name']];
          }
          $model->loadMetaAttributes();
          $model->setValid( true );
          $model->setNewRecord( false );
          $models[] = $model;
        }
        return $models;
      }
      if (count($results) < 1) {
        Helpers::debug("in function fetch, empty result set using: $sql");
      } else {
        Helpers::debug("in function fetch: " . count($results) . " were returned from: " . $sql);
      }
      return $results;
    } else {
      Helpers::debug("in function fetch, sql was empty.");
      return null;
    }
  }
  

  public function saveAssociations() {
    Helpers::debug("Base::saveAssociations beginning");
   global $wpdb;
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $hm = $this->orEq($this->actual_model_settings,'has_many',array());
    foreach ($hm as $name => $desc ) {
      if ( isset( $this->{ $name } ) ) {
        $values = $this->{ $name };
        if ( is_array($values) ) {
          foreach ( $values as &$value ) {
            if ( is_array( $value ) ) {
              $class = 'WCAPI\\' . $desc['class_name'];  
              Helpers::debug("is_array and class is: $class");           
              if ( isset( $value['id'] ) ) {
                  if ( is_array( $value['id'] ) ){
                      $tab=$value['id'];
                      foreach ( $tab as $id ){
                          $model =  $class::find( $id );
                       if ( $model->isValid() ) {
                  if ( isset($value['__disconnect__']) ) {
                      Helpers::debug("Array calling __disconnect__");  
                    call_user_func($desc['disconnect'], $this, $model);
                    unset($value['__disconnect__']);
                    $value['__disconnected__'] = true;
                  } else {
                    $model->fromApiArray( $value );
                    $model->update();
                    if ( isset($desc['connect'])) {
                      call_user_func($desc['connect'], $this, $model);
                    }
                  } 
                }
                      }
                  }
                else{
                    $model =  $class::find( $value['id'] );
                if ( $model->isValid() ) {
                    Helpers::debug("id is already set ised {$value['id']}");
                  if ( isset($value['__disconnect__']) ) {
                      Helpers::debug("calling __disconnect__");  
                    call_user_func($desc['disconnect'], $this, $model);
                    unset($value['__disconnect__']);
                    $value['__disconnected__'] = true;
                  } else {
                    $model->fromApiArray( $value );
                    $model->update();
                    if ( isset($desc['connect'])) {
                      call_user_func($desc['connect'], $this, $model);
                    }
                  } 
                }
              }//finn else
              } else {
                Helpers::debug("need to create association of type $class and value ".var_export($value,true));
                $model = new $class();
                $model = $model->setResult($this->_result);
                if($class !="WCAPI\Product"){
                $_model=$class::find_by_name( $value );
                if(!$_model){
                $model->create( $value );
                
                $model =  $class::find( $model->_actual_model_id );
                } 
                else $model =$_model;
                }
                else {
                     $model->create( $value );
                
                $model =  $class::find( $model->_actual_model_id );
                }
                $model = $model->setResult($this->_result);
                // now we need to connect them
                if ( isset( $desc['connect'] ) ) {
                  call_user_func($desc['connect'], $this, $model);
                } else {
                  Helpers::debug("default connection");
                  $ms = $model->getModelSettings();
                  $fkey = $desc['foreign_key'];
                  $sql = "UPDATE {$ms['model_table']} SET {$fkey} = %s WHERE {$ms['model_table_id']} = %s";
                  $sql = $wpdb->prepare($sql,$this->_actual_model_id, $model->_actual_model_id);
                  Helpers::debug("connection sql is: $sql");
                  $wpdb->query($sql);
                }
                $value = $model->asApiArray();
              }

            }
            else {
              //not handling model saving just yet
            }

          } // end foreach
          $this->{ $name } = $values;
        } // end is_array($values)
      }
    }
  }
  public function getConditionsString( $conditions ) {
      $sql = "";
      Helpers::debug("Base::getConditionsString " . var_export($conditions,true));
      if ( is_array( $conditions ) ) {

        $sql = join(' AND ', $conditions);
      } else if ( is_callable($conditions) ) {
        $sql = call_user_func($conditions,$this);
      } else if ( is_string($conditions) ) {
        $sql = $conditions;
      }
      Helpers::debug("Base::getConditionsString returning $sql");
      return $sql;
  }
  public function loadHasManyAssociation( $name ) {
    Helpers::debug("Base::loadHasManyAssociation $name");
   global $wpdb;
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $hm = $this->actual_model_settings['has_many'];
    $models = array();
    //echo "Loading $name\n";
    if ( isset( $hm[$name] ) ) {

      $klass = 'WCAPI\\' . $hm[$name]['class_name'];
      $fkey = $this->orEq($hm[$name],'foreign_key', false);
      $s = $klass::getModelSettings();
      if ( isset( $hm[$name]['sql'] ) ) {
        //echo $sql . "\n";
        if ( is_callable($hm[$name]['sql'])) {
          Helpers::debug("sql is a function, so we should call it!");
          $sql = call_user_func($hm[$name]['sql'],$this);
          if ( ! $sql ) {
            return array();
          }
        } else {
          $sql = $wpdb->prepare($hm[$name]['sql'], $this->_actual_model_id);
        }
      } else {
        $sql = $wpdb->prepare("SELECT {$s['model_table_id']} FROM {$s['model_table']} WHERE {$fkey} = %d",$this->_actual_model_id);
      }
      if ( isset($hm[$name]['conditions']) ) {
        $conditions = $this->getConditionsString($hm[$name]['conditions']);
        if ( !$conditions ) {
          Helpers::debug("conditions is false, returing");
          return array();
        }
        $sql .= " AND ($conditions)";
      }
      Helpers::debug("Base::loadHasManyAssociation sql is $sql");
      $ids = $wpdb->get_col($sql);
      foreach ( $ids as $id ) {
        $model = $klass::find($id);
        $models[] = $model->asApiArray();
      }
    }
    return $models;
  }
  public function saveHasManyAssociation( $name ) {
    throw new \Exception("WTF? I shouldn't be called");
   global $wpdb;
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;
    $hm = $s['has_many'][$name];
    $models = $this->{$name};
    if ( is_array( $models ) ) {
      foreach ( $models as $model ) {
        if ( is_array( $model ) ) {
          $klass = 'WCAPI\\' . $hm['class_name'];
          if ( isset( $model['id'] ) ) {
            $obj = $klass::find( $model['id'] );
            $obj->fromApiArray( $model );
            $obj->update();
          } else {
            // The record doesn't exist, so we create a new one.
            $obj = new $klass();
            $obj->create( $model );
          }
        } else {
          $model->update();
        }
      }
    }
  }
  public function loadBelongsToAssociation( $name ) {
   global $wpdb;
    $hm =  static::$_model_settings['belongs_to'];
    $model = null;
    if ( isset( $hm[$name] ) ) {
      $klass = "WCAPI\\" . $hm[$name]['class_name'];
      $fattr = $this->orEq($hm[$name],'meta_attribute', false);
      if ( !$fattr )
        $fattr = $this->orEq($hm[$name],'foreign_key', false);
      $s = $klass::getModelSettings();
      if ( $fattr ) {
        $model = $klass::find( $this->{$fattr});
      }
    }
    return $model;
  }
  /**
  *  From here we have a dynamic getter. We return a special REDENOTSET variable.
  */
  public function __get( $name ) {
    Helpers::debug(get_called_class() . "::__get $name");
    if ( strpos($name,"__") === 0 ) {
      return $this->{$name};
    }
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;

    if ( isset( $meta_table[$name] ) ) {
      $desc = $meta_table[$name];
      if ( isset($desc['getter']) && is_callable( $desc['getter'] )) {
        $value = call_user_func($desc['getter'], $desc);
      }
      if ( isset ( $this->_meta_attributes[$name] ) ) {
        return $this->_meta_attributes[$name];
      } else {
        return '';
      }
    } else if ( isset( $model_table[$name] ) ) {
      if ( isset( $this->_model_attributes[$name] ) ) {
        return $this->_model_attributes[$name];
      } else {
        return '';
      }
    } else if ( isset( $s['has_many'] ) && $this->inArray( $name, array_keys($s['has_many']) ) ) {
      return $this->loadHasManyAssociation($name);
    } else if ( isset( $s['belongs_to'] ) && $this->inArray( $name, array_keys($s['belongs_to']) ) ) {
      return $this->loadBelongsToAssociation($name);
    }
  } // end __get
  
  // Dynamic setter
  public function __set( $name, $value ) {
    Helpers::debug(get_called_class() . "::__set $name " . var_export($value,true));
    if ( strpos($name,"__") === 0 ) {
      $this->{$name} = $value;
      return;
    }
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;

    if ( ! isset( $meta_table[$name] ) ) {
      Helpers::debug(get_called_class() . "::__set $name is not defined in meta_table");
    }
    if ( isset( $meta_table[$name] ) ) {
      Helpers::debug(get_called_class() . "::__set is set meta_table $name " . var_export($value,true));
      $desc = $meta_table[$name];
      if ( isset($desc['setter'])) {
        Helpers::debug("::__set is applying setter for $name");
        apply_setter($value,$desc,'__set');
      } else {
           Helpers::debug("aladin2 $name value".$value);
        $this->_meta_attributes[$name] = $value;
      }
    } else if (strtolower($name) == 'id') {
      Helpers::debug(get_called_class() . "::__set if strtolower id $name " . var_export($value,true));
      $this->id = $value;
    } else if ( isset( $model_table[$name] ) ) {
      Helpers::debug(get_called_class() . "::__set if isset mode_table $name " . var_export($value,true));
      $this->_model_attributes[$name] = $value;
    }  else if ( isset( $s['has_many'] ) && $this->inArray( $name, array_keys($s['has_many']) ) ) {
        $many=$s['has_many'];
        $keymany= array_keys($s['has_many']);
        Helpers::debug(get_called_class() . " Model table :".var_export($model_table,true).' Meta table: ' . var_export($meta_table,true));
        Helpers::debug(get_called_class() . " has_many table :".var_export($many,true).' keymany: ' . var_export($keymany,true));
      Helpers::debug(get_called_class() . "::__set if isset has_many $name " . var_export($value,true));
      $this->{$name} = $value;
    } else {
      throw new \Exception( sprintf(__('That attribute %s does not exist to be set to %s. for %s','woocommerce_json_api'),"`$name`", (string)var_export($value,true), get_called_class()) );
    }
  }

  public function apply_getter($desc,$called_from) {
    Helpers::debug("Base::apply_getter {$desc['name']} called from $called_from");
    if ( is_string($desc['getter'])) {
      Helpers::debug("is_string");
      $arr=$this->{ $desc['getter'] }($desc);
      $bla=$arr[0];
      Helpers::debug("Hobi".$bla);
      return $arr[1];
      //return $this->{ $desc['getter'] }($desc);
    } else if ( is_callable($desc['getter'])) {
      Helper::debug("is_callable");
      return call_user_func($desc['getter'], $this, $desc);
    } else {
      throw new \Exception( $desc['getter'] .' getter is not a function in this scope');
    }
  }
  public function apply_setter($value, $desc,$called_from) {
    Helpers::debug("Base::apply_setter {$desc['name']} called from $called_from");
    if ( is_string($desc['setter'])) {
      Helpers::debug("is_string");
     $this->{ $desc['setter'] }($value,$desc);
    } else if ( is_callable($desc['setter'])) {
      Helpers::debug("is_callable");
      call_user_func($desc['setter'], $this, $value, $desc);
    } else {
      throw new \Exception( $desc['setter'] .' setter is not a function in this scope');
    }
  }
  public function apply_updater($value, $desc,$called_from) {
    Helpers::debug("Base::apply_updater {$desc['name']} called from $called_from");
    Helpers::debug("METASQL:Calling updater! for desc {$desc['updater']}");
    if ( is_string($desc['updater'])) {
      Helpers::debug("is_string");
      $this->{ $desc['updater'] }($value,$desc);
    } else if ( is_callable($desc['updater'])) {
      Helpers::debug("is_callable");
      call_user_func($desc['updater'], $this, $value, $desc);
    } else {
      throw new \Exception( $desc['updater'] .' updater is not a function in this scope');
    }
  }
/* if ( isset( $self->attributes_table[$name]) ) {
          $desc = $self->attributes_table[$name];
          Helpers::debug("self attributes_table/".$desc );
          Helpers::debug("End self attributes_table" );
          $this->dynamic_set( $name, $desc, $value, false);
        } else {
            Helpers::debug("Not self attributes_table".'name: '.$name.'/value: '.$value );
          $this->{ $name } = $value;
        }*/
  public function dynamic_set( $name, $desc, $value, $filter_value = null ) {
    Helpers::debug(get_called_class(). "::dynamic_set $name and " . var_export($value,true));
    if ( strpos($name,"__") === 0 ) {
      $this->{$name} = $value;
      return;
    }
    Helpers::debug("aladin1 type ". $desc['type'] ." value ".$value);
    if ( $desc['type'] == 'array') {
      $value = maybe_serialize( $value );
    }

    // if ( isset($desc['filters'] ) && $filter_value == true ) {
    //   foreach ( $desc['filters'] as $filter ) {
    //     Helpers::debug("applying filters to $name");
    //     $value = apply_filters( $filter, $value, $filter_value );
    //   }
    // }
    if ( isset($desc['setter']) ) {
      Helpers::debug("applying setter for $name");
      $this->apply_setter($value,$desc,'::dynamic_set');
    } else {
      if ( (!$value || empty($value) ) && (isset($desc['default']) && $desc['default'] && ! empty($desc['default']) ) ) {
        $value = $desc['default'];
      }
      Helpers::debug("Setting this->$name");
      $this->{ $name } = $value;
      if ( isset($desc['overwrites']) && is_array($desc['overwrites']) ) {
        foreach ($desc['overwrites'] as $attr ) {
          $this->{$attr} = $value;
        }
      }
    }
  }

  public function dynamic_get( $name, $desc, $filter_value = null ) {
    Helpers::debug(get_called_class(). "::dynamic_get $name and");
    if ( strpos($name,"__") === 0 ) {
      return $this->{$name};
    }
    if ( isset($desc['getter']) ) {
      $value = $this->apply_getter($desc,'::dynamic_get');
    } else {
      $value = $this->{ $name };
    }
    if ( (!$value || empty($value) ) && (isset($desc['default']) && $desc['default'] && ! empty($desc['default']) ) ) {
      $value = $desc['default'];
    }
    if ( isset($desc['type']) && $desc['type'] == 'array' && !is_array($value) ) {
      $value = maybe_unserialize( $value );
    }
    if ( isset($desc['type']) && $desc['type'] == 'array' && empty($value) ) {
      $value = array();
    }
    return $value;
  }
  /**
  * Sometimes we want to act directly on the result to be sent to the user.
  * This allows us to add errors and warnings.
  */
  public function setResult ( $result ) {
    $this->_result = $result;
    return $this;
  }

  public static function find( $id ) {
   global $wpdb;
   
    include WCAPIDIR."/_model_static_attributes.php";

    $model = new static();
    $model->setValid( false );
    
    $model_table             = $model->orEq( $self->settings, 'model_table', $wpdb->posts );  
    $meta_table              = $model->orEq( $self->settings, 'meta_table', $wpdb->postmeta );
    $model_table_id          = $model->orEq( $self->settings, 'model_table_id', 'ID' );   
    $meta_table_foreign_key  = $model->orEq( $self->settings, 'meta_table_foreign_key', 'post_id' );
    $meta_function           = $model->orEq( $self->settings, 'meta_function', 'get_post_meta' );

    $record = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$model_table} WHERE {$model_table_id} = %d", (int) $id), 'ARRAY_A' );
    
    if ( $record ) {
      //$model->setModelId( $id );
      // foreach ( static::$_model_attributes_table as $name => $desc ) {
      //   $model->dynamic_set( $name, $desc,$record[ $desc['name'] ] );
      //   //$model->{$name} = $record[$desc['name']];
      // }
      // $model->loadMetaAttributes();
      
      // $model->setValid( true );
      // $model->setNewRecord( false );
      $model->fromDatabaseResult( $record );
    
    } else {
      $model->setValid(false);
    }
    return $model;
  }
  public function fromDatabaseResult( $record ) {
    
    //Helpers::debug( $this->getIdentString() . "::fromDatabaseResult " . var_export($record,true));
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $model = $this;
    $model_table_id          = $model->orEq( $self->settings, 'model_table_id', 'ID' ); 

    // There is some inconsistencies in the ID,id, user_id, comment_post_ID etc.
    // and when we select, we get back sometimes different things. I neither know
    // nor really care why. 
    if ( isset( $record[ $model_table_id ] ) )
      $model->setModelId( $record[ $model_table_id ] );
    else if ( isset( $record[ strtolower($model_table_id) ] ) )
      $model->setModelId( $record[ strtolower($model_table_id) ] );
    else if ( isset( $record[ strtoupper($model_table_id) ] ) )
      $model->setModelId( $record[ strtoupper($model_table_id) ] );
    else
      throw new \Exception( __( sprintf('fromDatabaseResult requires that %s be in %s',$model_table_id, var_export($record,true)),'WCAPI' ) ) ;
      
    foreach ( static::$_model_attributes_table as $name => $desc ) {
      $model->dynamic_set( strtolower($name), $desc,$record[ $desc['name'] ] );
      //$model->{$name} = $record[$desc['name']];
    }
    $model->loadMetaAttributes();
    
    $model->setValid( true );
    $model->setNewRecord( false );
  }
  public function fromApiArray( $attrs ) {
   // Helpers::debug($this->getIdentString() . "::fromApiArray " . var_export($attrs,true) );
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;
    $attributes = array_merge($model_table, $meta_table);
    foreach ( $attrs as $name => $value ) {
      if ( isset($attributes[$name]) ) {
        $desc = $attributes[$name];
        $this->dynamic_set( $name, $desc, $value, $this->getModelId());
      } 
    }
    if ( isset( $s['has_many'] ) ) {
      $hm = $s['has_many'];
      foreach ( $hm as $key=>$value ) {
        if (isset($attrs[$key])) {
          $this->{$key} = $attrs[$key];
        }
      }
    }
    return $this;
  }
  public function asApiArray($args = array()) {
   global $wpdb;
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;
    $attributes = array_merge($model_table, $meta_table);
    $attributes_to_send['id'] = $this->getModelId();

    foreach ( $attributes as $name => $desc ) {
         $attributes_to_send[$name] = $this->dynamic_get( $name, $desc, $this->getModelId());   
    }
    return $attributes_to_send;
  }
  public function getSupportedAttributes() {
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;
    $attributes = array_merge($model_table, $meta_table);
    return $attributes;
  }

    /**
  *  Similar in function to Model.all in Rails, it's just here for convenience.
  */
  public static function all($fields = 'id', $conditions = null, $override_model_conditions = false) {
  global $wpdb;
   
    // static::setupModelAttributes();
    // static::setupMetaAttributes();
    $model = new static();
    if ( isset( static::$_model_settings ) ) {
      $model_table             = $model->orEq( static::$_model_settings, 'model_table', $wpdb->posts );  
      $model_table_id          = $model->orEq( static::$_model_settings, 'model_table_id', 'ID' );   
      $model_conditions        = $model->orEq( static::$_model_settings, 'model_conditions', '' );  
    } else {
      $model_table             = $wpdb->posts;  
      $model_table_id          = 'ID'; 
      $model_conditions        = '';  
    }
    $model_conditions = $model->getConditionsString($model_conditions);
    $conditions = $model->getConditionsString( $conditions );
    if ( ! empty( $model_conditions) && $conditions && ! empty( $conditions )) {
      if ( $override_model_conditions ) {
        $model_conditions = " WHERE $conditions";
      } else {
        $model_conditions .= " AND ($conditions)";
      }
    } else if ( empty( $model_conditions) && $conditions && ! empty( $conditions )) {
      $model_conditions = $conditions;
    }
    $sql = "SELECT $fields FROM {$model_table} {$model_conditions}";
    Helpers::debug("sql for " . get_called_class() . "::all $sql");
    $model->addQuery($sql);
    return $model;
  }

  public function create( $attrs = null ) {
    Helpers::debug("Base::create() for " . $this->getIdentString() );
    include WCAPIDIR."/_model_static_attributes.php";
    $meta_table = $this->actual_meta_attributes_table;
    $model_table = $this->actual_model_attributes_table;
    $s = $this->actual_model_settings;
    global $wpdb;
    $user_ID = $GLOBALS['user_ID'];

    // Maybe we want to set attribs and create in one go.
    if ( $attrs ) {
      Helpers::debug( $this->getIdentString() . "attrs is set" );
      Helpers::debug( var_export($attrs,true) );
      Helpers::debug( var_export($attrs,true) );
      foreach ( $attrs as $name=>$value ) {
        if ( isset( $self->attributes_table[$name]) ) {
          $desc = $self->attributes_table[$name];
          $this->dynamic_set( $name, $desc, $value, false);
        } else {
          $this->{ $name } = $value;
        }
        
      }
    }
    $post = array();

    $update_meta_function = $s['update_meta_function'];
    
    if ( $s['model_table'] == $wpdb->posts)
      $post['post_author'] = $user_ID;

    foreach ($model_table as $attr => $desc) {

      $value = $this->dynamic_get( $attr, $desc, null);
      $post[ $desc['name'] ] = $value;

    }
    if ( $s['model_table'] == $wpdb->posts) {
      $id = wp_insert_post( $post, true);
      Helpers::debug( "Post Array:" . var_export(array($id,$model_table,$post),true) );
    } else {
      if ( $wpdb->insert($s['model_table'],$post) ) {
        $id = $wpdb->insert_id;
      } else {
        $this->_result->addError( 
          __('Failed to create ' . get_called_class() ), 
          WCAPI_CANNOT_INSERT_RECORD 
        );
        return $this;
      }
    }
    if ( is_wp_error( $id )) {
      Helpers::debug(" is_wp_error");
      $this->setValid(false);
      $this->_result->addError( 
        __('Failed to create ' . get_called_class() ), 
        WCAPI_CANNOT_INSERT_RECORD 
      );
    } else {
      $this->setValid(true);
      $this->_actual_model_id = $id;
      $this->runAfterCreateCallbacks();
      if ( isset( $self->settings['create_meta_function']) ) {
        Helpers::debug("calling create_meta_funtion");
        call_user_func($self->settings['create_meta_function'],$this);
      } else {
        $this->createMetaAttributes(); 
      }
      Helpers::debug("saving associations for " . get_called_class());
      $this->saveAssociations();
    }
    return $this;
  }
  public function runAfterCreateCallbacks() {
    if ( isset( $this->after_create) ) {
      if ( is_array( $this->after_create) ) {
        foreach ( $this->after_create as $cb ) {
          if ( is_callable($cb) ) {
            call_user_func($cb,$this);
          }
        }
      } else if ( is_string( $cb ) ) {
        call_user_func($cb,$this);
      } else if ( is_callable($cb) ) {
        call_user_func($cb,$this);
      }
    }
  }
  public function update( $attrs = null) {
    Helpers::debug(get_called_class() . "({$this->_actual_model_id}) is beginning an update.");
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $model_table             = $this->orEq( $self->settings, 'model_table', $wpdb->posts );  
    $model_table_id          = $this->orEq( $self->settings, 'model_table_id', 'ID' );  
    Helpers::debug("model_table is $model_table and model_table_id is $model_table_id");
    if ( $attrs && is_array($attrs) ) {
      Helpers::debug("attrs where sent, calling fromApiArray");
      $this->fromApiArray($attrs);
    }
    $values = array();
    foreach ($self->model_attributes_table as $attr => $desc) {
      $value = $this->dynamic_get( $attr, $desc, $this->getModelId());
      $values[] = $wpdb->prepare("`{$desc['name']}` = %s", $value );
    }
    $post_sql = "UPDATE `{$model_table}` SET " . join(',',$values) . " WHERE `{$model_table_id}` = '{$this->_actual_model_id}'";
    Helpers::debug("post_sql is $post_sql");
    $ret = $wpdb->query($post_sql);
    static::maybe_throw_wp_error($ret);
    $this->saveMetaAttributes();
    $this->saveAssociations();
    return $this;
  }
  public function UpdateTaxonomy($table,$key_values,$where=null) {
      global $wpdb;
      include WCAPIDIR."/_model_static_attributes.php";
       $keys = array();
       $values = array();
       $table = "`$table`";
      Helpers::debug(get_called_class() . "::update $table");
      $values = array();
      foreach ( $key_values as $key=>$value ) {
      if ( $key = $this->databaseAttribute($key) ) {
        $values[] = $wpdb->prepare("`$key` = %s", $value );
      }
    }
    if ( count($values) > 0 ) {
      $sql= "UPDATE $table SET " . join(',',$values);
    }
    if ( is_array( $where ) ) {
      $conditions = array();
      foreach ( $where as $key=>$value ) {
        if ( $key = $this->databaseAttribute($key) ) {
          $conditions[] = $wpdb->prepare("`$key` = %s",$value);
        }
      }
      
      if ( count($conditions) > 0 ) {
        $sql .= " WHERE " . join(' AND ', $conditions);
      } else {
        throw new \Exception( __("Array of WHERE conditions doesn't lead to a where clause?",'WCAPI') );
      }
    } else if ( is_string($where) ) {
      $sql .= " WHERE " . $where;
    }
    Helpers::debug("SQL request: $sql");
      $ret = $wpdb->query($sql);
      static::maybe_throw_wp_error( $ret );
  }
  public function insert($table, $key_values, $where=null) {
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $keys = array();
    $values = array();
    $table = "`$table`";
    Helpers::debug(get_called_class() . "::insert $table");
    foreach ( $key_values as $key=>$value ) {
      if ( $key = $this->databaseAttribute($key) ) {
        $keys[] = "`$key`";
        $values[] = $wpdb->prepare('%s',$value);
      }
    }
    if ( count($values) > 0 ) {
      $sql = "INSERT INTO $table (" . join(',',$keys). ") VALUES (" . join(',', $values) . ")";
      $ret = $wpdb->query($sql);
    }
  }
  public static function maybe_throw_wp_error( $val ) {
    if ( is_wp_error($val) ) {
      throw new \Exception( get_called_class() . " : " . $val->get_messages() );
    }
  }
  public function delete($table, $where = null, $limit = 1) {
    global $wpdb;
    include WCAPIDIR."/_model_static_attributes.php";
    $table = "`$table`";
    Helpers::debug(get_called_class() . "::delete $table");
    $sql = "DELETE FROM $table";
    if ( is_array( $where ) ) {
      $conditions = array();
      foreach ( $where as $key=>$value ) {
        if ( $key = $this->databaseAttribute($key) ) {
          $conditions[] = $wpdb->prepare("`$key` = %s",$value);
        }
      }
      if ( count($conditions) > 0 ) {
        $sql .= " WHERE " . join(' AND ', $conditions);
      } else {
        throw new \Exception( __("Array of WHERE conditions doesn't lead to a where clause?",'WCAPI') );
      }
    } else if ( is_string($where) ) {
      $sql .= " WHERE " . $where;
    } else {
      throw new \Exception( sprintf(__("you cannot call %s::delete without a WHERE clause specifcy conditions in \$where, that is highly dangerous!",'WCAPI'), get_called_class()) );
    }
    //$sql .= " LIMIT $limit";
    Helpers::debug("SQL request: $sql");
    if ( strpos($sql,'WHERE') === FALSE) {
      throw new \Exception( sprintf(__("you cannot call %s::delete without a WHERE clause, that is highly dangerous!",'WCAPI'), get_called_class()) );
    } else {
      $ret = $wpdb->query($sql);
      static::maybe_throw_wp_error( $ret );
    }
  }
   public function delete_Associations($id) {
           global $wpdb;
        if (!$id ) {
          $this->result->addWarning( $id . ': ' . __('Image does not exist','woocommerce_json_api'), JSONAPI_PRODUCT_NOT_EXISTS, array( 'id' => $id) );
          return $this->done();
        } else {
          $this->delete($wpdb->posts, array(
        'id' =>  $id
      ) );
          $this->delete($wpdb->postmeta,array(
        'post_id' => $id
      ) );
          $this->delete($wpdb->term_relationships,array(
        'object_id' => $id
      ) );
        }
  }
  public function delete_AllRelatedImage($post_id) {
          $_thumbnail_id = get_post_meta($post_id,"_thumbnail_id",true);
          $product_gallery = get_post_meta($post_id,"_product_image_gallery",true);
          if(!empty($_thumbnail_id)){
              $this->delete_Associations($_thumbnail_id);
          }
          if(!empty($product_gallery)){
              $Allimg=  explode(',',$product_gallery);
              foreach ($Allimg as $id) {
               $this->delete_Associations($id);
           }
          }
  }
  public function getTerm($name,$type,$default) {
    Helpers::debug("Base::getTerm $name $type $default");
    global $wpdb;
    if ( $this->{"_$name"} ) {
      return $this->{"_$name"};
    }
    $sql = "
      SELECT 
        t.slug
      FROM
        {$wpdb->terms} as t,
        {$wpdb->term_relationships} as tr,
        {$wpdb->term_taxonomy} as tt
      WHERE
        tt.taxonomy = '$type' AND
        t.term_id = tt.term_id AND
        tr.term_taxonomy_id = tt.term_taxonomy_id AND
        tr.object_id = {$this->_actual_model_id}
      ORDER BY tr.term_order
    ";

    $terms = $wpdb->get_results( $sql , 'ARRAY_A');
    static::maybe_throw_wp_error( $terms );
    $this->{"_$name"} = (isset($terms[0])) ? $terms[0]['slug'] : $default;
    return $this->{"_$name"};
  }

  public function setTerm($name, $type, $value ) {
    Helpers::debug("Base::setTerm $name $type $value");
    $this->{"_$name"} = $value;
  }
  public function updateTerm( $name, $type, $value=null) {
    Helpers::debug("Base::updateTerm $name $type $value");
    if ( $value == null ) {
      $value = $this->{"_$name"};
    }
    $ret = wp_set_object_terms( $this->_actual_model_id, array( $value ), $type);
    if ( is_wp_error( $ret ) ) {
        Helpers::debug("wp error status");
      //throw new \Exception( $ret->get_messages());
    } else if ( is_string( $ret ) ) {
      //throw new \Exception("Wrong term name $ret");
    }
  }
   public function updateMeta($name,$meta_key,$value=null) {
    Helpers::debug("Base::updateMeta $name $meta_key $value");
    if ( $value == null ) {
      $value = $this->{"_$name"};
    }
    $ret = update_post_meta($this->_actual_model_id,$meta_key,sanitize_text_field($value));
    if ( is_wp_error( $ret ) ) {
        Helpers::debug("wp error tracking_number");
      throw new \Exception( $ret->get_messages());
    } else if ( is_string( $ret ) ) {
      throw new \Exception("Wrong term name $ret");
    }
  }
}

