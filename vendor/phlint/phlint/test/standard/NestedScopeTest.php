<?php

use \phlint\Test as PhlintTest;

class NestedScopeTest {

  /**
   * Test variable types in nested scopes.
   *
   * @test @internal
   */
  static function unittest_variableTypes () {
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

      function test ($o) {
        $o->foo();
        $o->bar();
        $o->baz();
      }

      function foo () {
        $a = new A();
        if ($a instanceof B)
          test($a);
        elseif ($a instanceof C)
          test($a);
        elseif ($a instanceof B)
          if (isset($undefinedVariable))
            if ($a instanceof A)
              if ($a instanceof C)
                test($a);
              else
                test($a);
            else
              test($a);
          else
            test($a);
        elseif (isset($undefinedVariable))
          test($a);
        else
          if ($a instanceof B)
            test($a);
          else
            test($a);
      }

    ', [
      '
        Unable to invoke undefined *B::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 23.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 23.
      ',
      '
        Unable to invoke undefined *C::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(C $o)* specialized for the expression *test($a)* on line 25.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$o->bar()* on line 16.
          Trace #1: Function *test(C $o)* specialized for the expression *test($a)* on line 25.
      ',
      '
        Unable to invoke undefined *C::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(C $o)* specialized for the expression *test($a)* on line 30.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$o->bar()* on line 16.
          Trace #1: Function *test(C $o)* specialized for the expression *test($a)* on line 30.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$o->bar()* on line 16.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 32.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 32.
      ',
      '
        Unable to invoke undefined *B::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 34.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 34.
      ',
      '
        Unable to invoke undefined *B::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 36.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 36.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$o->bar()* on line 16.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 38.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 38.
      ',
      '
        Unable to invoke undefined *B::foo* for the expression *$o->foo()* on line 15.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 41.
      ',
      '
        Unable to invoke undefined *B::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(B $o)* specialized for the expression *test($a)* on line 41.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$o->bar()* on line 16.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 43.
      ',
      '
        Unable to invoke undefined *A::baz* for the expression *$o->baz()* on line 17.
          Trace #1: Function *test(A $o)* specialized for the expression *test($a)* on line 43.
      ',
    ]);
  }

}
