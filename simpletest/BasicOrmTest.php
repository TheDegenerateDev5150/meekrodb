<?php
use Carbon\Carbon;

class Person extends MeekroORM {
  static $_orm_columns = [
    'is_alive' => ['type' => 'bool'],
    'is_male' => ['type' => 'bool'],
    'data' => ['type' => 'json'],
  ];
  static $_orm_associations = [
    'House' => ['type' => 'has_many', 'foreign_key' => 'owner_id'],
    'Soul' => ['type' => 'has_one', 'foreign_key' => 'person_id'],
    'Employer' => ['type' => 'belongs_to', 'foreign_key' => 'employer_id', 'class_name' => 'Company'],
  ];

  static function _orm_scopes() {
    return [
      'living' => function() { return self::where('is_alive=1'); },
      'male' => function() { return self::where('is_male=1'); },
      'female' => function() { return self::where('is_male=0'); },
      'teenager' => function() { return self::where('age>%i AND age<%i', 12, 20); },
      'first_teenager' => function() { return self::scope('teenager')->order_by('id')->limit(1); }
    ];
  }

  static function tsdiff($t1, $t2) {
    $interval = $t1->diff($t2);
    return ($interval->days * 24 * 60 * 60) +
      ($interval->h * 60 * 60) +
      ($interval->i * 60) +
      $interval->s +
      ($interval->f);
  }

  function _pre_save() {
    if ($this->name == 'Kevin') {
      if ($this->age == 53) {
        return false;
      }

      $this->age = 28;
    }
  }
}

class House extends MeekroORM {
  static function _orm_scopes() {
    return [
      'over' => function($over) { return self::where('price >= %i', $over); },
    ];
  }
}

class Soul extends MeekroORM {
  public $tmp;

  static $_orm_columns = [
    'heaven_bound' => ['type' => 'bool'],
  ];
}

class Company extends MeekroORM {
  static $_orm_tablename = 'companies';

}

// TODO: do auto-increment without primary key (and vice-versa) columns still work?
// TODO: can load() table with multiple primary keys?
// TODO: cleanup & test update()

class BasicOrmTest extends SimpleTest {
  function __construct() {
    foreach (DB::tableList() as $table) {
      DB::query("DROP TABLE %b", $table);
    }
  }

  // * can create basic Person objects and save them
  // * can use ::Load() to look up an object with a simple primary key
  // * can use ::Search() to look up an object by string match
  function test_1_basic() {
    DB::query($this->get_sql('create_persons'));
    DB::query($this->get_sql('create_houses'));
    DB::query($this->get_sql('create_souls'));
    DB::query($this->get_sql('create_companies'));

    $Person = new Person();
    $Person->name = 'Nick';
    $Person->age = 23;
    $Person->height = 1.7;
    $Person->favorite_color = 'blue';
    $Person->favorite_animaniacs = 'Yakko';
    $Person->is_alive = true;
    $Person->is_male = true;
    $Person->Save();

    $House = new House();
    $House->owner_id = $Person->id;
    $House->address = '3344 Cedar Road';
    $House->sqft = 1340;
    $House->price = 500;
    $House->Save();

    $House = new House();
    $House->owner_id = $Person->id;
    $House->address = '233 South Wacker Dr';
    $House->sqft = 2250;
    $House->price = 1200;
    $House->Save();

    $Soul = new Soul();
    $Soul->person_id = $Person->id;
    $Soul->heaven_bound = true;
    $Soul->Save();

    $Company = new Company();
    $Company->name = 'Acme Shoe Co';
    $Company->Save();
    $Person->employer_id = $Company->id;
    $Person->Save();

    $Person = new Person();
    $Person->name = 'Ellie';
    $Person->age = 17;
    $Person->height = 1.2;
    $Person->is_alive = true;
    $Person->is_male = false;
    $Person->Save();

    $Person = new Person();
    $Person->name = 'Gavin';
    $Person->age = 15;
    $Person->height = 1.85;
    $Person->is_alive = true;
    $Person->is_male = false;
    $Person->Save();

    $Person = new Person();
    $Person->name = 'Abigail';
    $Person->age = 29;
    $Person->height = 1.2;
    $Person->is_alive = false;
    $Person->is_male = false;
    $Person->Save();

    $Person = Person::Load(1);
    $this->assert($Person->age === 23);

    $Person = Person::Search(['name' => 'Gavin']);
    $this->assert($Person->age === 15);
  }

