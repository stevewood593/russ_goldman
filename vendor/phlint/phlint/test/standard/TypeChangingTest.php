<?php

use \phlint\Test as PhlintTest;

class TypeChangingTest {

  /**
   * Test changing variable types.
   * @test @internal
   */
  static function unittest_changingVariableType () {
    PhlintTest::assertIssues('

      $foo = null;

      $foo->bar();

      $foo = 1;
      $foo -= 1;

      $foo->bar();

      $foo = "abc";
      $foo = substr($foo, 1, 1);

      $foo->bar();

      $foo = false;

      $foo->bar();

    ', [
      'Unable to invoke undefined *mixed::bar* for the expression *$foo->bar()* on line 4.',
      'Unable to invoke undefined *int::bar* for the expression *$foo->bar()* on line 9.',
      'Unable to invoke undefined *string::bar* for the expression *$foo->bar()* on line 14.',
      'Unable to invoke undefined *bool::bar* for the expression *$foo->bar()* on line 18.',
    ]);
  }

  /**
   * This is not a valid test but rather just a blueprint.
   * @test @internal
   */
  static function unittest_conditionallyChangingVariableType () {
    PhlintTest::assertIssues('

      $foo = null;

      $foo->bar();

      if (rand(0, 1)) {
        $foo = 1;
        $foo -= 1;
      }

      $foo->bar();

      if (rand(0, 1)) {
        $foo = "abc";
        $foo = substr($foo, 1, 1);
      }

      $foo->bar();

      if (rand(0, 1)) {
        $foo = false;
      }

      $foo->bar();

    ', [
      'Unable to invoke undefined *mixed::bar* for the expression *$foo->bar()* on line 4.',
      'Unable to invoke undefined *mixed::bar* for the expression *$foo->bar()* on line 11.',
      'Unable to invoke undefined *int::bar* for the expression *$foo->bar()* on line 11.',
      'Unable to invoke undefined *mixed::bar* for the expression *$foo->bar()* on line 18.',
      'Unable to invoke undefined *int::bar* for the expression *$foo->bar()* on line 18.',
      'Unable to invoke undefined *string::bar* for the expression *$foo->bar()* on line 18.',
      'Unable to invoke undefined *mixed::bar* for the expression *$foo->bar()* on line 24.',
      'Unable to invoke undefined *int::bar* for the expression *$foo->bar()* on line 24.',
      'Unable to invoke undefined *string::bar* for the expression *$foo->bar()* on line 24.',
      'Unable to invoke undefined *bool::bar* for the expression *$foo->bar()* on line 24.',
    ]);
  }

  /**
   * Test changing to mixed array.
   * @test @internal
   */
  static function unittest_toMixedArray () {
    PhlintTest::assertNoIssues('
      $foo = "";
      $foo = [];
      $foo += [];
    ');
  }

  /**
   * Value and type mixing test.
   * @test @internal
   */
  static function unittest_valueAndTypeMixing () {
    PhlintTest::assertIssues('

      class A {
        function bar () {}
      }

      $foo = null;

      if (rand(0, 1))
        $foo = new A();

      if (!empty($foo))
        if (!empty($anotherFoo)) {
          $foo->bar();
          $foo->baz();
        }

    ', [
      'Unable to invoke undefined *A::baz* for the expression *$foo->baz()* on line 14.',
    ]);
  }

}
