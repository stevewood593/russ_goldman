<?php

namespace phlint\result;

use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class JUnit {

  protected $output = null;

  function __construct ($output) {
    $this->output = $output;
  }

  /** @ExtensionCall("phlint.analysis.begin") */
  function beginAnalysis () {
    rewind($this->output);
    fwrite(
      $this->output,
      '<?xml version="1.0" encoding="UTF-8" ?>' . "\n" .
      '  <testsuites>' . "\n" .
      '    <testsuite name="Phlint">' . "\n" .
      '      <testcase name="OK"></testcase>' . "\n"
    );
  }

  /** @ExtensionCall("phlint.analysis.issueFound") */
  function issueFound ($issue) {
    $testcase = rtrim($issue->message, '.');
    if ($issue->node) {
      $issueLocation = '';
      $scope = $issue->node->getAttribute('scope', '');
      if (!isset(op\metaContext('code')->data['symbols'][$scope]['phpId']))
        $scope = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $scope), '.');
      if (isset(op\metaContext('code')->data['symbols'][$scope]['phpId'])) {
        while ($scope) {
          if (strpos(\phlint\inference\Symbol::unqualified($scope), 'f_anonymous_') !== 0)
            if (in_array(\phlint\inference\Symbol::symbolIdentifierGroup($scope), ['class', 'function', 'namespace']))
              break;
          $scope = \phlint\inference\Scope::parent($scope);
        }
        if (!isset(op\metaContext('code')->data['symbols'][$scope]['phpId']))
          $scope = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $scope), '.');
        $symbolIdentifierGroup = \phlint\inference\Symbol::symbolIdentifierGroup($scope);
        if (strpos(op\metaContext('code')->data['symbols'][$scope]['phpId'], '::') !== false)
          $symbolIdentifierGroup = 'method';
        $issueLocation = trim(
          $symbolIdentifierGroup . ' ' . ltrim(op\metaContext('code')->data['symbols'][$scope]['phpId'], '\\')
        );
      }
      if (!$issueLocation)
        $issueLocation = 'file ' . trim($issue->node->getAttribute('path', ''));
      if ($issueLocation)
        $testcase = $testcase . ' in ' . $issueLocation;
    }
    fwrite(
      $this->output,
      '      <testcase name="' . self::xmlEncode($testcase) . '">' . "\n" .
      '        <failure message="' . self::xmlEncode($issue->getMessage()) . '">' .
                self::xmlEncode(rtrim($issue->getTraces(), "\n")) .
              '</failure>' . "\n" .
      '      </testcase>' . "\n"
    );
  }

  /** @ExtensionCall("phlint.analysis.end") */
  function endAnalysis () {
    fwrite(
      $this->output,
      '  </testsuite>' . "\n" .
      '</testsuites>' . "\n"
    );
  }

  static function xmlEncode ($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_DISALLOWED);
  }

}
