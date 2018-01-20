<?php

use \phlint\Test as PhlintTest;

class TypeCompatibilityTest {

  /** @test @internal */
  static function unittest_test () {

    PhlintTest::assertIssues('
      $x = function_that_does_not_exist();
    ', [
      'Unable to invoke undefined *function_that_does_not_exist()* on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      $x = chr(64);
    ');

    PhlintTest::assertIssues('
      $x = \class_that_does_not_exist::method_that_does_not_exist();
    ', [
      'Unable to invoke undefined *\class_that_does_not_exist::method_that_does_not_exist()* on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      $x = \DateTime::getLastErrors();
    ');

    PhlintTest::assertNoIssues('
      $f = function () {
        $x = \DateTime::getLastErrors();
      };
    ');

    PhlintTest::assertIssues('
      class A { static function x () {} }
      class B extends A {}
      B::y();
    ', [
      'Unable to invoke undefined *B::y()* on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      class A { static function x () {} }
      class B extends A {}
      B::x();
    ');

    PhlintTest::assertIssues('
      namespace ns1;
      class A { static function x () {} }
      class B extends A {}
      B::y();
    ', [
      'Unable to invoke undefined *ns1\B::y* for the expression *B::y()* on line 4.',
    ]);

    PhlintTest::assertNoIssues('
      namespace ns1;
      class a { static function x () {} }
      class b extends a {}
      b::x();
    ');

    PhlintTest::assertIssues('
      class a {}
      $x = new A();
      $x = new B();
    ', [
      'Expression *new A()* is not using the same letter casing as class *a* on line 2.',
      'Unable to invoke undefined *B* for the expression *new B()* on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      class a {}
      $x = new a();
    ');

    PhlintTest::assertIssues('
      class a {}
      try {} catch (A $e) {}
      try {} catch (B $e) {}
    ', [
      'Catch *A $e* is not using the same letter casing as class *a* on line 2.',
      'Unable to invoke undefined *B* for the catch *B $e* on line 3.',
    ]);

    PhlintTest::assertNoIssues('
      class a {}
      try {} catch (a $e) {}
    ');

    PhlintTest::assertNoIssues('
      $x = \DateTime::createFromFormat("Y-m-d", "2000-01-01");
    ');

  }

  /**
   * Test template specialization.
   *
   * @test @internal
   */
  static function unittest_templateSpecialization () {

    PhlintTest::assertNoIssues('
      function f () {
        return "2";
      }
      $x = f() + 1;
    ');

    PhlintTest::assertIssues('
      function f () {
        return "a";
      }
      $x = f() + 1;
    ', [
      'Provided expression *f()* of type *string* is not compatible in the expression *f() + 1* on line 4.',
    ]);

    PhlintTest::assertNoIssues('
      function sum ($a, $b) {
        return $a + $b;
      }
      $x = sum(2, 3);
      $x = sum("2", 3);
      $x = sum(2, "3");
    ');

    PhlintTest::assertIssues('
      function sum ($a, $b) {
        return $a + $b;
      }
      $x = sum("a", 3);
    ', [
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 2.
          Trace #1: Function *sum("a", 3)* specialized for the expression *sum("a", 3)* on line 4.
      ',
    ]);

    PhlintTest::assertIssues('
      function sum ($a, $b) {
        return $a + $b;
      }
      function doSum ($c, $d) {
        return sum($c, $d);
      }
      $x = doSum("a", 3);
    ', [
      '
        Provided variable *$a* of type *string* is not compatible in the expression *$a + $b* on line 2.
          Trace #1: Function *sum("a", 3)* specialized for the expression *sum($c, $d)* on line 5.
          Trace #2: Function *doSum("a", 3)* specialized for the expression *doSum("a", 3)* on line 7.
      ',
    ]);

    PhlintTest::assertNoIssues('
      function sum ($a, $b) {
        $c = $a;
        return $c + $b;
      }

      function doSum ($d, $e) {
        $f = $d;
        return sum($f, $e);
      }

      function getExternalInput () : int {
        return 2;
      }

      $x = doSum(getExternalInput(), 3);
    ');

    PhlintTest::assertIssues('
      function sum ($a, $b) {
        $c = $a;
        return $c + $b;
      }

      function doSum ($d, $e) {
        $f = $d;
        return sum($f, $e);
      }

      function getExternalInput () : string {
        return "a";
      }

      $x = doSum(getExternalInput(), 3);
    ', [
      '
        Provided variable *$c* of type *string* is not compatible in the expression *$c + $b* on line 3.
          Trace #1: Function *sum("a", 3)* specialized for the expression *sum($f, $e)* on line 8.
          Trace #2: Function *doSum("a", 3)* specialized for the expression *doSum(getExternalInput(), 3)* on line 15.
      ',
    ]);

    PhlintTest::assertIssues('
      function foo () {
        $x = true ? rand(0, 1) : false;
        return $x;
      }
      function bar () {
        $x = foo() + 1;
      }
    ', [
      'Provided expression *foo()* of type *bool* is not compatible in the expression *foo() + 1* on line 6.',
    ]);

  }

  /**
   * Test that the analyzer does not go into infinite recursion while analyzing.
   * @test @internal
   */
  static function unittest_infiniteRecursion () {
    PhlintTest::assertNoIssues('
      function f () {
        f();
      }
      f();
    ');
  }

  /**
   * Regression test for undefined function not being reported due to
   * a closure being registered as empty symbol name.
   * @test @internal
   */
  static function unittest_closureSymbol () {
    PhlintTest::assertIssues('
      function f1 () {
        fx();
      }
      function f2 () {
        $f3 = function () {};
        $x = $f3();
      }
    ', [
      'Unable to invoke undefined *fx()* on line 2.',
    ]);
  }

  /**
   * Test execution branching.
   *
   * @test @internal
   */
  static function unittest_executionBranching () {

    PhlintTest::assertNoIssues('
      function f () {
        if (rand(0, 1))
          $x = 0;
        else
          $x = 1;
        return $x;
      }
      $x = f() + 1;
    ');

    PhlintTest::assertIssues('
      function f () {
        if (rand(0, 1))
          $x = 0;
        else
          $x = "a";
        return $x;
      }
      $x = f() + 1;
    ', [
      'Provided expression *f()* of type *string* is not compatible in the expression *f() + 1* on line 8.',
    ]);

    PhlintTest::assertIssues('
      function sum ($a, $b) {
        $c = $a;
        return $c + $b;
      }

      function doSum ($d, $e) {
        if (rand(0, 1))
          $f = $d;
        else
          $f = "a";
        return sum($f, $e);
      }

      function getExternalInput () : int {
        return 2;
      }

      $x = doSum(getExternalInput(), 3);
    ', [
      '
        Provided variable *$c* of type *string* is not compatible in the expression *$c + $b* on line 3.
          Trace #1: Function *sum("a", $b)* specialized for the expression *sum($f, $e)* on line 11.
      ',
      '
        Provided variable *$c* of type *string* is not compatible in the expression *$c + $b* on line 3.
          Trace #1: Function *sum(2|"a", 3)* specialized for the expression *sum($f, $e)* on line 11.
          Trace #2: Function *doSum(2, 3)* specialized for the expression *doSum(getExternalInput(), 3)* on line 18.
      ',
    ]);

  }

  /**
   * Test class concept.
   * @test @internal
   */
  static function unittest_classConcept () {

    PhlintTest::assertNoIssues('

      class A {
        function bar () { return 1; }
      }

      class B {
        function bar () { return 2; }
      }

      class C {
        function foo () {}
      }

      function foo ($o, $i) {
        return $o->bar() + $i;
      }

      function baz ($newObj) {
        if (rand(0, 1))
          $obj = new A();
        else
          $obj = $newObj;
        return foo($obj, 2);
      }

      $x = foo(new B(), baz(new B()));

    ');

    PhlintTest::assertIssues('

      class A {
        function bar () { return 1; }
      }

      class B {
        function bar () { return 2; }
      }

      class C {
        function foo () {}
      }

      function foo ($o, $i) {
        return $o->bar() + $i;
      }

      function baz ($newObj) {
        if (rand(0, 1))
          $obj = new A();
        else
          $obj = $newObj;
        return foo($obj, 2);
      }

      $x = foo(new B(), baz(new C()));

    ', [
      '
        Unable to invoke undefined *C::bar* for the expression *$o->bar()* on line 15.
          Trace #1: Function *foo(A|C $o, 2)* specialized for the expression *foo($obj, 2)* on line 23.
          Trace #2: Function *baz(C $newObj)* specialized for the expression *baz(new C())* on line 26.
      ',
    ]);

  }

  /**
   * Test implicit conversion to bool with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToBoolWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      function foo (bool $bar) {}
      foo(1.1);
      foo("1.1");
      foo("0.0");
      foo("1.0");
      foo(1);
      foo(2);
      foo("1");
      foo("2");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *bool*
          in the expression *foo(1.1)* on line 2.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.1")* on line 3.
      ',
      '
        Provided argument *"0.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("0.0")* on line 4.
      ',
      '
        Provided argument *"1.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.0")* on line 5.
      ',
      // @todo: Implement 0 or 1 to intBool
      '
        Provided argument *1* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(1)* on line 6.
      ',
      '
        Provided argument *2* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(2)* on line 7.
      ',
      '
        Provided argument *"2"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("2")* on line 9.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("bar")* on line 10.
      ',
    ]);
  }

  /**
   * Test implicit conversion to bool with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToBoolWithPHPDocDeclaration () {
    PhlintTest::assertIssues('
      /**
       * @param bool $bar
       */
      function foo ($bar) {}
      foo(1.1);
      foo("1.1");
      foo("0.0");
      foo("1.0");
      foo(1);
      foo(2);
      foo("1");
      foo("2");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *bool*
          in the expression *foo(1.1)* on line 5.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.1")* on line 6.
      ',
      '
        Provided argument *"0.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("0.0")* on line 7.
      ',
      '
        Provided argument *"1.0"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("1.0")* on line 8.
      ',
      // @todo: Implement 0 or 1 to intBool
      '
        Provided argument *1* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(1)* on line 9.
      ',
      '
        Provided argument *2* of type *int* is not implicitly convertable to type *bool*
          in the expression *foo(2)* on line 10.
      ',
      '
        Provided argument *"2"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("2")* on line 12.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *bool*
          in the expression *foo("bar")* on line 13.
      ',
    ]);
  }

