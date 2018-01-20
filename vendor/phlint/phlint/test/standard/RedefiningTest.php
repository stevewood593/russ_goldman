<?php

use \phlint\Test as PhlintTest;

class RedefiningTest {

  /**
   * Test redefining.
   *
   * @test @internal
   */
  static function unittest_redefining () {

    $linter = PhlintTest::create();

    $linter->addSource('
      function x () {}
    ');

    $linter->addSource('
      function x () {}
    ');

    PhlintTest::assertIssues($linter->analyze('
      x();
    '), [
      'Having multiple definitions for *x* is prohibited on line 1.',
    ]);


    PhlintTest::assertNoIssues('

      class C {
        use T;
        function f () {}
      }

      class C2 {
        use T2;
        function f () {}
      }

      trait T {
        function f () {}
      }

      trait T2 {
        function f () {}
      }

      function f () {}

    ');

  }

  /**
   * Test redefining class constants.
   *
   * @test @internal
   */
  static function unittest_redefiningClassConstants () {
    PhlintTest::assertIssues('

      class A {
        const C1 = 1;
        const C2 = 2;
      }

      class B {
        const C2 = 3;
        const C2 = 4;
      }

    ', [
      'Having multiple definitions for *C2* is prohibited on line 8.',
      'Having multiple definitions for *C2* is prohibited on line 9.',
    ]);
  }

}
