<?php

use \phlint\Test as PhlintTest;

class VariableAppendInitializationTest {

  /**
   * General rule test.
   *
   * @test @internal
   */
  static function unittest_test () {

    PhlintTest::assertNoIssues('
      function f () {
        $a = [];
        $a[] = 2;
      }
    ');

    PhlintTest::assertNoIssues('
      function f ($a) {
        $a[] = 2;
      }
    ');

    PhlintTest::assertIssues('
      function f () {
        $a[] = 2;
        $a = [];
      }
    ', [
      'Variable *$a* initialized using append operator on line 2.',
    ]);

    PhlintTest::assertIssues('
      function f () {
        if (rand(0, 1))
          $a[] = 2;
        $a = [];
      }
    ', [
      'Variable *$a* initialized using append operator on line 3.',
    ]);

  }

}
