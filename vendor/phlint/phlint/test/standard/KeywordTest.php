<?php

use \phlint\Test as PhlintTest;

class KeywordTest {

  /**
   * "resource" is not a keyword in PHP although it is used
   * as keyword in the documentation.
   * @test @internal
   */
  static function unittest_foreachPlus () {

    PhlintTest::assertIssues('
      $x = new resource();
    ', [
      'Unable to invoke undefined *resource* for the expression *new resource()* on line 1.',
    ]);

    PhlintTest::assertNoIssues('
      class resource {}
      function foo (resource $x) {}
      foo(new resource());
    ');

  }

}
