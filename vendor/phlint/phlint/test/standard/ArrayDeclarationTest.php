<?php

use \phlint\Test as PhlintTest;

class ArrayDeclarationTest {

  /**
   * @test @internal
   */
  static function unittest_test () {

    PhlintTest::assertIssues('
      $a = [

        0 => 0,
        "0" => 0,
        0.0 => 0,
        0.9 => 0,
        false => 0,

        1 => 1,
        "1" => 1,
        1.1 => 1,
        1.9 => 1,
        true => 1,

        2 => 2,
        2.2 => 2,
        2.9 => 2,
        "2" => 2,

        100 => 100,
        "100" => 100,

        "00" => "00",
        "01" => "01",
        "02" => "02",
        "0.0" => "0.0",
        "1.0" => "1.0",
        "0.1" => "0.1",

        "a" => "a",
        "a" => "a",

        "b" => "b",
        "b" => "b",

        "" => "",
        null => "",

      ];
    ', [
      'Duplicate array key *0* on line 4.',
      'Duplicate array key *0* on line 5.',
      'Duplicate array key *0* on line 6.',
      'Duplicate array key *0* on line 7.',
      'Duplicate array key *1* on line 10.',
      'Duplicate array key *1* on line 11.',
      'Duplicate array key *1* on line 12.',
      'Duplicate array key *1* on line 13.',
      'Duplicate array key *2* on line 16.',
      'Duplicate array key *2* on line 17.',
      'Duplicate array key *2* on line 18.',
      'Duplicate array key *100* on line 21.',
      'Duplicate array key *a* on line 31.',
      'Duplicate array key *b* on line 34.',
      'Duplicate array key ** on line 37.',
    ]);

  }

}
