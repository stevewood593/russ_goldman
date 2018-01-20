<?php

use \phlint\Test as PhlintTest;

class TypeHintingTest {

  /**
   * Hinting a type that does not exist.
   * @test @internal
   */
  static function unittest_hintingNonExistingType () {
    PhlintTest::assertIssues('
      function foo () : resource {}
      foo();
    ', [
      'Type declaration requires undefined *resource* on line 1.',
    ]);
  }

  /**
   * Test return type phpDoc hinting.
   *
   * Regression test for the issue:
   *   Provided argument *foo()* of type *a* is not compatible in the expression *bar(foo())* on line 19.
   *
   * @test @internal
   */
  static function unittest_returnTypePhpDocHinting () {

    PhlintTest::assertNoIssues('

      namespace a {
        class A {}
      }

      namespace b {

        use \a\A;

        /**
         * @return A
         */
        function foo () {
          return new A();
        }

        function bar (A $a) {}

        bar(foo());

      }

    ');

  }

  /**
   * Test return type phpDoc hinting in case of template specialization.
   *
   * Regression test for the issue:
   *   Provided argument *foo(0)* of type ** is not compatible in the expression *bar(foo(0))* on line 19.
   *
   * @test @internal
   */
  static function unittest_returnTypePhpDocHintingTemplateSpecialization () {

    PhlintTest::assertNoIssues('

      namespace a {
        class A {}
      }

      namespace b {

        use \a\A;

        /**
         * @return A
         */
        function foo ($x) {
          return new A();
        }

        function bar (A $a) {}

        bar(foo(0));

      }

    ');

  }

}