  // * can save an empty record
  // * can reload() an object to undo pending changes
  function test_2_empty() {
    $Person = new Person();
    $Person->Save();

    $Person = Person::Search(['name' => '']);
    $this->assert($Person->age === 0);
    $Person->age = 1;
    $this->assert($Person->age === 1);
    $Person->reload();
    $this->assert($Person->age === 0);
  }

  // * _pre_save returning false throws exception
  // * _pre_save can add a dirty field
  function test_2_presave() {
    $error = '';
    try {
      $Person = new Person();
      $Person->name = 'Kevin';
      $Person->age = 53;
      $Person->Save();
    } catch (MeekroORMException $e) {
      $error = $e->getMessage();
    }
    $this->assert($error == '_pre_save returned false');

    $Person = new Person();
    $Person->name = 'Kevin';
    $Person->Save();
    $Person->reload();
    $this->assert($Person->age === 28);
  }

  // * can search for Person by int value
  // * bool value is marshalled and unmarshalled correctly
  // * toHash() works
  function test_2_bool() {
    $Person = Person::Search(['age' => 17]);
    $this->assert($Person->name === 'Ellie');
    $this->assert($Person->is_alive === true);
    $this->assert($Person->is_male === false);
    $this->assert($Person->toHash()['is_male'] === false);
    $Person->is_alive = false;
    $Person->Save();

    $Person = Person::Search(['age' => 17]);
    $this->assert($Person->name === 'Ellie');
    $this->assert($Person->is_alive === false);
    $this->assert($Person->is_male === false);
    $Person->is_alive = true;
    $Person->Save();
  }

  // * can use Search() to load entry with extra fields
  // * can use dirtyhash() to see modified properties
  function test_3_extravars() {
    $Person = Person::Search("SELECT *, age+1 AS nextyear FROM persons WHERE age = 17");
    $this->assert($Person->name === 'Ellie');
    $this->assert($Person->is_alive === true);
    $this->assert($Person->nextyear === '18');
    $Person->is_alive = false;

    $dirty = $Person->dirtyhash();
    $this->assert(count($dirty) === 1);
    $this->assert(array_keys($dirty)[0] === 'is_alive');
    $this->assert(array_values($dirty)[0] === 0);
    
    $Person->Save();
    $Person->is_alive = true;
    $Person->Save();
  }

  // * can load and save a timestamp
  function test_3_timestamp() {
    $zerodate = DateTime::createFromFormat('Y-m-d H:i:s', '0000-00-00 00:00:00');

    $Person = Person::Load(1);
    $this->assert($Person->last_happy_moment == $zerodate);
    $Person->last_happy_moment = new DateTime();
    $Person->Save();

    $Person = Person::Load(1);
    $this->assert($Person->last_happy_moment instanceof DateTime);

    $diff = Person::tsdiff(new DateTime(), $Person->last_happy_moment);
    $this->assert($diff <= 1);

    $Person->last_happy_moment = null;
    $Person->Save();
    $Person = Person::Load(1);
    $this->assert($Person->last_happy_moment == $zerodate);
    $Person->last_happy_moment = '0000-00-00 00:00:00';
    $Person->Save();
    $Person = Person::Load(1);
    $this->assert($Person->last_happy_moment == $zerodate);
  }

