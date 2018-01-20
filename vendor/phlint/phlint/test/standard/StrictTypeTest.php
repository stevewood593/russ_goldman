<?php

use \phlint\Test as PhlintTest;

class StrictTypeTest {

  /**
   * Test implicit conversion to bool with argument declaration.
   *
   * @test @internal
   */
  static function unittest_strictTypeImplicitConversionToBoolWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      declare(strict_types=1);
      function foo (bool $bar) {}
      foo(1.1);
      foo("1.1");
      foo("0.0");
      foo("1.0");
      foo(1);
      foo(2);
      foo("1");
      foo("2");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *bool*
          in the expression *foo(1.1)* on line 3.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.1")* on line 4.
      ',
      '
        Provided argument *"0.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("0.0")* on line 5.
      ',
      '
        Provided argument *"1.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.0")* on line 6.
      ',
      '
        Provided argument *1* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(1)* on line 7.
      ',
      '
        Provided argument *2* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(2)* on line 8.
      ',
      '
        Provided argument *"1"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1")* on line 9.
      ',
      '
        Provided argument *"2"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("2")* on line 10.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("bar")* on line 11.
      ',
    ]);
  }

  /**
   * Test implicit conversion to string with argument declaration.
   *
   * @test @internal
   */
  static function unittest_strictTypeImplicitConversionToStringWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      declare(strict_types=1);
      function foo (string $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *string*
          in the expression *foo(1.1)* on line 3.
      ',
      '
        Provided argument *1* of type *int* is not implicitly convertable to type *string*
          in the expression *foo(1)* on line 5.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *string*
          in the expression *foo(false)* on line 8.
      ',
    ]);
  }

  /**
   * Test implicit conversion to integer with argument declaration.
   *
   * @test @internal
   */
  static function unittest_strictTypeImplicitConversionToIntegerWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      declare(strict_types=1);
      function foo (int $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *int*
          in the expression *foo(1.1)* on line 3.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("1.1")* on line 4.
      ',
      '
        Provided argument *"1"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("1")* on line 6.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("bar")* on line 7.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *int*
          in the expression *foo(false)* on line 8.
      ',
    ]);
  }

  /**
   * Test implicit conversion to float with argument declaration.
   *
   * @test @internal
   */
  static function unittest_strictTypeImplicitConversionToFloatWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      declare(strict_types=1);
      function foo (float $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *float*
          in the expression *foo("1.1")* on line 4.
      ',
      '
        Provided argument *"1"* of type *string* is not implicitly convertable to type *float*
          in the expression *foo("1")* on line 6.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *float*
          in the expression *foo("bar")* on line 7.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *float*
          in the expression *foo(false)* on line 8.
      ',
    ]);
  }

}
