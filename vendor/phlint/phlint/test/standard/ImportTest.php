<?php

use \phlint\Test as PhlintTest;

class ImportTest {

  /** @test @internal */
  static function unittest_test () {

    PhlintTest::assertNoIssues('
      use \a\b\c;
      $x = null;
      if ($x instanceof c) {}
    ');

    PhlintTest::assertIssues('
      use \a\b\c;
      $x = null;
      if ($x instanceof \a\b\c) {}
    ', [
      'Import *a\b\c* is not used on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      namespace a\b\c {
        class d {}
      }
      namespace e {
        use \a\b;
        $x = new b\c\d();
      }
    ');

    PhlintTest::assertIssues('
      use \a\b;
      $x = new b\c\d();
    ', [
      // @todo: fix
      'Unable to invoke undefined *b\c\d* for the expression *new b\c\d()* on line 2.',
    ]);

    PhlintTest::assertNoIssues('
      namespace a {
        class B {}
      }
      namespace c {
        use a\B;
        class D {
          function e () {
            if (rand(0, 1))
              $x = new B();
          }
        }
      }

    ');

  }

  /**
   * Cross file import usage.
   *
   * @test @internal
   */
  static function unittest_crossFile () {

    $linter = Phlint::create();

    $linter->addSource('
      namespace a;
      class B {}
    ');

    PhlintTest::assertNoIssues($linter->analyze('
      namespace c;
      use \a\B;
      $x = new B();
    '));

  }

  /**
   * Test import collision in repeating namespaces.
   *
   * Regression test for the issues:
   *   Unable to invoke undefined *a\Foo::bar* for the expression *$c->bar()* on line 21.
   *   Unable to invoke undefined *a\Foo::baz* for the expression *$c->baz()* on line 27.
   *
   * @test @internal
   */
  static function unittest_repeatingNamespaces () {
    PhlintTest::assertIssues('
      namespace a {
        class Foo {
          function foo () {}
        }
        class Bar {
          function bar () {}
        }
        class Baz {
          function baz () {}
        }
      }
      namespace b {
        use \a\Foo as C;
        $c = new C();
        $c->foo();
        $c->fun();
      }
      namespace b {
        use \a\Bar as C;
        $c = new C();
        $c->bar();
        $c->fun();
      }
      namespace b {
        use \a\Baz as C;
        $c = new C();
        $c->baz();
        $c->fun();
      }
    ', [
      'Unable to invoke undefined *a\Foo::fun* for the expression *$c->fun()* on line 16.',
      'Unable to invoke undefined *a\Bar::fun* for the expression *$c->fun()* on line 22.',
      'Unable to invoke undefined *a\Baz::fun* for the expression *$c->fun()* on line 28.',
    ]);
  }

}
