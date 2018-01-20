<?php

use \phlint\Test as PhlintTest;

class ProhibitedConstructsTest {

  /** @test @internal */
  static function unittest_exitTest () {
    PhlintTest::assertIssues('
      exit;
    ', [
      'Using *exit();* is prohibited on line 1.',
    ]);
  }

  /** @test @internal */
  static function unittest_variableVariableTest () {
    PhlintTest::assertIssues('$x = "a"; $$x = "b";', ['Using variable variable *$$x* is prohibited on line 1.']);
  }

}
