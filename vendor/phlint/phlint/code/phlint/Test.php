<?php

namespace phlint;

use \Phlint;
use \RecursiveDirectoryIterator;
use \RecursiveIteratorIterator;

class Test {

  /** @internal */
  public static $importStandardDefinitions = true;

  static function create () {
    $phlint = Phlint::create();
    $phlint->importStandardDefinitions = self::$importStandardDefinitions;
    $phlint->enableRule('all');
    return $phlint;
  }

  static function assertIssues ($code, $expectedIssues = []) {
    $result = is_string($code) ? self::create()->analyze($code) : $code;
    $minimizeMessage = function ($message) { return trim(preg_replace('/(?is)[ \t\r\n]+/', ' ', $message)); };
    $missingIssues = [];
    $unexpectedIssues = [];
    foreach ($expectedIssues as $expectedIssue) {
      $expectedIssueFound = false;
      foreach ($result->getIssues() as $issue)
        if ($minimizeMessage($issue) == $minimizeMessage($expectedIssue))
          $expectedIssueFound = true;
      if (!$expectedIssueFound)
        $missingIssues[] = $expectedIssue;
    }
    foreach ($result->getIssues() as $issue) {
      $unexpectedIssueFound = true;
      foreach ($expectedIssues as $expectedIssue)
        if ($minimizeMessage($issue) == $minimizeMessage($expectedIssue))
          $unexpectedIssueFound = false;
      if ($unexpectedIssueFound)
        $unexpectedIssues[] = $issue;
    }
    assert(
      count(array_unique($result->getIssues())) == count($expectedIssues) && count($missingIssues) == 0,
      count(array_unique($result->getIssues())) . " issues found when " . count($expectedIssues) . " expected.\n" .
      (count($missingIssues) > 0 ? 'Expected issue(s) not found: ' . implode("\n", $missingIssues) . "\n" : '') .
      (count($unexpectedIssues) > 0 ? 'Unexpected issues(s) found: ' . implode("\n", $unexpectedIssues) . "\n" : '') .
      "Result:\n" . $result->toString() . (is_string($code) ? "\n\nFor code:\n" . $code . "\n" : '')
    );
  }

  static function assertNoIssues ($code) {
    assert(count(func_get_args()) == 1);
    self::assertIssues($code, []);
  }

  static function assertEquals ($actual, $expected) {
    $minimizeMessage = function ($message) { return trim(preg_replace('/(?is)[ \t\r\n]+/', ' ', $message)); };
    assert(
      $minimizeMessage($actual) == $minimizeMessage($expected),
      "Actual:\n" . $actual . "\n\nExpected:\n" . $expected . "\n"
    );
  }

  static function mockFilesystem ($path, $files) {

    if (file_exists($path)) {

      $existingFiles = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
      );

      foreach ($existingFiles as $existingFile) {
        if ($existingFile->isDir())
          rmdir($existingFile->getRealPath());
        else
          unlink($existingFile->getRealPath());
      }

    }

    foreach ($files as $file => $contents) {
      if (!file_exists($path . '/' . dirname($file)))
        mkdir($path . '/' . dirname($file), 0777, true);
      file_put_contents($path . '/' . $file, $contents);
    }

  }

}
