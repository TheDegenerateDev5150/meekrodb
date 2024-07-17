<?php
/*
  CREATE TABLE `users_params` (
   `id` bigint(20) unsigned NOT NULL,
   `key` varchar(255) NOT NULL,
   `value` varchar(255) NOT NULL,
   `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
   `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
   PRIMARY KEY (`id`,`key`),
   KEY `expires_at` (`expires_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/

abstract class MeekroORM {
  // INTERNAL -- DO NOT TOUCH
  private $_orm_row = []; // processed hash
  private $_orm_row_raw = []; // raw hash (writeable to database)
  private $_orm_row_orig = []; // original raw hash from database
  private $_orm_assoc_load = [];
  private $_orm_is_fresh = true;
  private static $_orm_struct = [];

  // (OPTIONAL) SET IN INHERITING CLASS
  protected static $_orm_tablename = null;
  protected static $_orm_associations = [];
  protected static $_orm_columns = [];
  protected static $_orm_scopes = [];

  // -------------- SIMPLE HELPER FUNCTIONS
  public static function _orm_struct() {
    $table_name = static::_orm_tablename();
    if (! array_key_exists($table_name, self::$_orm_struct)) {
      self::$_orm_struct[$table_name] = new MeekroORMTable(get_called_class());
    }
    return self::$_orm_struct[$table_name];
  }

  public static function _orm_tablename() {
    if (static::$_orm_tablename) return static::$_orm_tablename;
    
    $table = strtolower(get_called_class());
    $last_char = substr($table, strlen($table)-1, 1);
    if ($last_char != 's') $table .= 's';
    return $table;
  }

  public static function _orm_meekrodb() { return DB::getMDB(); }

  protected function _orm_dirty_fields() {
    return array_keys($this->_orm_dirtyhash());
  }

  protected function _orm_dirtyhash() {
    $hash = [];
    foreach ($this->_orm_row_raw as $key => $value) {
      if (!array_key_exists($key, $this->_orm_row_orig) || $value !== $this->_orm_row_orig[$key]) {
        $hash[$key] = $value;
      }
    }

    return $hash;
  }

  protected function _where() {
    $where = new WhereClause('and');
    $primary_keys = static::_orm_struct()->primary_keys();

    foreach ($primary_keys as $key) {
      $where->add('%c = %?', $key, $this->getraw($key));
    }
    
    return $where;
  }

  protected function _orm_run_callback() {
    $args = func_get_args();
    $func_name = array_shift($args);
    $func_call = array($this, $func_name);
    if (is_callable($func_call)) return call_user_func_array($func_call, $args);
    return false;
  }


  public function is_fresh() {
    return $this->_orm_is_fresh;
  }

  // -------------- GET/SET AND MARSHAL / UNMARSHAL
  public function has($key) {
    return !! static::_orm_coltype($key);
  }

  public function get($key) {
    if (! $this->has($key)) {
      throw new MeekroORMException("$this does not have key $key");
    }

    return $this->_orm_row[$key] ?? null;
  }

  public function getraw($key) {
    if (! $this->has($key)) {
      throw new MeekroORMException("$this does not have key $key");
    }

    return $this->_orm_row_raw[$key] ?? null;
  }

  public function set($key, $value) {
    if (! $this->has($key)) {
      throw new MeekroORMException("$this does not have key $key");
    }

    $this->_orm_row_raw[$key] = $this->_marshal($key, $value);
    $this->_orm_row[$key] = $value;
  }

  public function setraw($key, $value) {
    if (! $this->has($key)) {
      throw new MeekroORMException("$this does not have key $key");
    }

    $this->_orm_row_raw[$key] = $value;
    $this->_orm_row[$key] = $this->_unmarshal($key, $value);
  }

  public function _marshal($key, $value) {
    $type = static::_orm_coltype($key);
    $is_nullable = static::_orm_struct()->column_nullable($key);
    
    $fieldmarshal = "_marshal_field_{$key}";  
    $typemarshal = "_marshal_type_{$type}";
    if (method_exists($this, $fieldmarshal)) {
      $value = $this->$fieldmarshal($key, $value, $is_nullable);
    }
    else if (method_exists($this, $typemarshal)) {
      $value = $this->$typemarshal($key, $value, $is_nullable);
    }

    return $value;
    
    // $default = '';
    // if ($type == 'int' || $type == 'double') $default = 0;
    // else if ($type == 'datetime') $default = '0000-00-00 00:00:00';
  }

  public function _unmarshal($key, $value) {
    $type = static::_orm_coltype($key);
    $is_nullable = static::_orm_struct()->column_nullable($key);
    
    $fieldmarshal = "_unmarshal_field_{$key}";  
    $typemarshal = "_unmarshal_type_{$type}";
    if (method_exists($this, $fieldmarshal)) {
      $value = $this->$fieldmarshal($key, $value, $is_nullable);
    }
    else if (method_exists($this, $typemarshal)) {
      $value = $this->$typemarshal($key, $value, $is_nullable);
    }

    return $value;
  }

  public function _marshal_type_bool($key, $value, $is_nullable) {
    if ($is_nullable && is_null($value)) return null;
    return $value ? 1 : 0;
  }
  public function _unmarshal_type_bool($key, $value) {
    if (is_null($value)) return null;
    return !!$value;
  }

  public function _marshal_type_int($key, $value, $is_nullable) {
    if ($is_nullable && is_null($value)) return null;
    return intval($value);
  }
  public function _unmarshal_type_int($key, $value) {
    if (is_null($value)) return null;
    return intval($value);
  }

  public function _marshal_type_double($key, $value, $is_nullable) {
    if ($is_nullable && is_null($value)) return null;
    return doubleval($value);
  }
  public function _unmarshal_type_double($key, $value) {
    if (is_null($value)) return null;
    return doubleval($value);
  }

  public function _marshal_type_datetime($key, $value, $is_nullable) {
    if (!$is_nullable && is_null($value)) $value = '0000-00-00 00:00:00';
    if ($value instanceof \DateTime) return $value->format('Y-m-d H:i:s');
    return $value;
  }
  public function _unmarshal_type_datetime($key, $value) {
    if (is_null($value)) return null;
    if ($value) return DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $value;
  }

  public function _marshal_type_string($key, $value, $is_nullable) {
    if ($is_nullable && is_null($value)) return null;
    return strval($value);
  }
  public function _unmarshal_type_string($key, $value) {
    if (is_null($value)) return null;
    return strval($value);
  }

  public static function _orm_colinfo($column, $type) {
    if (! is_array(static::$_orm_columns)) return;
    if (! array_key_exists($column, static::$_orm_columns)) return;

    $info = static::$_orm_columns[$column];
    return $info[$type] ?? null;
  }

  public static function _orm_coltype($column) {
    if ($type = static::_orm_colinfo($column, 'type')) {
      return $type;
    }
    return static::_orm_struct()->column_type($column);
  }

  // -------------- ASSOCIATIONS
  public static function is_assoc($name) { return !! static::_orm_assoc($name); }
  protected static function _orm_assoc($name) {
    if (! array_key_exists($name, static::$_orm_associations)) return null;
    $assoc = static::$_orm_associations[$name];

    if (! isset($assoc['foreign_key'])) {
      throw new MeekroORMException("assocation must have foreign_key");
    }

    $assoc['class_name'] = $assoc['class_name'] ?? $name;
    return $assoc;
  }

  public function assoc($name) {
    if (! static::is_assoc($name)) return null;
    if (! isset($this->_orm_assoc_load[$name])) {
      $this->_orm_assoc_load[$name] = $this->_load_assoc($name);
    }
    
    return $this->_orm_assoc_load[$name];
  }

  protected function _load_assoc($name) {
    $assoc = static::_orm_assoc($name);
    if (! $assoc) {
      throw new MeekroORMException("Unknown assocation: $name");
    }

    $class_name = $assoc['class_name'];
    $foreign_key = $assoc['foreign_key'];
    $primary_key = $class_name::_orm_struct()->primary_key();
    $primary_value = $this->getraw($primary_key);

    if (! is_subclass_of($class_name, __CLASS__)) {
      throw new MeekroORMException(sprintf('%s is not a class that inherits from %s', $class_name, get_class()));
    }

    if ($assoc['type'] == 'belongs_to') {
      return $class_name::Load($this->$foreign_key);
    }
    else if ($assoc['type'] == 'has_one') {
      return $class_name::Search([
        $assoc['foreign_key'] => $primary_value,
      ]);
    }
    else if ($assoc['type'] == 'has_many') {
      return $class_name::Where('%c=%?', $assoc['foreign_key'], $primary_value);
    }
    else {
      throw new Exception("Invalid type for $name association");
    }
  }

  // -------------- CONSTRUCTORS
  public function _load_hash(array $row) {
    $this->_orm_is_fresh = false;
    $this->_orm_assoc_load = [];
    $this->_orm_row_raw = $row;
    $this->_orm_row_orig = $row;
    foreach ($row as $key => $value) {
      $this->_orm_row[$key] = $this->_unmarshal($key, $value);
    }
  }

  public static function LoadFromHash(array $row = []) {
    $class_name = get_called_class();
    $Obj = new $class_name();
    $Obj->_load_hash($row);
    return $Obj;
  }

  public static function Load(...$values) {
    $keys = static::_orm_struct()->primary_keys();
    if (count($values) != count($keys)) {
      throw new Exception(sprintf("Load on %s must be called with %d parameters!", 
        get_called_class(), count($keys)));
    }

    return static::Search(array_combine($keys, $values));
  }

  protected static function _orm_query_from_hash(array $hash, $one, $lock=false) {
    $query = "SELECT * FROM %b WHERE %ha";
    if ($one) $query .= " LIMIT 1";
    if ($lock) $query .= " FOR UPDATE";

    return array($query, static::_orm_tablename(), $hash);
  }

  public static function Search() {
    // infer the table structure first in case we run FOUND_ROWS()
    static::_orm_struct();

    $args = func_get_args();
    if (is_array($args[0])) {
      $opts_default = array('lock' => false);
      $opts = isset($args[1]) && is_array($args[1]) ? $args[1] : array();
      $opts = array_merge($opts_default, $opts);

      $args = static::_orm_query_from_hash($args[0], true, $opts['lock']);
    }

    $row = static::_orm_meekrodb()->queryFirstRow(...$args);
    if (is_array($row)) return static::LoadFromHash($row);
    else return null;
  }

  public static function SearchMany() {
    // infer the table structure first in case we run FOUND_ROWS()
    static::_orm_struct();

    $args = func_get_args();
    if (is_array($args[0])) $args = static::_orm_query_from_hash($args[0], false);
    
    $result = [];
    $rows = static::_orm_meekrodb()->query(...$args);
    if (is_array($rows)) {
      foreach ($rows as $row) {
        $result[] = static::LoadFromHash($row);
      }
    }
    return $result;
  }


  // -------------- DYNAMIC METHODS
  public function __set($key, $value) {
    if (!$this->is_fresh() && static::_orm_struct()->is_primary_key($key)) {
      throw new MeekroORMException("Can't update primary key!");
    }
    else if ($this->has($key)) {
      $this->set($key, $value);
    }
    else {
      $this->$key = $value;
    }
  }

  // return by ref on __get() lets $Obj->var[] = 'array_element' work properly
  public function &__get($key) {
    // return by reference requires temp var
    if (static::is_assoc($key)) {
      $result = $this->assoc($key);
      return $result;
    }
    if (static::_orm_struct()->has($key)) {
      $result = $this->get($key);
      return $result;
    }

    return $this->$key;
  }

  static function _orm_scopes() {
    return [];
  }

  static function _orm_runscope($scope, ...$args) {
    $scopes = static::_orm_scopes();
    if (! is_array($scopes)) {
      throw new MeekroORMException("No scopes available");
    }
    if (! array_key_exists($scope, $scopes)) {
      throw new MeekroORMException("Scope not available: $scope");
    }

    $scope = $scopes[$scope];
    if (! is_callable($scope)) {
      throw new MeekroORMException("Invalid scope: must be anonymous function");
    }

    $Scope = $scope(...$args);
    if (! ($Scope instanceof MeekroORMScope)) {
      throw new MeekroORMException("Invalid scope: must use ClassName::Where()");
    }
    return $Scope;
  }

  static function where(...$args) {
    $Scope = new MeekroORMScope(get_called_class());
    $Scope->where(...$args);
    return $Scope;
  }

  static function scope(...$scopes) {
    $Scope = new MeekroORMScope(get_called_class());
    $Scope->scope(...$scopes);
    return $Scope;
  }

  public function save($run_callbacks=true) {
    $is_fresh = $this->is_fresh();
    $have_committed = false;
    $table = static::_orm_tablename();
    $mdb = static::_orm_meekrodb();
    $mdb->startTransaction();

    try {
      if ($run_callbacks) {
        $fields = $this->_orm_dirty_fields();

        foreach ($fields as $field) {
          $this->_orm_run_callback("_validate_{$field}");
        }
        
        $this->_orm_run_callback('_pre_save', $fields);
        if ($is_fresh) $this->_orm_run_callback('_pre_create', $fields);
        else $this->_orm_run_callback('_pre_update', $fields);
      }
      
      // dirty fields list might change while running the _pre callbacks
      $replace = $this->_orm_dirtyhash();
      $fields = array_keys($replace);

      if ($is_fresh) {
        $mdb->insert($table, $replace);
        $this->_orm_is_fresh = false;

        // for reload() to work below, we need to know what our auto-increment value is
        if ($aifield = static::_orm_struct()->ai_field()) {
          $this->set($aifield, $mdb->insertId());
        }
      }
      else if (count($replace) > 0) {
        $mdb->update($table, $replace, "%l", $this->_where());
      }
      
      // for INSERTs, pick up any default values
      $this->reload();

      if ($run_callbacks) {
        if ($is_fresh) $this->_orm_run_callback('_post_create', $fields);
        else $this->_orm_run_callback('_post_update', $fields);
        $this->_orm_run_callback('_post_save', $fields);
      }
      $mdb->commit();
      $have_committed = true;

    } finally {
      if (! $have_committed) $mdb->rollback();
    }

    if ($run_callbacks) {
      $this->_orm_run_callback('_post_commit', $fields);
    }
  }

  public function reload($lock=false) {
    if ($this->is_fresh()) throw new MeekroORMException("Can't reload unsaved record!");

    $mdb = static::_orm_meekrodb();
    $table = static::_orm_tablename();
    $row = $mdb->queryFirstRow("SELECT * FROM %b WHERE %l LIMIT 1", $table, $this->_where());

    if (! $row) {
      throw new MeekroORMException("Unable to reload(): missing row");
    }
    $this->_load_hash($row);
  }

  public function lock() { $this->reload(true); }

  // TODO: cleanup update(), optionally run pre/post functions?
  public function update($key, $value=null) {
    if (is_array($key)) $hash = $key;
    else $hash = array($key => $value);
    //$dirty_fields = array_keys($hash);

    $this->_orm_row = array_merge($this->_orm_row, $hash);

    if (! $this->is_fresh()) {
      //$this->_orm_run_callback('_pre_save', $dirty_fields);
      //$this->_orm_run_callback('_pre_update', $dirty_fields);

      static::_orm_meekrodb()->update(static::_orm_tablename(), $hash, "%l", $this->_where());
      $this->_orm_row_orig = array_merge($this->_orm_row_orig, $hash);

      //$this->_orm_run_callback('_post_update', $dirty_fields);
      //$this->_orm_run_callback('_post_save', $dirty_fields);
    }
  }

  public function destroy() {
    $mdb = static::_orm_meekrodb();
    $mdb->startTransaction();

    try {
      $this->_orm_run_callback('_pre_destroy');
      $mdb->query("DELETE FROM %b WHERE %l LIMIT 1", static::_orm_tablename(), $this->_where());
      $this->_orm_run_callback('_post_destroy');
      $mdb->commit();
      $have_committed = true;
    } finally {
      if (! $have_committed) $mdb->rollback();
    }
  }

  public function toHash() {
    return $this->_orm_row;
  }

  public function toRawHash() {
    return $this->_orm_row_raw;
  }

  public function __toString() {
    return get_called_class();
  }

}

class MeekroORMTable {
  protected $struct = [];
  protected $table_name;
  protected $class_name;
  
  function __construct($class_name) {
    $this->class_name = $class_name;
    $this->table_name = $class_name::_orm_tablename();
    $this->struct = $this->table_struct();
  }

  function primary_keys() {
    return array_keys(array_filter(
      $this->struct, 
      function($x) { return $x['Key'] == 'PRI'; }
    ));
  }

  function primary_key() {
    return count($this->primary_keys()) == 1 ? $this->primary_keys()[0] : null;
  }

  function is_primary_key($key) {
    return in_array($key, $this->primary_keys());
  }

  function ai_field() {
    $data = array_filter($this->struct, function($x) { return $x['Extra'] == 'auto_increment'; });
    if (! $data) return null;
    $data = array_values($data);
    return $data[0]['Field'];
  }

  function column_type($column) {
    static $typemap = [
      'tinyint' => 'int',
      'smallint' => 'int',
      'mediumint' => 'int',
      'int' => 'int',
      'bigint' => 'int',
      'float' => 'double',
      'double' => 'double',
      'decimal' => 'double',
      'datetime' => 'datetime',
      'timestamp' => 'datetime',
    ];

    if (! $this->has($column)) return;
    $type = strtolower($this->struct[$column]['Type'][0]);
    return $typemap[$type] ?? 'string';
  }

  function column_nullable($column) {
    if (! $this->has($column)) return;
    $type = strtolower($this->struct[$column]['Null']);
    return $type == 'yes';
  }

  function has($column) {
    return array_key_exists($column, $this->struct);
  }

  protected function table_struct() {
    $mdb = $this->class_name::_orm_meekrodb();
    $table = $mdb->query("DESCRIBE %b", $this->table_name);
    $struct = [];
    
    foreach ($table as $row) {
      $row['Type'] = preg_split('/\W+/', $row['Type'], -1, PREG_SPLIT_NO_EMPTY);
      $struct[$row['Field']] = $row;
    }
    return $struct;
  }

}

class MeekroORMScope implements ArrayAccess, Iterator, Countable {
  protected $class_name;
  protected $Where;
  protected $order_by = [];
  protected $limit_offset;
  protected $limit_rowcount;
  protected $Objects;
  protected $position = 0;

  function __construct($class_name) {
    $this->class_name = $class_name;
    $this->Where = new WhereClause('and');
  }

  function where(...$args) {
    $this->Objects = null;
    $this->position = 0;

    $this->Where->add(...$args);
    return $this;
  }

  function order_by(...$items) {
    if (is_array($items[0])) {
      $this->order_by = $items[0];
    }
    else {
      $this->order_by = $items;
    }
    return $this;
  }

  function limit(int $one, int $two=null) {
    if (is_null($two)) {
      $this->limit_rowcount = $one;
    } else {
      $this->limit_offset = $one;
      $this->limit_rowcount = $two;
    }
    return $this;
  }

  function scope($scope, ...$args) {
    $this->Objects = null;
    $this->position = 0;

    $Scope = $this->class_name::_orm_runscope($scope, ...$args);

    if (count($this->Where) > 0) {
      $this->Where->add($Scope->Where);
    } else {
      $this->Where = $Scope->Where;
    }

    if (!is_null($Scope->limit_rowcount)) {
      $this->limit_rowcount = $Scope->limit_rowcount;
      $this->limit_offset = $Scope->limit_offset;
    }
    if ($Scope->order_by) {
      $this->order_by = $Scope->order_by;
    }

    return $this;
  }

  protected function run() {
    $table_name = $this->class_name::_orm_tablename();

    $query = 'SELECT * FROM %b WHERE %l';
    $args = [$table_name, $this->Where];

    if (count($this->order_by) > 0) {
      // array_is_list
      if ($this->order_by == array_values($this->order_by)) {
        $c_string = array_fill(0, count($this->order_by), '%c');
        $query .= ' ORDER BY ' . implode(',', $c_string);
        $args = array_merge($args, array_values($this->order_by));
      }
      else {
        $c_string = [];
        foreach ($this->order_by as $column => $order) {
          $c_string[] = '%c ' . (strtolower($order) == 'desc' ? 'desc' : 'asc');
        }
        $query .= ' ORDER BY ' . implode(',', $c_string);
        $args = array_merge($args, array_keys($this->order_by));
      }
    }

    if (!is_null($this->limit_rowcount)) {
      if (!is_null($this->limit_offset)) {
        $query .= sprintf(' LIMIT %u, %u', $this->limit_offset, $this->limit_rowcount);
      }
      else {
        $query .= sprintf(' LIMIT %u', $this->limit_rowcount);
      }
    }

    $this->Objects = $this->class_name::SearchMany($query, ...$args);
    return $this->Objects;
  }

  protected function run_if_missing() {
    if (is_array($this->Objects)) return;
    return $this->run();
  }

  #[\ReturnTypeWillChange]
  function count() {
    $this->run_if_missing();
    return count($this->Objects);
  }

  // ***** Iterator
  #[\ReturnTypeWillChange]
  function current() {
    $this->run_if_missing();
    return $this->valid() ? $this->Objects[$this->position] : null;
  }
  #[\ReturnTypeWillChange]
  function key() {
    $this->run_if_missing();
    return $this->position;
  }
  #[\ReturnTypeWillChange]
  function next() {
    $this->run_if_missing();
    $this->position++;
  }
  #[\ReturnTypeWillChange]
  function rewind() {
    $this->run_if_missing();
    $this->position = 0;
  }
  #[\ReturnTypeWillChange]
  function valid() {
    $this->run_if_missing();
    return array_key_exists($this->position, $this->Objects);
  }

  // ***** ArrayAccess
  #[\ReturnTypeWillChange]
  function offsetExists($offset) {
    $this->run_if_missing();
    return array_key_exists($offset, $this->Objects);
  }
  #[\ReturnTypeWillChange]
  function offsetGet($offset) {
    $this->run_if_missing();
    return $this->Objects[$offset];
  }
  #[\ReturnTypeWillChange]
  function offsetSet($offset, $value) {
    throw new MeekroORMException("Unable to edit scoped result set");
  }
  #[\ReturnTypeWillChange]
  function offsetUnset($offset) {
    throw new MeekroORMException("Unable to edit scoped result set");
  }
}

class MeekroORMException extends Exception { }

?>