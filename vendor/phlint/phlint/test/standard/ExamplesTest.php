<?php

use \phlint\Test as PhlintTest;

/**
 * Tests of various code snippets given around
 * as example of how Phlint works.
 */
class ExamplesTest {

  /** @test @internal */
  static function unittest_example1 () {
    PhlintTest::assertIssues('<?php

      class GreetingFactory {
        static function createRandomGreeter () {
          return (rand(0, 1) ? new Greeter() : new PoliteGreeter());
        }
      }

      class Greeter {
        function greet ($whom) {
          echo "Hello " . ucfirst($whom->getFirstName());
        }
      }

      class PoliteGreeter {
        function greet ($whom) {
          if ($whom instanceof User) {
            echo "Hello " . ucfirst($whom->getFirstName()) . ", have a great day!";
            return;
          }
          echo "Hello " . ucfirst($whom) . ", have a great day!";
        }
      }

      class UserRepository {
        function getRandomUser () {
          if (rand(0, 1))
            $user = "Luka";
          else
            $user = new User();
          return $user;
        }
      }

      class User {
        function getFirstName () {
          return "Luka";
        }
      }

      class PlanetRepository {}

      $greeter = GreetingFactory::createRandomGreeter();
      $user = (rand(0, 1) ? new UserRepository() : new PlanetRepository())->getRandomUser();

      $greeter->greet($user);

    ', [
      '
        Unable to invoke undefined *PlanetRepository::getRandomUser* for the expression
          *(rand(0, 1) ? new UserRepository() : new PlanetRepository())->getRandomUser()*
          on line 44.
      ',
      '
        Unable to invoke undefined *string::getFirstName*
            for the expression *$whom->getFirstName()* on line 11.
          Trace #1: Method *function greet("Luka")* specialized
            for the expression *$greeter->greet($user)* on line 46.
      ',
    ]);
  }

  /** @test @internal */
  static function unittest_example2 () {
    PhlintTest::assertIssues('<?php

      class GreetingFactory {
        static function createGreeter () {

          /** Quick fix for a special greeting message on Sundays. */
          if (date("l") == "Sunday")
            return new SundayGreeter();

          return new Greeter();
        }
      }

      class Greeter {
        function greet ($whom) {
          echo "Hello " . $whom->getFirstName();
        }
      }

      class SundayGreeter {
        function greet ($whom) {
          echo "Hello " . $whom . ", have a great day!";
        }
      }

      class UserRepository {
        function getRandomUser () {
          if (rand(0, 1))
            return "Luka";
          else
            return new User();
        }
      }

      class User {
        function getFirstName () {
          return "Luka";
        }
      }

      class PlanetRepository {}

      $greeter = GreetingFactory::createGreeter();
      $user = (rand(0, 1) ? new UserRepository() : new PlanetRepository())->getRandomUser();

      $greeter->greet($user);

    ', [
      '
        Unable to invoke undefined *PlanetRepository::getRandomUser* for the expression
          *(rand(0, 1) ? new UserRepository() : new PlanetRepository())->getRandomUser()* on line 44.
      ',
      '
        Provided variable *$whom* of type *User* is not compatible in the expression
            *"Hello " . $whom* on line 22.
          Trace #1: Method *function greet(string|User $whom)* specialized
            for the expression *$greeter->greet($user)* on line 46.
      ',
      '
        Unable to invoke undefined *string::getFirstName*
            for the expression *$whom->getFirstName()* on line 16.
          Trace #1: Method *function greet(string|User $whom)* specialized
            for the expression *$greeter->greet($user)* on line 46.
      ',
    ]);
  }

}