  // * json type can be used/loaded/saved
  // * json type can be appended to with "var[] = val"
  function test_31_json() {
    $Person = Person::Load(1);
    $Person->data = [1,3,5];
    $Person->Save();

    $Person = Person::Load(1);
    $this->assert($Person->data === [1,3,5]);
    $Person->data[] = 7;
    $Person->Save();

    $Person = Person::Load(1);
    $this->assert($Person->data === [1,3,5,7]);
  }

  // * NULL values can be set to either NULL, or empty string, and those are different
  // * NOT NULL values will be set to empty string (or equivalent) when we try to null them
  function test_4_null() {
    $Person = Person::Search(['name' => 'Nick']);
    $this->assert($Person->favorite_color === 'blue');
    $this->assert($Person->favorite_animaniacs === 'Yakko');
    $this->assert($Person->is_alive === true);
    $this->assert($Person->age === 23);
    $this->assert($Person->height === 1.7);
    $Person->favorite_color = '';
    $Person->favorite_animaniacs = '';
    $Person->is_alive = '';
    $Person->age = '';
    $Person->height = '';
    $Person->Save();
    $Person = Person::Search(['name' => 'Nick']);
    $this->assert($Person->favorite_color === '');
    $this->assert($Person->favorite_animaniacs === '');
    $this->assert($Person->is_alive === false);
    $this->assert($Person->age === 0);
    $this->assert($Person->height === 0.0);
    $Person->favorite_color = null;
    $Person->favorite_animaniacs = null;
    $Person->is_alive = null;
    $Person->age = null;
    $Person->height = null;
    $Person->Save();
    $Person = Person::Search(['name' => 'Nick']);
    $this->assert($Person->favorite_color === null);
    $this->assert($Person->favorite_animaniacs === '');
    $this->assert($Person->is_alive === null);
    $this->assert($Person->age === 0);
    $this->assert($Person->height === 0.0);
    $Person->is_alive = true;
    $Person->favorite_color = 'blue';
    $Person->favorite_animaniacs = 'Yakko';
    $Person->age = 23;
    $Person->height = 1.7;
    $Person->Save();
  }

  function test_5_scope() {
    $Living = Person::scope('living');
    $this->assert(count($Living) === 3);

    $LivingTeens = Person::scope('living')->scope('teenager')->order_by('id');
    $this->assert(count($LivingTeens) === 2);
    $this->assert($LivingTeens[0]->name === 'Ellie');

    $LivingTeens = Person::scope('living')->scope('teenager')->order_by('id')->limit(1);
    $this->assert(count($LivingTeens) === 1);
    $this->assert($LivingTeens[0]->name === 'Ellie');

    $LivingTeens = Person::scope('living')->scope('teenager')->order_by(['id' => 'desc'])->limit(1);
    $this->assert(count($LivingTeens) === 1);
    $this->assert($LivingTeens[0]->name === 'Gavin');
    
    $FirstTeenager = Person::scope('first_teenager');
    $this->assert(count($FirstTeenager) === 1);
    $this->assert($FirstTeenager[0]->name === 'Ellie');
  }

  // * has_many: works with scoping
  // * has_many: no results means empty scope/array
  // * has_one: assocs properly loaded only once
  // * belongs_to: class_name works
  function test_6_assoc() {
    $Person = Person::Search(['name' => 'Nick']);
    $Houses = $Person->House->order_by('price');

    $this->assert(count($Houses) === 2);
    $this->assert($Houses[0]->sqft === 1340);

    $Houses = $Person->House->scope('over', 1000);
    $this->assert(count($Houses) === 1);
    $this->assert($Houses[0]->sqft === 2250);

    $Person2 = Person::Search(['name' => 'Ellie']);
    $this->assert(count($Person2->House) === 0);

    $this->assert($Person->Soul->heaven_bound === true);
    
    $Person->Soul->tmp = true;
    $this->assert($Person->Soul->tmp === true);

    $this->assert($Person->Employer->name === 'Acme Shoe Co');
  }

}