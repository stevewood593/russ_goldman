<?php

use \phlint\Test as PhlintTest;

class ConditionalGuaranteeTest {

  /**
   * Test condition guarantees.
   *
   * @test @internal
   */
  static function unittest_conditionGuarantees () {

    PhlintTest::assertNoIssues('
      function f () {
        if (isset($a))
          $b = $a;
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        if (isset($a))
          if (!isset($undefinedVariable))
            $b = $a;
      }
    ');

    PhlintTest::assertIssues('
      function f () {
        if (isset($a)) {}
        $b = $a;
      }
    ', [
      'Variable *$a* used before initialized on line 3.',
    ]);

    PhlintTest::assertIssues('
      function f () {
        if (isset($a)) {}
        if (!isset($undefinedVariable)) {}
        $b = $a;
      }
    ', [
      'Variable *$a* used before initialized on line 4.',
    ]);

    PhlintTest::assertNoIssues('
      function f () {
        if (isset($a)) {
          if (isset($a)) {}
          if (!isset($undefinedVariable))
            $b = $a;
        }
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        if (rand(0, 1))
          $a = 1;
        if (isset($a))
          $b = $a;
      }
    ');

    PhlintTest::assertIssues('
      function f () {
        if (!isset($a))
          $b = $a;
      }
    ', [
      'Variable *$a* used before initialized on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      function f () {
        if (rand(0, 1))
          $a = 1;
        $b = !empty($a) ? $a : 0;
        $c = isset($a) ? $a : 0;
      }
    ');

    PhlintTest::assertIssues('
      function f () {
        if (rand(0, 1))
          $a = 1;
        $b = true ? $a : 0;
      }
    ', [
      'Variable *$a* used before initialized on line 4.',
    ]);

  }

  /**
   * Test conditional guarantee interface concept.
   * @test @internal
   */
  static function unittest_concept () {

    PhlintTest::assertNoIssues('
      class A {
        function foo () {}
      }
      class B {
        function bar () {}
      }
      function f () {
        $foo = "a";
        $a = new A();
        if ($a instanceof B)
          $a->bar();
        elseif (isset($undefinedVariable))
          $a->foo();
        else
          $a->foo();
        $a->foo();
      }
    ');

    PhlintTest::assertIssues('
      function bar ($y) {
        return $y + 1;
      }
      function foo ($x = 0) {
        if ($x instanceof \DateTimeInterface)
          $x = $x->format("U");
        elseif (!is_numeric($x))
          $x = "0";
        bar($x);
      }
    ', [
      '
        Provided variable *$y* of type *string* is not compatible in the expression *$y + 1* on line 2.
          Trace #1: Function *bar(0|\'0\')* specialized for the expression *bar($x)* on line 9.
      ',
    ]);

  }

  /**
   * Test nested conditional guarantee.
   * @test @internal
   */
  static function unittest_nested () {

    PhlintTest::assertIssues('

      class A {
        function foo () {}
      }

      class B {
        function bar () {}
      }

      class C {
        function baz () {}
      }

      function foo ($a) {

        bar($a);

        if ($a instanceof B) {
          bar($a);
          #if (is_int($a))
          #  $a += 1;
        } elseif ($a instanceof C) {
          bar($a);
          #if (is_int($a))
          #  $a += 1;
        } else {
          bar($a);
          #if (is_int($a))
          #  $a += 1;
        }

        bar($a);

      }

      function bar ($a) {
        $a->foo();
        $a->bar();
        $a->baz();
      }

      foo(new A());

    ', [
      '
        Unable to invoke undefined *B::foo* for the expression *$a->foo()* on line 37.
          Trace #1: Function *bar(B $a)* specialized for the expression *bar($a)* on line 19.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$a->baz()* on line 39.
          Trace #1: Function *bar(B $a)* specialized for the expression *bar($a)* on line 19.
      ',
      '
        Unable to invoke undefined *C::foo* for the expression *$a->foo()* on line 37.
          Trace #1: Function *bar(C $a)* specialized for the expression *bar($a)* on line 23.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$a->bar()* on line 38.
          Trace #1: Function *bar(C $a)* specialized for the expression *bar($a)* on line 23.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 38.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 16.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$a->baz()* on line 39.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 16.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *B::foo* for the expression *$a->foo()* on line 37.
          Trace #1: Function *bar(B $a)* specialized for the expression *bar($a)* on line 19.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$a->baz()* on line 39.
          Trace #1: Function *bar(B $a)* specialized for the expression *bar($a)* on line 19.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *C::foo* for the expression *$a->foo()* on line 37.
          Trace #1: Function *bar(C $a)* specialized for the expression *bar($a)* on line 23.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$a->bar()* on line 38.
          Trace #1: Function *bar(C $a)* specialized for the expression *bar($a)* on line 23.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 38.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 27.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$a->baz()* on line 39.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 27.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$a->bar()* on line 38.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 32.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$a->baz()* on line 39.
          Trace #1: Function *bar(A $a)* specialized for the expression *bar($a)* on line 32.
          Trace #2: Function *foo(A $a)* specialized for the expression *foo(new A())* on line 42.
      ',
    ]);

  }

  /**
   * Test conditional guarantee cross-namespace symbol lookup.
   * @test @internal
   */
  static function unittest_crossNamespaceSymbolLookup () {
    PhlintTest::assertIssues('
      namespace a {
        class A {
          function foo () {}
        }
      }
      namespace b {
        use a\A;
        class B {
          function foo () {
            $x = null;
            if ($x instanceof A) {
              $x->foo();
              $x->bar();
            }
          }
        }
      }
    ', [
      'Unable to invoke undefined *a\A::bar* for the expression *$x->bar()* on line 13.',
    ]);
  }

  /**
   * Test conditional guarantees barrier with complex conditions.
   * @test @internal
   */
  static function unittest_complexAndCondition () {

    PhlintTest::assertIssues('

      class A {
        function foo () {}
      }

      class B {
        function bar () {}
      }

      class C {
        function foo () {}
        function bar () {}
      }

      function foo ($a) {
        if (rand(0, 1) == 1 && (((($a instanceof A) || ($a instanceof B)) && rand(0, 1) == 1) || ($a instanceof C)))
          $a->foo();
      }

    ', [
      'Unable to invoke undefined *B::foo* for the expression *$a->foo()* on line 17.',
    ]);

  }

  /**
   * Test conditional guarantees barrier within a loop.
   * @test @internal
   */
  static function unittest_loopBarrier () {
    PhlintTest::assertIssues('
      class A {
        function foo () {}
      }
      function foo () {
        /** @var A */
        $a = null;
        foreach ([1, 2, 3] as $x) {
          if (is_null($a))
            break;
          else
            $a->foo();
          $a->foo();
        }
        $a->foo();
      }
    ', [
      'Unable to invoke undefined *mixed::foo* for the expression *$a->foo()* on line 14.',
    ]);
  }

  /**
   * Test conditional connectives conditional guarantees.
   * @test @internal
   */
  static function unittest_conditionalConnectives () {
    PhlintTest::assertIssues('

      class A {}

      class B {
        function foo () {
          return true;
        }
      }

      function foo (A $a) {
        $b = ($a instanceof B) && $a->foo();
        if ($b->bar()) {}
        if (($b instanceof B) && $b->foo())
          $b->foo();
      }

    ', [
      'Unable to invoke undefined *bool::bar* for the expression *$b->bar()* on line 12.',
    ]);
  }

  /**
   * Test conditional connectives conditional guarantees with type changing and method invocation.
   * @test @internal
   */
  static function unittest_conditionalConnectivesMethodInvocation () {
    PhlintTest::assertIssues('
      class A { function bar () {} }
      $foo = "";
      if (($foo instanceof \A && !$foo->bar() && (rand(0, 1) || !$foo->baz())) || !$foo->lol()) {}
    ', [
      'Unable to invoke undefined *A::baz* for the expression *$foo->baz()* on line 3.',
      'Unable to invoke undefined *string::lol* for the expression *$foo->lol()* on line 3.',
    ]);
  }

  /**
   * Test `empty` conditional guarantees with values.
   * @test @internal
   */
  static function unittest_emptyWithValues () {
    PhlintTest::assertIssues('

      class A {
        function foo () {}
      }

      $obj = null;

      if (rand(0, 1))
        $obj = new A();

      if (!empty($obj)) {
        $obj->foo();
        $obj->bar();
      }

    ', [
      'Unable to invoke undefined *A::bar* for the expression *$obj->bar()* on line 13.',
    ]);
  }

  /**
   * Test a complex expression in isset.
   * @test @internal
   */
  static function unittest_issetComplexExpression () {
    PhlintTest::assertNoIssues('
      function foo ($x) {
        if (isset($x[0][1]->{"prop"}[2]))
          return $x[0][1]->{"prop"}[2];
      }
    ');
  }

  /**
   * Test that nested comparison conditions do not cause issues.
   * @test @internal
   */
  static function unittest_nestedConditions () {
    PhlintTest::assertNoIssues('
      $foo = null;
      if (is_null($foo))
        if (true === true)
          if ($foo) {}
    ');
  }

  /**
   * Test function Exists guarantee.
   * @test @internal
   */
  static function unittest_functionExists () {
    PhlintTest::assertIssues('
      if (function_exists("foo"))
        foo();
      foo();
    ', [
      'Unable to invoke undefined *foo()* on line 3.',
    ]);
  }

  /**
   * Regression test for the issue:
   *   Provided symbol *$foo* of type *bool* is not compatible in the expression *foreach ($foo as $bar)* on line 5.
   *
   * @test @internal
   */
  static function unittest_nonEmptyBoolGuarantee () {
    PhlintTest::assertIssues('
      $foo = false;
      if (rand(0, 1))
        $foo = new ArrayObject();
      if (!empty($foo))
        foreach ($foo as $bar) {}
    ', [
    ]);
  }

  /**
   * Test constraint conditional guarantee.
   * @test @internal
   */
  static function unittest_objectConstraintGuarantee () {
    PhlintTest::assertIssues('
      function foo ($obj) {
        if (is_object($obj))
          return get_class($obj) . $obj->bar();
        return $obj;
      }
      foo(2);
      foo("Hello world");
      foo(new stdClass());
    ', [
      '
        Unable to invoke undefined *stdClass::bar* for the expression *$obj->bar()* on line 3.
          Trace #1: Function *foo(stdClass $obj)* specialized for the expression *foo(new stdClass())* on line 8.
      ',
    ]);
  }

  /**
   * @todo: This if this should be always reported or not.
   *
   * Regression test for the issue:
   *   Variable *$baz* used before initialized on line 6.
   *
   * @test @internal
   */
  static function unittest_nonEmptyForeach () {
    return;
    PhlintTest::assertIssues('
      $foo = [];
      if (!empty($foo)) {
        foreach ($foo as $bar) {
          $baz = $bar;
        }
        $fun = $baz;
      }
    ', [
    ]);
  }

  /**
   * Test non-empty string guarantee.
   *
   * Regression test for the issue:
   *   Provided symbol *$foo* of type *string* is not compatible in the expression *foreach ($foo as $bar)* on line 3.
   *
   * @test @internal
   */
  static function unittest_nonEmptyString () {
    // @todo: Fix and enable.
    return;
    PhlintTest::assertNoIssues('
      $foo = "";
      if (!empty($foo))
        foreach ($foo as $bar) {}
    ');
  }

}
