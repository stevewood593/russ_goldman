<?php

use \phlint\Test as PhlintTest;

class OperatorCompatibilityTest {

  /**
   * Foreach operator test.
   *
   * @test @internal
   */
  static function unittest_foreachOperator () {
    PhlintTest::assertIssues('

      class A {}

      foreach ([] as $item) {}
      foreach (0 as $item) {}
      foreach ("" as $item) {}
      foreach (false as $item) {}
      foreach (null as $item) {}
      foreach (new ArrayObject() as $item) {}
      foreach (new A() as $item) {}

    ', [
      '
        Provided symbol *0* of type *int* is not compatible
          in the expression *foreach (0 as $item)* on line 5.
      ',
      '
        Provided symbol *""* of type *string* is not compatible
          in the expression *foreach ("" as $item)* on line 6.
      ',
      '
        Provided symbol *false* of type *bool* is not compatible
          in the expression *foreach (false as $item)* on line 7.
      ',
      '
        Provided symbol *null* of type ** is not compatible
          in the expression *foreach (null as $item)* on line 8.
      ',
      '
        Provided symbol *new A()* of type *A* is not compatible
          in the expression *foreach (new A() as $item)* on line 10.
      ',
    ]);
  }

}
