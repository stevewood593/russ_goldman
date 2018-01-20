<?php

use \phlint\Test as PhlintTest;

class ConditionalDefiningTest {

  /**
   * Test that conditional defining is not complaining.
   *
   * @test @internal
   */
  static function unittest_conditionDefining () {
    PhlintTest::assertNoIssues('
      function foo () {}
      if (!function_exists("foo")) {
        function foo() {}
      }
    ');
  }

  /**
   * Test defining a function within a function.
   *
   * @test @internal
   */
  static function unittest_functionWithinFunction () {
    PhlintTest::assertIssues('
      class A {
        static function foo () {
          function foo () {}
        }
      }
      A::foo();
      foo();
      A::bar();
      bar();
    ', [
      'Unable to invoke undefined *A::bar()* on line 8.',
      'Unable to invoke undefined *bar()* on line 9.',
    ]);
  }

}