  /**
   * Test implicit conversion to string with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToStringWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      function foo (string $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *string*
          in the expression *foo(false)* on line 7.
      ',
    ]);
  }

  /**
   * Test implicit conversion to string with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToStringWithPHPDocDeclaration () {
    PhlintTest::assertIssues('
      /**
       * @param string $bar
       */
      function foo ($bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *string*
          in the expression *foo(false)* on line 10.
      ',
    ]);
  }

  /**
   * Test implicit conversion to integer with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToIntegerWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      function foo (int $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *int*
          in the expression *foo(1.1)* on line 2.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("1.1")* on line 3.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("bar")* on line 6.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *int*
          in the expression *foo(false)* on line 7.
      ',
    ]);
  }

  /**
   * Test implicit conversion to integer with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToIntegerWithPHPDocDeclaration () {
    PhlintTest::assertIssues('
      /**
       * @param int $bar
       */
      function foo ($bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *1.1* of type *float* is not implicitly convertable to type *int*
          in the expression *foo(1.1)* on line 5.
      ',
      '
        Provided argument *"1.1"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("1.1")* on line 6.
      ',
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *int*
          in the expression *foo("bar")* on line 9.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *int*
          in the expression *foo(false)* on line 10.
      ',
    ]);
  }

  /**
   * Test implicit conversion to float with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToFloatWithArugmentDeclaration () {
    PhlintTest::assertIssues('
      function foo (float $bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *float*
          in the expression *foo("bar")* on line 6.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *float*
          in the expression *foo(false)* on line 7.
      ',
    ]);
  }

  /**
   * Test implicit conversion to float with argument declaration.
   *
   * @test @internal
   */
  static function unittest_implicitConversionToFloatWithPHPDocDeclaration () {
    PhlintTest::assertIssues('
      /**
       * @param float $bar
       */
      function foo ($bar) {}
      foo(1.1);
      foo("1.1");
      foo(1);
      foo("1");
      foo("bar");
      foo(false);
    ', [
      '
        Provided argument *"bar"* of type *string* is not implicitly convertable to type *float*
          in the expression *foo("bar")* on line 9.
      ',
      '
        Provided argument *false* of type *bool* is not implicitly convertable to type *float*
          in the expression *foo(false)* on line 10.
      ',
    ]);
  }

  /**
   * Test isTraversable.
   * @test @internal
   */
  static function unittest_isTraversable () {

    PhlintTest::assertIssues('
      function myRangeSum ($values) {
        $result = 0;
        foreach ($values as $value) {
          $result += $value;
        }
        return $result;
      }

      myRangeSum([1, 2.2, "2", 3]);
      myRangeSum([1, 2.2, "2", 3, "a"]);
      myRangeSum(2);
    ', [
      '
        Provided symbol *$values* of type *int* is not compatible in the expression *foreach ($values as $value)* on line 3.
          Trace #1: Function *myRangeSum(2)* specialized for the expression *myRangeSum(2)* on line 11.
      ',
      '
        Provided variable *$value* of type *autoString* is not compatible in the expression *$result += $value* on line 4.
          Trace #1: Function *myRangeSum(autoString[] $values)* specialized for the expression *myRangeSum([1, 2.2, "2", 3, "a"])* on line 10.
      ',
    ]);

  }

  /**
   * Test argument types.
   * @test @internal
   */
  static function unittest_argumentTypes () {
    PhlintTest::assertIssues('

      function foo () {
        return rand(0, 1) ? 2 : ["a" => 1];
      }

      function bar () {
        return rand(0, 1) ? 2.0 : ["a" => 1.0];
      }

      $baz = sprintf("%0.2f and %0.2f", foo(), bar());

    ', [
      '
        Provided argument *foo()* of type *int[]* is not implicitly convertable to type *string*
          in the expression *sprintf("%0.2f and %0.2f", foo(), bar())* on line 10.',
      '
        Provided argument *bar()* of type *float[]* is not implicitly convertable to type *string*
          in the expression *sprintf("%0.2f and %0.2f", foo(), bar())* on line 10.
      ',
    ]);
  }

  /**
   * Test type compatibility with class and interface inheritance.
   * @test @internal
   */
  static function unittest_classInheritanceCompatibility () {
    PhlintTest::assertIssues('

      class A extends B {}
      class B implements I {}
      class C implements J {}
      interface I extends J {}
      interface J {}

      function foo (I $a) {}

      foo(new A());
      foo(new C());

    ', [
      '
        Provided argument *new C()* of type *C* is not implicitly convertable to type *I*
          in the expression *foo(new C())* on line 11.
      ',
    ]);
  }

  /**
   * Test type compatibility with class and interface inheritance on a standard library example.
   * @test @internal
   */
  static function unittest_classInheritanceCompatibilityInStandardLibrary () {
    PhlintTest::assertNoIssues('
      $foo = new DateTime();
      $foo->diff($foo);
    ');
  }

  /**
   * @test @internal
   */
  static function unittest_classInheritanceCompabibilityWithMethodSpecialization () {

    // @todo: Implement;
    return;

    PhlintTest::assertIssues('

      class A extends B {}

      class B implements I {
        function foo (J $x) {}
      }

      class C {
        function foo (C $x) {}
      }

      interface I extends J {}

      interface J {
        function foo (J $x) {}
      }

      function baz ($a, $b) {
        $a->foo($b);
        $a->bar($b);
      }

      baz(new A(), new A());
      baz(new C(), new A());
      baz(new A(), new C());
      baz(new C(), new C());

    ', [
      '
        Unable to invoke undefined *A::bar* for the expression *$a->bar($b)* on line 20.
          Trace #1: Function *baz(A $a, A $b)* specialized for the expression *baz(new A(), new A())* on line 23.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$a->bar($b)* on line 20.
          Trace #1: Function *baz(C $a, A $b)* specialized for the expression *baz(new C(), new A())* on line 24.
      ',
      '
        Unable to invoke undefined *A::bar* for the expression *$a->bar($b)* on line 20.
          Trace #1: Function *baz(A $a, C $b)* specialized for the expression *baz(new A(), new C())* on line 25.
      ',
      '
        Unable to invoke undefined *C::bar* for the expression *$a->bar($b)* on line 20.
          Trace #1: Function *baz(C $a, C $b)* specialized for the expression *baz(new C(), new C())* on line 26.
      ',
    ]);
  }

  /**
   * @test @internal
   */
  static function unittest_typeCompatibility () {

    // @todo: Implement.
    return;

    PhlintTest::assertIssues('

      class A {

        function bar () {
          $b = B::getInstance();
          return $b->baz();
        }

      }

      class B {

        public static function getInstance () {
          static $instance = null;
          if (!$instance)
            $instance = new B();
          return $instance;
        }

        function baz () {
          $data = [];
          foreach ([1, 2, 3] as $v)
            $data[] = $v;
          return $data;
        }

      }

      $obj = new A();

      $foo = sprintf("%0.2f", $obj->bar());

    ', [
    ]);
  }

  /**
   * Test invocation of scalars.
   *
   * @test @internal
   */
  static function unittest_invokeScalar () {
    // @todo: Fix and update.
    PhlintTest::assertIssues('
      if (rand(0, 1))
        $foo = null;
      else if (rand(0, 1))
        $foo = true;
      else if (rand(0, 1))
        $foo = false;
      else
        $foo = 1;
      $foo();
    ', [
      'Unable to invoke undefined *int* for the expression *$foo()* on line 9.',
    ]);
  }

  /**
   * Test implicit type cast.
   *
   * @test @internal
   */
  static function unittest_implicitTypeCast () {
    // @todo: Rewrite, implement, and enable.
    PhlintTest::assertNoIssues('
      class X {}
      function foo (X $a) {}
      $bar = [new X(), new X(), new X()];
      foo($bar[0]);
    ');
    /*
    PhlintTest::assertNoIssues('
      class X {}
      class Y {}
      function foo (X $a) {}
      $bar = [new X(), new Y(), new X(), new Y()];
      foo($bar[0]);
    ');
    /*
    context('code', new \phlint\Code(), function () {
      assert(Type::common(['t_X', 't_X', 't_X']) == 't_X');
      assert(Type::common(['t_X', 't_Y', 't_Y', 't_X']) == '');
      assert(Type::common(['t_int', 't_float']) == 't_float');
      assert(Type::common(['t_autoInteger', 't_autoFloat']) == 't_autoFloat');
      assert(Type::common(['t_int', 't_int']) == 't_int');
      assert(Type::common(['t_int', 't_string']) == 't_autoString');
      assert(Type::common(['t_int', 't_float', 't_autoInteger', 't_int']) == 't_autoFloat');
    });
    /**/
  }

  /**
   * Test unknown type invocation.
   *
   * Regression test for the issue:
   *   Unable to invoke undefined *::fun* for the expression *$baz->fun()* on line 4.
   *
   * @test @internal
   */
  static function unittest_unknownTypeInvocation () {
    PhlintTest::assertNoIssues('
      function foo ($bar) {
        $baz = call_user_func($bar);
        $baz();
        $baz->fun();
        $baz::fun();
      }
    ');
  }

  /**
   * Test numeric operation on float.
   *
   * Regression test for the issue:
   *   Provided expression *sqrt(1)* of type *float* is not compatible in the expression *1 - sqrt(1)* on line 1.
   *
   * @test @internal
   */
  static function unittest_floatNumeric () {
    PhlintTest::assertNoIssues('
      $foo = 1 - sqrt(1);
    ');
  }

  /**
   * Static type keyword sanity test.
   *
   * Regression test for the issue:
   *   Provided argument *A::create()* of type *static* is not compatible
   *     in the expression *A::foo(A::create())* on line 7.
   *
   * @test @internal
   */
  static function unittest_staticTypeKeyword () {
    PhlintTest::assertNoIssues('
      class A {
        static function create () {
          return new static();
        }
        static function foo (A $a) {}
      }
      A::foo(A::create());
    ');
  }

  /**
   * Test cross namespace inheritance compatibility.
   *
   * Regression test for the issue:
   *   Provided argument *new C()* of type *c\C* is not compatible
   *     in the expression *$b->foo(new C())* on line 5.
   *
   * @test @internal
   */
  static function unittest_crossNamespaceInheritanceCompatibility () {
    PhlintTest::assertIssues('
      namespace a {
        use \b\B;
        use \c\C;
        $b = new B();
        $b->foo(new C());
        $b->foo(new B());
      }
      namespace b {
        use \d\D;
        class B {
          function foo (D $d) {}
        }
      }
      namespace c {
        use \d\D;
        class C implements D {}
      }
      namespace d {
        interface D {}
      }
    ', [
      '
        Provided argument *new B()* of type *b\B* is not implicitly convertable to type *d\D*
          in the expression *$b->foo(new B())* on line 6.
      ',
    ]);
  }

}
