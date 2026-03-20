<?php
class BoolRecord extends MeekroORM {
  static $_tablename = 'bool_test';
}

class PgsqlBoolTest extends SimpleTest {
  function __construct() {
    DB::$param_char = ':';
    BoolRecord::_orm_struct_reset();
  }

  function skip() {
    if ($this->db_type != 'pgsql') return "boolean type test is pgsql-only";
  }

  function test_01_create_table() {
    DB::query("DROP TABLE IF EXISTS bool_test");
    DB::query($this->get_sql('create_bool_test'));
  }

  function test_02_insert_and_retrieve() {
    DB::insert('bool_test', array('label' => 'yes', 'flag' => true, 'nullable_flag' => true));
    DB::insert('bool_test', array('label' => 'no', 'flag' => false, 'nullable_flag' => false));
    DB::insert('bool_test', array('label' => 'null', 'flag' => false, 'nullable_flag' => null));

    $rows = DB::query("SELECT * FROM bool_test ORDER BY id");
    $this->assert(count($rows) === 3);

    // PostgreSQL PDO returns booleans as '1' / '0'
    $yes = $rows[0];
    $this->assert($yes['label'] === 'yes');
    $this->assert($yes['flag'] === '1');
    $this->assert($yes['nullable_flag'] === '1');

    $no = $rows[1];
    $this->assert($no['label'] === 'no');
    $this->assert($no['flag'] === '0');
    $this->assert($no['nullable_flag'] === '0');

    $null = $rows[2];
    $this->assert($null['label'] === 'null');
    $this->assert($null['flag'] === '0');
    $this->assert($null['nullable_flag'] === null);
  }

  function test_03_query_with_bool_literal() {
    // Insert using explicit SQL boolean literals via :l
    DB::query("INSERT INTO bool_test (label, flag, nullable_flag) VALUES (:s, :l, :l)", 'literal_true', 'TRUE', 'FALSE');
    $row = DB::queryFirstRow("SELECT * FROM bool_test WHERE label = :s", 'literal_true');
    $this->assert($row['flag'] === '1');
    $this->assert($row['nullable_flag'] === '0');
  }

  function test_04_where_clause_with_bool() {
    $rows = DB::query("SELECT * FROM bool_test WHERE flag = TRUE ORDER BY id");
    // 'yes' and 'literal_true' rows have flag = TRUE
    $this->assert(count($rows) === 2);
    foreach ($rows as $row) {
      $this->assert($row['flag'] === '1');
    }

    $rows = DB::query("SELECT * FROM bool_test WHERE flag = FALSE ORDER BY id");
    // 'no' and 'null' rows have flag = FALSE
    $this->assert(count($rows) === 2);
    foreach ($rows as $row) {
      $this->assert($row['flag'] === '0');
    }
  }

  function test_05_null_bool() {
    $row = DB::queryFirstRow("SELECT * FROM bool_test WHERE nullable_flag IS NULL");
    $this->assert($row !== null);
    $this->assert($row['label'] === 'null');
    $this->assert($row['nullable_flag'] === null);

    $rows = DB::query("SELECT * FROM bool_test WHERE nullable_flag IS NOT NULL ORDER BY id");
    $this->assert(count($rows) === 3); // yes, no, literal_true
  }

  function test_06_update_bool() {
    DB::update('bool_test', array('flag' => true), "label = :s", 'no');
    $row = DB::queryFirstRow("SELECT * FROM bool_test WHERE label = :s", 'no');
    $this->assert($row['flag'] === '1');

    // restore
    DB::update('bool_test', array('flag' => false), "label = :s", 'no');
    $row = DB::queryFirstRow("SELECT * FROM bool_test WHERE label = :s", 'no');
    $this->assert($row['flag'] === '0');
  }

  function test_07_queryFirstField_bool() {
    $flag = DB::queryFirstField("SELECT flag FROM bool_test WHERE label = :s", 'yes');
    $this->assert($flag === '1');

    $flag = DB::queryFirstField("SELECT flag FROM bool_test WHERE label = :s", 'no');
    $this->assert($flag === '0');

    $flag = DB::queryFirstField("SELECT nullable_flag FROM bool_test WHERE label = :s", 'null');
    $this->assert($flag === null);
  }

  // ORM tests: bool columns are marshalled to PHP true/false (not '1'/'0')

  function test_08_orm_save_and_load() {
    $r = new BoolRecord();
    $r->label = 'orm_yes';
    $r->flag = true;
    $r->nullable_flag = true;
    $r->Save();
    $id = $r->id;

    $loaded = BoolRecord::Search(['id' => $id, 'flag' => true]);
    $this->assert($loaded->flag === true);
    $this->assert($loaded->nullable_flag === true);
    $this->assert($loaded->label === 'orm_yes');
  }

  function test_09_orm_false_and_null() {
    $r = new BoolRecord();
    $r->label = 'orm_no';
    $r->flag = false;
    $r->nullable_flag = null;
    $r->Save();
    $id = $r->id;

    $loaded = BoolRecord::Load($id);
    $this->assert($loaded->flag === false);
    $this->assert($loaded->nullable_flag === null);
  }

  function test_10_orm_update_bool() {
    $r = BoolRecord::Search(['label' => 'orm_yes']);
    $this->assert($r->flag === true);

    $r->flag = false;
    $r->Save();

    $r = BoolRecord::Search(['label' => 'orm_yes']);
    $this->assert($r->flag === false);

    // restore
    $r->update('flag', true);
    $r = BoolRecord::Search(['label' => 'orm_yes']);
    $this->assert($r->flag === true);
  }

  function test_11_orm_search_many() {
    $all = BoolRecord::SearchMany(['flag' => true]);
    foreach ($all as $r) {
      $this->assert($r->flag === true);
    }

    $all = BoolRecord::SearchMany(['flag' => false]);
    foreach ($all as $r) {
      $this->assert($r->flag === false);
    }
  }
}
