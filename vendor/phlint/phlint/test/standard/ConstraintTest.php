<?php

use \phlint\Test as PhlintTest;

class ConstraintTest {

  /**
   * Test constraint attribute.
   * @test @internal
   */
  static function unittest_constraintAttribute () {
    PhlintTest::assertIssues('
      $i = 0;
      /**
       * @constraint(is_numeric($a))
       * @constraint(is_numeric($c))
       */
      function foo ($a, /** @constraint(is_numeric($b)) */ $b, $c) {
        return $a + $b + $c;
      }
      foo(1, 2, 3);
      foo("a", "b", "c");
    ', [
      '
        Constraint *@constraint(is_numeric($a))* failed for the function *foo("a", "b", "c")* on line 3.
          Trace #1: Function *foo("a", "b", "c")* specialized for the expression *foo("a", "b", "c")* on line 10.
      ',
      '
        Constraint *@constraint(is_numeric($c))* failed for the function *foo("a", "b", "c")* on line 4.
          Trace #1: Function *foo("a", "b", "c")* specialized for the expression *foo("a", "b", "c")* on line 10.
      ',
      '
        Constraint *@constraint(is_numeric($b))* failed for the function *foo("a", "b", "c")* on line 6.
          Trace #1: Function *foo("a", "b", "c")* specialized for the expression *foo("a", "b", "c")* on line 10.
      ',
    ]);
  }

  /**
   * Test parameter constraint.
   * @test @internal
   */
  static function unittest_parameterConstraint () {
    PhlintTest::assertIssues('
      function foo ($obj) {
        return get_class($obj);
      }
      foo(2);
      foo("Hello world");
      foo(new stdClass());
    ', [
      '
        Constraint *@param object $object* failed for the function *get_class(2) : string*.
          Trace #1: Function *get_class(2) : string* specialized for the expression *get_class($obj)* on line 2.
          Trace #2: Function *foo(2)* specialized for the expression *foo(2)* on line 4.
      ',
      '
        Constraint *@param object $object* failed for the function *get_class("Hello world") : string*.
          Trace #1: Function *get_class("Hello world") : string* specialized for the expression *get_class($obj)* on line 2.
          Trace #2: Function *foo("Hello world")* specialized for the expression *foo("Hello world")* on line 5.
      ',
    ]);
  }

  /**
   * Regression test for fully qualified symbol object constraint.
   *
   * Regression test for the issue:
   *   Constraint *@param object $object* failed for the function *get_class(a\B $object) : string*.
   *     Trace #1: Function *get_class(a\B $object) : string*
   *       specialized for the expression *get_class(new \a\B())* on line 3.
   *
   * @test @internal
   */
  static function unittest_fullyQualifiedSymbolObjectConstraint () {
    PhlintTest::assertNoIssues('
      namespace a {
        class B {}
        get_class(new \a\B());
      }
    ');
  }

}
