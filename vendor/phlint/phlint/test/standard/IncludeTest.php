<?php

use \phlint\autoload\Composer;
use \phlint\Test as PhlintTest;

class IncludeTest {

  /**
   * Regression test for the issue:
   *   Unable to invoke undefined *bar()* on line 2.
   *
   * @test @internal
   */
  static function unittest_phpInclude () {

    PhlintTest::mockFilesystem(sys_get_temp_dir() . '/m4zmn0eprwhbyjbnrkyuu7do/', [
      '/vendor/composer/autoload_files.php' =>
        '<?php return ' . var_export([
          sys_get_temp_dir() . '/m4zmn0eprwhbyjbnrkyuu7do/vendor/company/project/A.php',
        ], true) . ';',
      '/vendor/company/project/A.php' => '<?php
        require __dir__ . "/B.php";
        require __dir__ . "/X.php";
        function foo () {}
      ',
      '/vendor/company/project/B.php' => '<?php
        function bar () {}
      ',
    ]);

    $linter = PhlintTest::create();

    $linter[] = new Composer(sys_get_temp_dir() . '/m4zmn0eprwhbyjbnrkyuu7do/composer.json');

    PhlintTest::assertNoIssues($linter->analyze('
      foo();
      bar();
    '));

  }

  /**
   * Test using a short open tag.
   *
   * @test @internal
   */
  static function unittest_shortOpenTag () {

    PhlintTest::assertIssues(trim('
      <?
      function foo () {}
    '), [
      'Using short open tag is prohibited on line 2.',
    ]);

  }

}
