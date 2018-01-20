<?php

use \phlint\Test as PhlintTest;

class DynamicClassConstructionTest {

  /**
   * Test construct from value.
   *
   * @test @internal
   */
  static function unittest_constructFromValue () {
    PhlintTest::assertIssues('
      class A {
        function bar () {}
      }
      function foo () {
        $a = "A";
        return new $a();
      }
      $a = foo();
      $a->bar();
      $a->baz();
    ', [
      'Unable to invoke undefined *A::baz* for the expression *$a->baz()* on line 10.',
    ]);
  }

  /**
   * Test fully qualified construct from value.
   *
   * @test @internal
   */
  static function unittest_fullyQualifiedConstructFromValue () {
    PhlintTest::assertIssues('
      class A {
        function bar () {}
      }
      function foo () {
        $a = "\A";
        return new $a();
      }
      $a = foo();
      $a->bar();
      $a->baz();
    ', [
      'Unable to invoke undefined *A::baz* for the expression *$a->baz()* on line 10.',
    ]);
  }

}
