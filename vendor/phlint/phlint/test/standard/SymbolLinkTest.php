<?php

use \luka8088\phops as op;
use \phlint\autoload\Mock as MockAutoload;
use \phlint\Test as PhlintTest;

class SymbolLinkTest {

  /**
   * Test symbol autoloading.
   *
   * @test @internal
   */
  static function unittest_autoloader () {

    $linter = PhlintTest::create();

    $linter[] = /** @ExtensionCall("phlint.phpAutoloadClass") */ function ($symbol) {
      if ($symbol == 'class_that_needs_to_be_autoloaded') {
        op\metaContext('code')->load([[
          'path' => '',
          'source' => '
            class class_that_needs_to_be_autoloaded {
              function x () {}
            }
          ',
          'isLibrary' => false,
        ]]);
      }
      if ($symbol == 'a\\class_that_needs_to_be_autoloaded') {
        op\metaContext('code')->load([[
          'path' => '',
          'source' => '
            namespace a {
              class class_that_needs_to_be_autoloaded {
                function y () {}
              }
            }
          ',
          'isLibrary' => false,
        ]]);
      }
    };

    PhlintTest::assertNoIssues($linter->analyze('
      $x = \class_that_needs_to_be_autoloaded::x();
    '));

    PhlintTest::assertNoIssues($linter->analyze('
      namespace a {
        $x = class_that_needs_to_be_autoloaded::y();
      }
    '));

    PhlintTest::assertIssues($linter->analyze('
      $x = \class_that_will_not_be_autoloaded::x();
    '), [
      'Unable to invoke undefined *\class_that_will_not_be_autoloaded::x()* on line 1.',
    ]);

    PhlintTest::assertNoIssues($linter->analyze('
      $f = function () {
        $x = \example::x();
      };
      class example extends \class_that_needs_to_be_autoloaded {}
    '));

    PhlintTest::assertIssues($linter->analyze('
      $f = function () {
        $x = \example::x();
      };
      class example extends \class_that_will_not_be_autoloaded {}
    '), [
      'Unable to invoke undefined *\example::x()* on line 2.',
    ]);
  }

  /**
   * Test lookahead.
   *
   * @test @internal
   */
  static function unittest_lookahead () {

    $linter = PhlintTest::create();

    $linter->addSource('
      class X {
        function z () {
          $y = new Y();
        }
      }
    ');

    $linter->addSource('
      class Y {}
    ');

    PhlintTest::assertNoIssues($linter->analyze('
      $x = new X();
    '));

  }

  /**
   * Test that autoloading from the middle of the code does not reset
   * the traverse state.
   * @test @internal
   */
  static function unittest_traverseStateOnAutoload () {

    $linter = PhlintTest::create();

    $linter[] = /** @ExtensionCall("phlint.phpAutoloadClass") */ function ($symbol) {
      if ($symbol == 'A') {
        op\metaContext('code')->load([[
          'path' => '',
          'source' => '
            class A {
              function bar () {}
            }
          ',
          'isLibrary' => false,
        ]]);
      }
      if ($symbol == 'B') {
        op\metaContext('code')->load([[
          'path' => '',
          'source' => '
            class B {
              function baz () {}
            }
          ',
          'isLibrary' => false,
        ]]);
      }
    };

    PhlintTest::assertNoIssues($linter->analyze('
      function foo ($x) {
        $x["b"] = new B();

        /** @var A */
        $o = $x["a"];
        $o->bar();
      }
    '));

  }

  /**
   * Test namespaced functions symbol linking.
   *
   * @test @internal
   */
  static function unittest_namespacedFunctions () {

    PhlintTest::assertNoIssues('

      namespace {
        function someFunction () {}
      }

      namespace example {
        function someFunction () {}
        function additionalFunction () {}
      }

      namespace {
        someFunction();
        \someFunction();
        \example\someFunction();
        \example\additionalFunction();
      }

    ');

    PhlintTest::assertNoIssues('

      namespace {
        function someFunction () {}
      }

      namespace example {
      }

      namespace example {
        someFunction();
      }

    ');

    /*
    // Function calls inside namespaces are not bound to their namespace.
    // @todo: Revisit, should be fixed in the NameResolver?
    PhlintTest::assertNoIssues('

      namespace {
      }

      namespace example {
        function someFunction () {}
      }

      namespace example {
        someFunction();
      }

    ');
    /**/

    PhlintTest::assertIssues('
      namespace example {
        someFunction();
      }
    ', [
      'Unable to invoke undefined *example\someFunction* for the expression *someFunction()* on line 2.',
    ]);

  }

  /**
   * Symbol imports.
   *
   * @test @internal
   */
  static function unittest_symbolImports () {

    PhlintTest::assertNoIssues('
      namespace a {
        b\A::f();
        $x = new b\A();
      }

      namespace a\b {
        class A {
          function f () {}
        }
      }
    ');

  }

  /**
   * Test referencing classes through variables.
   *
   * @test @internal
   */
  static function unittest_variableClass () {

    PhlintTest::assertIssues('
      $undefinedVariable1::$undefinedVariable2();
    ', [
      'Variable *$undefinedVariable1* used before initialized on line 1.',
      'Variable *$undefinedVariable2* used before initialized on line 1.',
      // @todo: Re-enable after known issues are fixed.
      #'Unable to invoke undefined *$undefinedVariable1::$undefinedVariable2();*.',
    ]);

  }

  /**
   * Test trait method lookup.
   * @test @internal
   */
  static function unittest_traitMethod () {

    PhlintTest::assertNoIssues('
      trait T {
        function foo () {}
      }
      class C {
        use T;
      }
      $c = new C();
      $c->foo();
    ');

    PhlintTest::assertNoIssues('
      namespace a {
        trait T {
          function foo () {}
        }
      }
      namespace b {
        class C {
          use \a\T;
        }
        $c = new C();
        $c->foo();
      }
    ');

  }

  /**
   * Test import alias lookup.
   * @test @internal
   */
  static function unittest_aliasLookup () {
    PhlintTest::assertNoIssues('
      namespace a\b {
        class D {}
      }
      namespace c {
        use \a\b as i;
        $x = new i\D;
      }
    ');
  }

  /**
   * Test linking to an extended interface.
   * @test @internal
   */
  static function unittest_linkToExtendedInterface () {

    PhlintTest::assertIssues('
      class C {
        function foo () {}
      }
      $x = new C();
      $y = $x;
      $y->foo();
      $y->bar();
    ', [
      'Unable to invoke undefined *C::bar* for the expression *$y->bar()* on line 7.',
    ]);

    PhlintTest::assertIssues('
      namespace e\f {
        use \c;
        function bar (c\d\J $x) {
          $x->foo();
          $x->baz();
        }
      }
      namespace c\d {
        use \a\b;
        interface J extends b\I {}
      }
      namespace a\b {
        interface I {
          function foo () {}
        }
      }
    ', [
      'Unable to invoke undefined *c\d\J::baz* for the expression *$x->baz()* on line 5.',
    ]);

  }

  /**
   * Test invoking a array element doesn't get linked to an invalid definition
   * causing an infinite recursion in template specialization.
   * @test @internal
   */
  static function unittest_invokeArrayElement () {

    PhlintTest::assertNoIssues('
      namespace a {
        class B {
          function foo () {
            $this->bar["helper"]();
          }
        }
      }
    ');

  }

  /**
   * Test static call linking with relative reference.
   * @test @internal
   */
  static function unittest_staticCallWithRelativeReference () {

    PhlintTest::assertIssues('
      namespace a {
        class D {
          static function foo () { return 1; }
        }
      }
      namespace {
        use \a as c;
        function baz () {
          return c\D::foo();
          return c\D::bar();
        }
      }
    ', [
      'Unable to invoke undefined *a\D::bar* for the expression *c\D::bar()* on line 10.',
    ]);

  }

  /**
   * @test @internal
   * Test nested extends linking.
   */
  static function unittest_nestedExtends () {
    PhlintTest::assertIssues('
      trait T {
        function Foo () {}
      }
      class A {
        use T;
      }
      class B extends A {}
      class C extends B {}
      class D extends B {}

      $o = new D();
      $o->Foo();
      $o->Bar();
    ', [
      'Unable to invoke undefined *D::Bar* for the expression *$o->Bar()* on line 13.',
    ]);
  }

  /**
   * Test linking to a library class with library trait.
   *
   * Regression test for the issue:
   *   Unable to invoke undefined *a\A::bar* for the expression *$foo->bar()* on line 2.
   *
   * @test @internal
   */
  static function unittest_staticDefaultInitialization () {

    $phlint = PhlintTest::create();

    $phlint[] = new MockAutoload([
      'a\A' => '
        namespace a;
        class A {
          use B;
        }
      ',
      'a\B' => '
        namespace a;
        trait B {
          function bar () {}
        }
      ',
    ]);

    PhlintTest::assertIssues($phlint->analyze('
      $foo = new \a\A();
      $foo->bar();
      $foo->baz();
    '), [
      'Unable to invoke undefined *a\A::baz* for the expression *$foo->baz()* on line 3.',
    ]);

  }

}
