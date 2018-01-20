<?php

use \phlint\Test as PhlintTest;

class JUnitTest {

  /**
   * Test testcase name generation.
   *
   * @test @internal
   */
  static function unittest_testcaseName () {

    $phlint = PhlintTest::create();

    $phlint[] = new \phlint\result\JUnit(fopen(sys_get_temp_dir() . '/nzxqbj4me9y8eqovn2b7ovuv-junit.xml', 'w'));

    $phlint->analyze('
      namespace a;
      class B {
        function foo () {
          $bar = $baz;
        }
      }
    ');

    PhlintTest::assertEquals(file_get_contents(sys_get_temp_dir() . '/nzxqbj4me9y8eqovn2b7ovuv-junit.xml'), '
      <?xml version="1.0" encoding="UTF-8" ?>
        <testsuites>
          <testsuite name="Phlint">
            <testcase name="OK"></testcase>
            <testcase name="Variable *$baz* used before initialized in method a\B::foo">
              <failure message="Variable *$baz* used before initialized on line 4."></failure>
            </testcase>
        </testsuite>
      </testsuites>
    ');

  }

  /**
   * Test testcase name generation in inline function.
   *
   * @test @internal
   */
  static function unittest_testcaseNameInInlineFunction () {

    $phlint = PhlintTest::create();

    $phlint[] = new \phlint\result\JUnit(fopen(sys_get_temp_dir() . '/lxlefc4oop5m7cur6ocwnoqj-junit.xml', 'w'));

    $phlint->analyze('
      namespace a;
      class B {
        function foo () {
          return function () {
            if (rand(0, 1))
              $bar = $baz;
          };
        }
      }
    ');

    PhlintTest::assertEquals(file_get_contents(sys_get_temp_dir() . '/lxlefc4oop5m7cur6ocwnoqj-junit.xml'), '
      <?xml version="1.0" encoding="UTF-8" ?>
        <testsuites>
          <testsuite name="Phlint">
            <testcase name="OK"></testcase>
            <testcase name="Variable *$baz* used before initialized in method a\B::foo">
              <failure message="Variable *$baz* used before initialized on line 6."></failure>
            </testcase>
        </testsuite>
      </testsuites>
    ');

  }

}
