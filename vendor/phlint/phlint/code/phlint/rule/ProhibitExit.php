<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \Phlint;
use \PhpParser\Node\Expr\Exit_;

/**
 * `exit` in PHP is a language construct described in http://php.net/manual/en/function.exit.php
 *
 * It immediately halts the script not rolling up the stack which can sometimes make it difficult
 * to trace.
 *
 * This rule prohibits usage of this feature.
 */
class ProhibitExit {

  function getIdentifier () {
    return 'prohibitExit';
  }

  function getCategories () {
    return [
      'default',
    ];
  }

  function __invoke ($node) {
    if ($node instanceof Exit_) {
      op\metaContext('result')->addIssue($node, 'Using *exit();* is prohibited.');
    }
  }

}
