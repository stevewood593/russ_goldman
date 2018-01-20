<?php

use \phlint\Test as PhlintTest;

class ExecutionBranchEffectTest {

  /**
   * Test conditional guarantees barrier.
   * @test @internal
   */
  static function unittest_conditionGuaranteesBarrier () {

    PhlintTest::assertNoIssues('
      function f () {
        $list = isset($undefinedVariable) ? [1, 2] : [];
        foreach ($list as $listItem)
          $result = $listItem;
        if (empty($result))
          throw new \Exception("No result");
        return $result;
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        $list = isset($undefinedVariable) ? [1, 2] : [];
        foreach ($list as $listItem)
          $result = $listItem;
        if (empty($result["x"]))
          throw new \Exception("No result");
        return $result["y"];
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        $list = isset($undefinedVariable) ? [1, 2] : [];
        foreach ($list as $listItem)
          $result = $listItem;
        if (!isset($result))
          throw new \Exception("No result");
        return $result;
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        $list = isset($undefinedVariable) ? [1, 2] : [];
        foreach ($list as $listItem)
          $result = $listItem;
        if (!isset($result["x"]))
          throw new \Exception("No result");
        return $result["y"];
      }
    ');

  }

  /**
   * Regression test for the issues:
   *   Unable to invoke undefined *mixed::foo* for the expression *$a->foo()* on line 12.
   *   Unable to invoke undefined *mixed::bar* for the expression *$a->bar()* on line 13.
   *
   * @test @internal
   */
  static function unittest_valueBarrier () {
    PhlintTest::assertIssues('
      class A {
        static function create () {
          return rand(0, 1) ? new A() : null;
        }
        function foo () {}
      }
      $a = A::create();
      $a->foo();
      $a->bar();
      if (!$a)
        throw new Exception("No A!");
      $a->foo();
      $a->bar();
    ', [
      'Unable to invoke undefined *mixed::foo* for the expression *$a->foo()* on line 8.',
      'Unable to invoke undefined *mixed::bar* for the expression *$a->bar()* on line 9.',
      'Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 9.',
      'Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 13.',
    ]);
  }

  /**
   * Regression test for the issues:
   *   Unable to invoke undefined *mixed::baz* for the expression *$foo->baz()* on line 10.
   *   Unable to invoke undefined *mixed::fun* for the expression *$foo->fun()* on line 11.
   *
   * @test @internal
   */
  static function unittest_deterministicType () {
    PhlintTest::assertIssues('
      class A {
        function baz () {}
      }
      function bar () {
        return new A();
      }
      $foo = rand(0, 1) ? bar() : null;
      if (!$foo)
        $foo = bar();
      $foo->baz();
      $foo->fun();
    ', [
      'Unable to invoke undefined *A::fun* for the expression *$foo->fun()* on line 11.',
    ]);
  }

  /**
   * Regression test for various implementation issues.
   *
   * @test @internal
   */
  static function unittest_instaceofVariable () {
    PhlintTest::assertNoIssues('
      $foo = null;
      $bar = "";
      if ($foo instanceof $bar) {}
    ');
  }

}
