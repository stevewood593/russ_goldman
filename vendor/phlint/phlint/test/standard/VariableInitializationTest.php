<?php

use \phlint\Test as PhlintTest;

class VariableInitializationTest {

  /**
   * General tests.
   *
   * @test @internal
   */
  static function unittest_test () {

    PhlintTest::assertNoIssues('
      $x = 0;
      $y = $x;
    ');

    PhlintTest::assertIssues('
      $y = $x;
    ', [
      'Variable *$x* used before initialized on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      $x = $_SERVER;
    ');

    PhlintTest::assertIssues('
      $y = function ($x) {};
      $y($x);
    ', [
      'Variable *$x* used before initialized on line 2.',
    ]);

    PhlintTest::assertNoIssues('
      static $x = 0;
      $y = $x;
    ');

    PhlintTest::assertIssues('
      foreach ([$x] as $x) {}
    ', [
      'Variable *$x* used before initialized on line 1.',
    ]);

    PhlintTest::assertIssues('
      $f1 = function ($x) {
        $f2 = function ($y) {
          $z = $x;
        };
      };
    ', [
      'Variable *$x* used before initialized on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      $f1 = function ($x) {
        return $x;
      };
    ');

    PhlintTest::assertNoIssues('
      foreach ([1, 2] as $x) {
        $a = $x;
      }
    ');

    PhlintTest::assertNoIssues('
      foreach ([1, 2] as $y => $x) {
        $a = $y;
        $b = $x;
      }
    ');

    PhlintTest::assertNoIssues('
      $a = [];
      $f1 = function ($x) use (&$a) {
        $y = &$a[$x];
      };
    ');

    PhlintTest::assertIssues('
      $f1 = function () {
        $f2 = function () use (&$a) {
          $x = $a;
        };
      };
    ', [
      'Variable *$a* used before initialized on line 2.',
      'Variable *$a* used before initialized on line 3.',
    ]);

    PhlintTest::assertIssues('
      $a = [];
      $f1 = function ($x) {
        $y = &$a[$x];
      };
    ', [
      'Variable *$a* used before initialized on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      try {
        $x = 1;
      } catch (\Exception $e) {
        $y = $e;
      }
    ');

    PhlintTest::assertNoIssues('
      $x = function (/** @out */ &$y) {
        $y = 2;
      };
      $x($z);
    ');

    PhlintTest::assertIssues('
      $x = function (&$y) {
        $y = 2;
      };
      $x($z);
    ', [
      'Variable *$z* used before initialized on line 4.',
    ]);

    PhlintTest::assertNoIssues('
      $x = function ($y1, /** @out */ &$y2) {
        $y1 = 1;
        $y2 = 2;
      };
      $x(5, $z);
    ');

    PhlintTest::assertNoIssues('
      preg_match("/a/", "a", $match);
    ');

    PhlintTest::assertIssues('
      preg_match("/a/", $content);
    ', [
      'Variable *$content* used before initialized on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      function f1 ($x) {
        if ($y = $x)
          return f2($y);
      }
      function f2 ($x2) {}
    ');

    PhlintTest::assertNoIssues('
      $f = function ($x) {
        $f2 = function ($x2) {};
        if ($y = $x)
          return $f2($y);
      };
    ');

    PhlintTest::assertNoIssues('
      function f () {
        if (rand(0, 1))
          foreach ([1, 2] as $index => $value) {}
        if (rand(0, 1))
          foreach ([1, 2] as $index => $value) {}
      }
    ');

    PhlintTest::assertNoIssues('
      function f1 () {
        return ["a", "b"];
      }
      function f2 () {
        list($x1, $x2) = f1();
        $y1 = $x1;
        $y2 = $x2;
      }
    ');

    PhlintTest::assertNoIssues('
      class A {
        protected $i = 0;
        function __construct () {
          $this->i = 2;
        }
      }
    ');

  }

  /**
   * Test conditional initialization.
   *
   * @test @internal
   */
  static function unittest_conditionalInitialization () {

    PhlintTest::assertNoIssues('
      function f () {
        $x = 0;
        if (rand(0, 1))
          $x = 1;
        return $x;
      }
    ');

    PhlintTest::assertNoIssues('
      function f () {
        if (rand(0, 1))
          $x = 0;
        else
          $x = 1;
        return $x;
      }
    ');

    PhlintTest::assertIssues('
      function f () {
        if (rand(0, 1))
          $x = 0;
        return $x;
      }
    ', [
      'Variable *$x* used before initialized on line 4.',
    ]);

    PhlintTest::assertIssues('
      function f () {
        foreach (array_fill(0, rand(0, 1), 1) as $x)
          $y = $x;
        return $y;
      }
    ', [
      'Variable *$y* used before initialized on line 4.',
    ]);

    PhlintTest::assertIssues('
      class A implements \IteratorAggregate {
        function getIterator () {
          return new \ArrayIterator([]);
        }
      }
      function x () {
        $a = new A();
        if (!empty($a))
          foreach ($a as $v)
            $b = $v;
        $x = $b;
      }
      x();
    ', [
      'Variable *$b* used before initialized on line 11.',
    ]);

  }

  /**
   * Test cross-namespace lookup.
   *
   * @test @internal
   */
  static function unittest_crossNamespace () {

    $linter = new Phlint();

    $linter->addSource('
      namespace a;
      function x () {
        $i = 0;
      }
    ');

    $linter->addSource('
      namespace b;
      function x () {
        return $i;
      }
    ');

    PhlintTest::assertIssues($linter->analyze(), [
      'Variable *$i* used before initialized on line 3.'
    ]);

  }

  /**
   * Test that namespace does not create a new context.
   * @test @internal
   */
  static function unittest_namespaceContext () {
    PhlintTest::assertNoIssues('
      namespace a {
        $x = 1;
      }
      namespace b {
        $y = $x;
      }
    ');
  }

  /**
   * That variable initialization for conditional connectives.
   * @test @internal
   */
  static function unittest_conditionalConnectives () {
    PhlintTest::assertIssues('
      function foo () {
        $a = ($b = rand(0, 1)) && ($c = $b);
        $d = $c;
      }
    ', [
      'Variable *$c* used before initialized on line 3.',
    ]);
  }

  /**
   * Regression test for the issue:
   *   Variable *$b* used before initialized on line 2.
   *
   * @test @internal
   */
  static function unittest_parametersInConditions () {
    PhlintTest::assertIssues('
      function foo ($a, $b) {
        if (is_null($a) && is_null($b) && is_null($c)) {}
      }
    ', [
      'Variable *$c* used before initialized on line 2.',
    ]);
  }

  /**
   * Regression test for the issue:
   *   Variable *$b* used before initialized on line 3.
   *
   * @test @internal
   */
  static function unittest_outReferenceInCondition () {
    PhlintTest::assertIssues('
      function foo ($a) {
        if ($a && bar($b) && (rand($z, 1) && ($x = 2) && ($y = 3))) {
          if ($b && $y && $z) {}
        }
      }
      function bar (/** @out */ &$r) {
        $r = 2;
        return true;
      }
    ', [
      'Variable *$z* used before initialized on line 2.',
      'Variable *$z* used before initialized on line 3.',
    ]);
  }

  /**
   * Test that conditionally defining a variable in switch
   * and using it afterwards produces an appropriate issue.
   *
   * @test @internal
   */
  static function unittest_conditionallyDefineVariableInSwitch () {
    PhlintTest::assertIssues('
      switch (rand(0, 1)) {
        case 0:
          $foo = 1;
      }
      $bar = $foo;
    ', [
      'Variable *$foo* used before initialized on line 5.',
    ]);
  }

  /**
   * Regression test for the issue:
   *   Variable *$foo* used before initialized on line 6.
   *
   * @test @internal
   */
  static function unittest_unconditionallyDefineVariableInSwitch () {
    PhlintTest::assertNoIssues('
      switch (rand(0, 1)) {
        case 0:
        default:
          $foo = 1;
      }
      $bar = $foo;
    ');
  }

  /**
   * Define undefined variable test.
   *
   * Regression test for the issue:
   *   Variable *$foo* used before initialized on line 3.
   *
   * @test @internal
   */
  static function unittest_defineUndefinedVariable () {
    PhlintTest::assertNoIssues('
      if (empty($foo))
        $foo = 1;
      $bar = $foo;
    ');
  }

  /**
   * `static` variables are default initialized to `null`.
   *
   * Regression test for the issues:
   *   Variable *$foo* used before initialized on line 1.
   *   Variable *$foo* used before initialized on line 2.
   *
   * @test @internal
   */
  static function unittest_staticDefaultInitialization () {
    PhlintTest::assertNoIssues('
      static $foo;
      $bar = $foo;
    ');
  }

  /**
   * Test undefined variables in multiple definitions.
   *
   * @test @internal
   */
  static function unittest_multipleDefinitionsUndefinedVariable () {
    PhlintTest::assertIssues('
      function foo () {
        if (rand(0, 1))
          $bar = 1;
        else
          $bar = 2;
      }
      function foo () {
        if (rand(0, 1))
          $baz = $bar;
        $fun = $bar;
      }
    ', [
      'Having multiple definitions for *foo* is prohibited on line 1.',
      'Having multiple definitions for *foo* is prohibited on line 7.',
      'Variable *$bar* used before initialized on line 9.',
      'Variable *$bar* used before initialized on line 10.',
    ]);
  }

  /**
   * Test simple unset.
   *
   * @test @internal
   */
  static function unittest_simpleUnset () {
    PhlintTest::assertIssues('
      unset($foo);
      $foo = 1;
      unset($foo);
      $bar = $foo;
    ', [
      'Variable *$foo* used before initialized on line 4.',
    ]);
  }

}
