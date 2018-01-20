<?php

use \phlint\Test as PhlintTest;

class ReturnTypeTest {

  /**
   * Test multiple return type branches.
   *
   * @test @internal
   */
  static function unittest_multipleReturnBrances () {
    PhlintTest::assertIssues('

      class A {}
      class B {}
      class C {}

      function foo () {
        if (rand(0, 1))
          return new A();
        else if (rand(0, 1))
          return new B();
        return new C();
      }

      function bar ($baz) {
        $baz->fun();
      }

      bar(foo());

    ', [
      '
        Unable to invoke undefined *A::fun* for the expression *$baz->fun()* on line 15.
          Trace #1: Function *bar(A|B|C $baz)* specialized for the expression *bar(foo())* on line 18.
      ',
      '
        Unable to invoke undefined *B::fun* for the expression *$baz->fun()* on line 15.
          Trace #1: Function *bar(A|B|C $baz)* specialized for the expression *bar(foo())* on line 18.
      ',
      '
        Unable to invoke undefined *C::fun* for the expression *$baz->fun()* on line 15.
          Trace #1: Function *bar(A|B|C $baz)* specialized for the expression *bar(foo())* on line 18.
      ',
    ]);
  }

  /**
   * Test that only reachable return types are considered.
   *
   * @test @internal
   */
  static function unittest_onlyReachable () {
    PhlintTest::assertIssues('

      class A {}
      class B {}
      class C {}

      function foo () {
        return new A();
        if (rand(0, 1))
          return new B();
        return new C();
      }

      function bar ($baz) {
        $baz->fun();
      }

      bar(foo());

    ', [
      '
        Unable to invoke undefined *A::fun* for the expression *$baz->fun()* on line 14.
          Trace #1: Function *bar(A $baz)* specialized for the expression *bar(foo())* on line 17.
      ',
    ]);
  }

}
