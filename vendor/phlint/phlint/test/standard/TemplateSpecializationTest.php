<?php

use \phlint\autoload\Mock as MockAutoload;
use \phlint\Test as PhlintTest;

class TemplateSpecializationTest {

  /**
   * Test that calling a function before its definition causes no issue.
   * @test @internal
   */
  static function unittest_test () {
    PhlintTest::assertNoIssues('
      $x = foo(1);
      function foo ($i) {
        return $i;
      }
    ');
  }

  /**
   * Test that invocations are linked to the correct specializations.
   * @test @internal
   */
  static function unittest_link () {

    PhlintTest::assertNoIssues('

      const X1 = 1;
      const X2 = 2;

      function foo ($x = X1) {
        if ($x === X2)
          $y = $GLOBALS["x"];
      }

      /** @pure */
      function bar () {
        foo();
      }

      bar();

    ');

    PhlintTest::assertNoIssues('

      const X1 = 1;
      const X2 = 2;

      function foo ($x = X1) {
        if ($x === X2)
          $y = $GLOBALS["x"];
      }

      /** @pure */
      function bar () {
        foo();
      }

      /** @pure */
      function baz () {
        foo();
      }

      foo();
      bar();
      baz();

    ');

  }

  /**
   * Test alternative templates.
   * @test @internal
   */
  static function unittest_alternativeTemplates () {
    PhlintTest::assertIssues('
      if (rand(0, 1)) {
        function foo ($bar) {
          $x = 2;
          return $bar + 1;
        }
      } else {
        function foo ($bar) {
          $y = $x;
          return $bar - 1;
        }
      }
      foo(1);
      foo("Hello");
    ', [
      '
        Provided variable *$bar* of type *string* is not compatible in the expression *$bar + 1* on line 4.
          Trace #1: Function *foo("Hello")* specialized for the expression *foo("Hello")* on line 13.
      ',
      'Variable *$x* used before initialized on line 8.',
      '
        Provided variable *$bar* of type *string* is not compatible in the expression *$bar - 1* on line 9.
          Trace #1: Function *foo("Hello")* specialized for the expression *foo("Hello")* on line 13.
      ',
    ]);
  }

  /**
   * Test alternative return types with multiple declarations.
   *
   * @test @internal
   */
  static function unittest_alternativeReturnTypesWithMultipleDeclarations () {
    PhlintTest::assertIssues('
      if (rand(0, 1)) {
        function foo () {
          $bar = 1;
          return $bar;
        }
      } else {
        function foo () {
          $bar = [1];
          return $bar;
        }
      }
      $baz = foo() - 1;
      foreach (foo() as $fun) {}
    ', [
      'Provided expression *foo()* of type *t_int[]* is not compatible in the expression *foo() - 1* on line 12.',
      'Provided symbol *foo()* of type *int* is not compatible in the expression *foreach (foo() as $fun)* on line 13.',
    ]);
  }

  /**
   * Test that no specialization is done for a known signature.
   *
   * @test @internal
   */
  static function unittest_knownSignatureSpecialization () {

    $linter = PhlintTest::create();

    $linter->addSource('
      class A {
        /**
         * @return B
         */
        static function createB () {
          /**
           * The following assert should not be complained about as
           * it is a part of the library code and this function
           * is not being specialized as it has a known signature.
           */
          assert(false);
        }
      }
      class B {
        function foo () {}
      }
    ', true);

    PhlintTest::assertIssues($linter->analyze('
      $b = A::createB();
      $b->foo();
      $b->bar();
    '), [
      'Unable to invoke undefined *B::bar* for the expression *$b->bar()* on line 3.',
    ]);

  }

  /**
   * Test sub-templating.
   *
   * @test @internal
   */
  static function unittest_subTemplating () {
    PhlintTest::assertIssues('
      function foo ($x) {
        return function ($y) use ($x) {
          return $x + $y;
        };
      }
      $intFoo = foo(1);
      $intFoo("world");
      $stringFoo = foo("Hello");
      $stringFoo(2);
    ', [
      '
        Provided variable *$x* of type *string* is not compatible in the expression *$x + $y* on line 3.
          Trace #1: Function *(2) use ("Hello")* specialized for the expression *$stringFoo(2)* on line 9.
      ',
      '
        Provided variable *$x* of type *string* is not compatible in the expression *$x + $y* on line 3.
          Trace #1: Function *foo("Hello")* specialized for the expression *foo("Hello")* on line 8.
      ',
      '
        Provided variable *$y* of type *string* is not compatible in the expression *$x + $y* on line 3.
          Trace #1: Function *("world") use (1)* specialized for the expression *$intFoo("world")* on line 7.
      ',
    ]);
  }

  /**
   * Test sub-template nesting.
   *
   * @test @internal
   */
  static function unittest_subTemplateNesting () {
    PhlintTest::assertIssues('
      function foo ($a) {
        return function ($b) use ($a) {
          return function ($c) use ($a, $b) {
            return function ($d) use ($a, $b, $c) {
              return function ($e) use ($a, $b, $c, $d) {
                return $a + $b + $c + $d + $e;
              };
            };
          };
        };
      }
      $stringFoo = foo("Hello");
      $intFoo = $stringFoo(1);
      $arrayFoo = $intFoo([]);
      $boolFoo = $arrayFoo(false);
      $boolFoo(null);
    ', [
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 6.
          Trace #1: Function *foo("Hello")* specialized for the
            expression *foo("Hello")* on line 12.
      ',
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 6.
          Trace #1: Function *(1) use ("Hello")* specialized for the
            expression *$stringFoo(1)* on line 13.
      ',
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 6.
          Trace #1: Function *( $c) use ("Hello", 1)* specialized for the
            expression *$intFoo([])* on line 14.
      ',
      '
        Provided variable *$d* of type *bool* is not compatible in the expression *$a + $b + $c + $d* on line 6.
          Trace #1: Function *(false) use ("Hello", 1, $c)* specialized for the
            expression *$arrayFoo(false)* on line 15.
      ',
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 6.
          Trace #1: Function *(false) use ("Hello", 1, $c)* specialized for the
            expression *$arrayFoo(false)* on line 15.
      ',
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 6.
          Trace #1: Function *(NULL) use ("Hello", 1, $c, false)* specialized for the expression *$boolFoo(null)* on line 16.
      ',
      '
        Provided variable *$d* of type *bool* is not compatible in the expression *$a + $b + $c + $d* on line 6.
          Trace #1: Function *(NULL) use ("Hello", 1, $c, false)* specialized for the expression *$boolFoo(null)* on line 16.
      ',
      '
        Provided variable *$e* of type *mixed* is not compatible in the expression *$a + $b + $c + $d + $e* on line 6.
          Trace #1: Function *(NULL) use ("Hello", 1, $c, false)* specialized for the expression *$boolFoo(null)* on line 16.
      ',
    ]);
  }

}
