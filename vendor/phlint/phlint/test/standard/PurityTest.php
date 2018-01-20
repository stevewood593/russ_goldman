<?php

use \phlint\Test as PhlintTest;

class PurityTest {

  /**
   * Test enforcement of purity.
   * @test @internal
   */
  static function unittest_test () {

    PhlintTest::assertNoIssues('
      function foo () {
        bar();
      }
      function bar () {
        $x = $GLOBALS["x"];
        static $i = 0;
      }
    ');

    PhlintTest::assertNoIssues('
      /** @pure */
      function foo () {
        return bar();
      }
      function bar () {
        return 2;
      }
    ');

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        static $i = 0;
      }
      foo();
    ', [
      'Isolation breach: Declaring static variable *$i* on line 3.',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        return __dir__ . __file__ . __line__;
      }
    ', [
      'Isolation breach: Using magic constant *__DIR__* on line 3.',
      'Isolation breach: Using magic constant *__FILE__* on line 3.',
      'Isolation breach: Using magic constant *__LINE__* on line 3.',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        static $i = 0;
        $i += 1;
        return $i;
      }
    ', [
      'Isolation breach: Declaring static variable *$i* on line 3.',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        bar();
      }
      function bar () {
        baz();
      }
      function baz () {
        $x = $GLOBALS["x"];
      }
    ', [
      '
        Isolation breach: Accessing superglobal *$GLOBALS* on line 9.
          Trace #1: Function *@isolated baz()* specialized for the expression *baz()* on line 6.
          Trace #2: Function *@isolated bar()* specialized for the expression *bar()* on line 3.
      ',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        return A::$i + 1;
      }
      class A {
        static $i = 0;
      }
    ', [
      'Isolation breach: Accessing global variable *A::$i* on line 3.',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        setlocale(LC_ALL, "en_GB");
        $x = [5, 3, 1, 2, 4];
        usort($x, function ($a, $b) {
          return $a - $b;
        });
        return substr(implode("", $x), 2, 2);
      }
    ', [
      '
        Isolation breach: Modifies global state.
          Trace #1: Function *@isolated setlocale(0, "en_GB", string $__variadic) : string*
            specialized for the expression *setlocale(LC_ALL, "en_GB")* on line 3.
      ',
    ]);

  }

}
