<?php

use \phlint\Test as PhlintTest;

class SanityTest {

  /**
   * Sanity test of some edge case situations to make sure
   * they don't cause troubles.
   *
   * @test @internal
   */
  static function unittest_sanityTest () {

    PhlintTest::assertIssues('
      function f () {
        $x =
      }
    ', [
      'Parse error: Syntax error, unexpected \'}\' on line 3.',
    ]);

    PhlintTest::assertNoIssues('

      function f () {
        if (rand(0, 1))
          $x = 0;
        else
          $o = function () {};
      }

      function f2 () {}

      class C {
        function m () {}
      }

      interface I {
        function f ();
      }

    ');
  }

  /**
   * Test that code doesn't break on empty `list` item.
   * @see http://www.php.net/manual/en/function.list.p
   * @test @internal
   */
  static function unittest_emptyListVariable () {
    PhlintTest::assertNoIssues('
      list(, $foo) = explode(" ", "Hello world");
    ');
  }

  /**
   * Test that code doesn't break on empty `return`.
   * @see http://php.net/manual/en/function.return.php
   * @test @internal
   */
  static function unittest_emptyReturn () {
    PhlintTest::assertNoIssues('
      function foo () {
        return;
      }
    ');
  }

  /**
   * Test that code doesn't break on empty ternary operator condition.
   * @see http://ie1.php.net/manual/en/language.operators.comparison.php#language.operators.comparison.ternary
   * @test @internal
   */
  static function unittest_emptyTernaryCondition () {
    PhlintTest::assertNoIssues('
      function foo () {
        $x = true ?: false;
      }
    ');
  }

  /**
   * Test that repeating traces do not hand or take unreasonable amount of time.
   *
   * @test @internal
   */
  static function unittest_repeatingTraceNoHand () {
    PhlintTest::create()->analyze('
      function foo ($a, $b) {
        foo("a");
        foo("b");
        foo($a, "a");
        foo($a, "b");
        return $a + $b + 1;
      }
    ');
  }

  /**
   * Test that inference does not happen multiple times causing impossible scenarios.
   *
   * Regression test for the issue:
   *   Provided argument *$bar* of type *array* is not compatible in the expression *is_array($bar)* on line 4.
   *
   * @test @internal
   */
  static function unittest_multiInferenceProtectionTest () {

    $linter = PhlintTest::create();

    $linter->addSource('
      class A {}
    ');

    PhlintTest::assertNoIssues($linter->analyze('
      function foo () {
        $bar = "";
        if (!empty($bar))
          $bar = is_array($bar) ? array_fill(0, 1) : "";
      }
    '));

  }

}
