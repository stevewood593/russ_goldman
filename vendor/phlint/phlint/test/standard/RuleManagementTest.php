<?php

use \phlint\Test as PhlintTest;

class RuleManagementTest {

  /**
   * Test rule enabling and disabling.
   * @test @internal
   */
  static function unittest_test () {
    $phlint = PhlintTest::create();
    assert(count($phlint->analyze('$foo = 2 + "a"; $x = $y; $z[] = 1;')->getIssues()) == 3);
    $phlint->disableRule('requireVariableInitialization');
    assert(count($phlint->analyze('$foo = 2 + "a"; $x = $y; $z[] = 1;')->getIssues()) == 2);
    $phlint->disableRule('all');
    assert(count($phlint->analyze('$foo = 2 + "a"; $x = $y; $z[] = 1;')->getIssues()) == 0);
    $phlint->enableRule('requireVariableInitialization');
    assert(count($phlint->analyze('$foo = 2 + "a"; $x = $y; $z[] = 1;')->getIssues()) == 1);
    $phlint->enableRule('all');
    assert(count($phlint->analyze('$foo = 2 + "a"; $x = $y; $z[] = 1;')->getIssues()) == 3);
  }

}
