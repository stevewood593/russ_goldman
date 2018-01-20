<?php

use \phlint\Test as PhlintTest;

class EvaluateAssertsTest {

  /**
   * Test parameter assertions.
   * @test @internal
   */
  static function unittest_parametersAssertations () {
    PhlintTest::assertIssues('
      function foo ($a, $b) {
        assert(is_numeric($a));
        assert(is_numeric($b));
      }
      foo(1, "a");
    ', [
      '
        Assertion failure for the expression *assert(is_numeric($b))* on line 3.
          Trace #1: Function *foo(1, "a")* specialized for the expression *foo(1, "a")* on line 5.
      ',
    ]);
  }

  /**
   * Test `class_exists` argument type.
   * @test @internal
   */
  static function unittest_classExistsAssertationsArgumentType () {
    PhlintTest::assertIssues('
      assert(class_exists(null));
      assert(!class_exists(null));
    ', [
      'Assertion failure for the expression *assert(class_exists(null))* on line 1.',
    ]);
  }

  /**
   * Test `class_exists` assertions.
   * @test @internal
   */
  static function unittest_classExistsAssertations () {
    PhlintTest::assertIssues('
      namespace A {
        class B {}
      }
      namespace C {
        use \A\B as Y;
        assert(class_exists(X::class));
        assert(class_exists(Y::class));
      }
    ', [
      'Assertion failure for the expression *assert(class_exists(X::class))* on line 6.',
    ]);
  }

  /**
   * Test `class_exists` assertions in combination with
   * value based template specialization.
   * @test @internal
   */
  static function unittest_classExistsAssertationsWithValueSpecialization () {
    PhlintTest::assertIssues('
      namespace A {
        class B {}
      }
      namespace C {
        class D {
          function foo ($bar) {
            assert(class_exists($bar));
          }
        }
      }
      namespace {
        use \A\B;
        \C\D::foo(B::class);
        \C\D::foo(E::class);
      }
    ', [
      '
        Assertion failure for the expression *assert(class_exists($bar))* on line 7.
          Trace #1: Method *function foo("E")* specialized for the expression *\C\D::foo(E::class)* on line 14.
      ',
    ]);
  }

}
