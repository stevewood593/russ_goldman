<?php

use \phlint\Test as PhlintTest;

class IsolationTest {

  /** @test @internal */
  static function unittest_test () {

    PhlintTest::assertNoIssues('
      function foo () {
        $x = $GLOBALS["x"];
      }
    ');

    PhlintTest::assertIssues('
      /** @isolated */
      function foo () {
        $x = $GLOBALS["x"];
      }
    ', [
      'Isolation breach: Accessing superglobal *$GLOBALS* on line 3.',
    ]);

  }

  /**
   * Test isolation inference.
   *
   * @test @internal
   */
  static function unittest_isolationInference () {
    PhlintTest::assertIssues('
      function foo () {
        $x = $GLOBALS["x"];
      }
      function bar () {
        foo();
      }
      /** @isolated */
      function baz () {
        bar();
      }
    ', [
      '
        Isolation breach: Accessing superglobal *$GLOBALS* on line 2.
          Trace #1: Function *@isolated foo()* specialized for the expression *foo()* on line 5.
          Trace #2: Function *@isolated bar()* specialized for the expression *bar()* on line 9.
      ',
    ]);
  }

}
