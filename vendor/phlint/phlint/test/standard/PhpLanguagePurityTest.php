<?php

use \phlint\Test as PhlintTest;

/**
 * These tests deal with purity of PHP function and class specializations.
 * They are also used to provide examples that prove that some functions and classes,
 * which might be believed to be pure, are actually not pure
 * in some of their specializations.
 */
class PhpLanguagePurityTest {

  /**
   * Test `array_diff_assoc` purity.
   * @test @internal
   */
  static function unittest_array_diff_assoc () {

    PhlintTest::assertNoIssues('
      /** @pure */
      function foo ($a) {
        return array_diff_assoc([$a], [$a]);
      }
      foo("2");
    ');

    // @todo: Enable when implemented.
    if (false)
    PhlintTest::assertIssues('
      class A {
        protected $i = 0;
        function __toString () {
          $this->i += 1;
          return $this->i . "!";
        }
      }
      /** @pure */
      function foo ($a) {
        return array_diff_assoc([$a], [$a]);
      }
      foo(new A());
    ', [
      '
        Function *foo* not pure on line 9.
          Cause #1: Passing non-constant $a to isolated function *array_diff_assoc()* on line 10.
          Trace #1: foo(interface:\A $a) specialized for foo(new A())
      ',
    ]);

  }

  /**
   * Test `sort` purity.
   * @test @internal
   */
  static function unittest_sort () {

    PhlintTest::assertNoIssues('
      /** @pure */
      function foo () {
        $a = [3, 2, 1];
        sort($a);
        sort($a, SORT_NUMERIC);
        return $a;
      }
    ');

    PhlintTest::assertIssues('
      /** @pure */
      function foo () {
        $a = [3, 2, 1];
        sort($a, SORT_LOCALE_STRING);
        return $a;
      }
    ', [
      '
        Isolation breach: Depends on the current locale.
          Trace #1: Function *@isolated sort(int[] $array, 5) : bool*
            specialized for the expression *sort($a, SORT_LOCALE_STRING)* on line 4.
      ',
    ]);

    PhlintTest::assertIssues('
      /** @pure */
      function foo ($sort_flags) {
        $a = [3, 2, 1];
        sort($a, $sort_flags);
        return $a;
      }
    ', [
      '
        Isolation breach: Depends on the current locale.
          Trace #1: Function *@isolated sort(int[] $array, int $sort_flags) : bool*
            specialized for the expression *sort($a, $sort_flags)* on line 4.
      ',
    ]);

  }

  /**
   * Test `sprintf` purity.
   * @test @internal
   */
  static function unittest_sprintf () {

    PhlintTest::assertNoIssues('
      /** @pure */
      function foo ($a) {
        return sprintf("%s%s", $a, $a);
      }
      foo("2");
    ');

    // @todo: Enable when implemented.
    if (false)
    PhlintTest::assertIssues('
      class A {
        protected $i = 0;
        function __toString () {
          $this->i += 1;
          return $this->i . "!";
        }
      }
      /** @pure */
      function foo ($a) {
        return sprintf("%s%s", $a, $a);
      }
      foo(new A());
    ', [
      '
        Function *foo* not pure on line 9.
          Cause #1: Passing non-constant $a to isolated function *sprintf()* on line 10.
          Trace #1: foo(interface:\A $a) specialized for foo(new A())
      ',
    ]);

  }

}
