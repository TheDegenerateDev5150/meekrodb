<?php
class TransactionTest extends SimpleTest {
  function test_1_transactions() {
    DB::$nested_transactions = false;
    
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 600, 'Abe');
    
    $depth = DB::startTransaction();
    $this->assert($depth === 1);
    
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 700, 'Abe');
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 800, 'Abe');
    
    $depth = DB::rollback();
    $this->assert($depth === 0);
    
    $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
    $this->assert($age == 600);
  }

  function test_2_transactions() {
    DB::$nested_transactions = true;
    
    $depth = DB::startTransaction();
      $this->assert($depth === 1);
      DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 700, 'Abe');
      
      $depth = DB::startTransaction();
        $this->assert($depth === 2);
        DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 800, 'Abe');
        
        $depth = DB::startTransaction();
          $this->assert($depth === 3);
          $this->assert(DB::transactionDepth() === 3);
          DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 500, 'Abe');
        $depth = DB::commit();
        
        $this->assert($depth === 2);
        
        $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
        $this->assert($age == 500);
        
      $depth = DB::rollback();
      $this->assert($depth === 1);
      
      $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
      $this->assert($age == 700);
    
    $depth = DB::commit();
    $this->assert($depth === 0);
    
    $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
    $this->assert($age == 700);
    
    
    DB::$nested_transactions = false;
  }
  
  function test_3_transactions() {
    DB::$nested_transactions = true;
    
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 600, 'Abe');
    
    DB::startTransaction();
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 700, 'Abe');
    DB::startTransaction();
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 800, 'Abe');
    DB::rollback();
    
    $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
    $this->assert($age == 700);
    
    DB::rollback();
    
    $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
    $this->assert($age == 600);
    
    DB::$nested_transactions = false;
  }
  
  function test_4_transaction_rollback_all() {
    DB::$nested_transactions = true;
    
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 200, 'Abe');
    
    $depth = DB::startTransaction();
    $this->assert($depth === 1);
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 300, 'Abe');
    $depth = DB::startTransaction();
    $this->assert($depth === 2);
    
    DB::query("UPDATE accounts SET %c=%i WHERE username=%s", 'user.age', 400, 'Abe');
    $depth = DB::rollback(true);
    $this->assert($depth === 0);
    
    $age = DB::queryFirstField("SELECT %c FROM accounts WHERE username=%s", 'user.age', 'Abe');
    $this->assert($age == 200);
    
    DB::$nested_transactions = false;
  }
  
}
