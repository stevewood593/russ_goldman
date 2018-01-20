<?php

use \phlint\Test as PhlintTest;

class TypeDeclarationTest {

  /**
   * Referencing a type that does not exist.
   * @test @internal
   */
  static function unittest_test () {
    PhlintTest::assertIssues('
      function foo (integer $i) : resource {}
      foo();
    ', [
      'Type declaration requires undefined *integer* on line 1.',
      'Type declaration requires undefined *resource* on line 1.',
    ]);
  }

  /**
   * Test a variable type declaration.
   * @test @internal
   */
  static function unittest_variableTypeDeclaration () {
    PhlintTest::assertIssues('

      class A {
        function foo () {}
      }

      function foo ($x) {
        /** @var A $a */
        $a = $x["obj"];
        $a->foo();
        $a->bar();
      }

    ', [
      'Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 10.',
    ]);
  }

  /**
   * Test a variable type declaration when assignment is done from a
   * declared object through ArrayAccess.
   * @test @internal
   */
  static function unittest_variableTypeDeclarationFromArrayAccess () {
    PhlintTest::assertIssues('

      class A {
        function foo () {}
      }

      function foo (ArrayObject $x) {
        /** @var A */
        $a = $x["obj"];
        $a->foo();
        $a->bar();
      }

    ', [
      'Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 10.',
    ]);
  }

  /**
   * Test a relative variable type declaration.
   * @test @internal
   */
  static function unittest_relativeVariableTypeDeclaration () {
    PhlintTest::assertIssues('

      namespace a\b\c {
        class D {
          function foo () {}
        }
      }

      namespace {
        use a\b;
        function foo ($x) {
          /** @var b\c\D $a */
          $a = $x["obj"];
          $a->foo();
          $a->bar();
        }
      }

    ', [
      'Unable to invoke undefined *a\b\c\D::bar* for the expression *$a->bar()* on line 14.',
    ]);
  }


  /**
   * Test declaring a type for array key fetch.
   * @test @internal
   */
  static function unittest_arrayKeyFetch () {
    PhlintTest::assertIssues('

      class A {
        function bar () {}
      }

      class B {
        function baz () {}
      }

      function foo ($x) {
        $x["b"] = new B();

        /** @var A */
        $o = $x["a"];
        $o->foo();
        $o->bar();
      }

    ', [
      'Unable to invoke undefined *A::foo* for the expression *$o->foo()* on line 15.',
    ]);
  }

  /**
   * Test phpdoc declared types against the real types.
   *
   * @test @internal
   */
  static function unittest_selfKeywordInference () {
    PhlintTest::assertIssues('
      /**
       * @param string|boolean $a
       * @param int $b
       * @return bool
       */
      function foo ($a, $b) {
        return $a + $b;
      }
    ', [
      'Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 7.',
    ]);
  }

}
