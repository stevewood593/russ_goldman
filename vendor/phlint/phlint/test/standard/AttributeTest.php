<?php

use \phlint\Test as PhlintTest;

class AttributeTest {

  /**
   * Test behavior with incomplete `@param` attribute.
   *
   * @test @internal
   */
  static function unittest_incompleteParam () {
    PhlintTest::assertNoIssues('
      /**
       * @param
       */
      function foor () {}
    ');
  }

}
