<?php

use \phlint\Test as PhlintTest;

class PHPStandardLibraryTest {

  /**
   * Test ArrayIterator sort signature.
   *
   * @test @internal
   */
  static function unittest_arrayIteratorSignature () {

    PhlintTest::assertIssues('
      function foo (ArrayIterator $bar) {
        $bar->uasort(function ($a, $b) { return strcmp($a, $b); });
        $bar->uksort(function ($a, $b) { return strcmp($a, $b); });
        $bar->uzsort(function ($a, $b) { return strcmp($a, $b); });
      }
    ', [
      'Unable to invoke undefined *ArrayIterator::uzsort* for the expression *$bar->uzsort(( $a,  $b))* on line 4.',
    ]);

  }

  /**
   * Test `date` return value compatibility.
   *
   * @test @internal
   */
  static function unittest_dateReturnValue () {
    PhlintTest::assertIssues('
      dechex(date("Y"));
      dechex(date("Y-m-d"));
    ', [
      '
        Provided argument *date("Y-m-d")* of type *string* is not implicitly convertable to type *int*
          in the expression *dechex(date("Y-m-d"))* on line 2.
      ',
    ]);
  }

  /**
   * Test `substr`.
   *
   * @test @internal
   */
  static function unittest_substrTest () {
    PhlintTest::assertIssues('
      dechex(substr("Date: 2000-01-01", 6, 4));
      dechex(substr("Date: 2000-01-01", 11, 2));
      dechex(substr("Date: 2000-01-01", 10, 3));
      dechex(substr("Date: 2000-01-01", 3, 4));
    ', [
      '
        Provided argument *substr("Date: 2000-01-01", 10, 3)*
          of type *string* is not implicitly convertable to type *int*
          in the expression *dechex(substr("Date: 2000-01-01", 10, 3))* on line 3.
      ',
      '
        Provided argument *substr("Date: 2000-01-01", 3, 4)*
          of type *string* is not implicitly convertable to type *int*
          in the expression *dechex(substr("Date: 2000-01-01", 3, 4))* on line 4.
      ',
    ]);
  }

}
