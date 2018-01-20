<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \Phlint;
use \PhpParser\Node;
use \PhpParser\Node\Expr\Variable;
use \PhpParser\NodeVisitorAbstract;
use \PhpParser\PrettyPrinter\Standard as PrettyPrinter;

/**
 * Variable Variables is a PHP language feature described in http://php.net/manual/en/language.variables.variable.php
 *
 * Usage of this feature allows very dynamic code which easily becomes very unreadable and
 * it can be very difficult to verify.
 *
 * This rule prohibits usage of this feature.
 */
class ProhibitVariableVariables extends NodeVisitorAbstract {

  function getIdentifier () {
    return 'prohibitVariableVariables';
  }

  function getCategories () {
    return [
      'default',
    ];
  }

  function enterNode(Node $node) {

    if (($node instanceof Variable) && !is_string($node->name)) {
      $prettyPrinter = new PrettyPrinter();
      $expression = $prettyPrinter->prettyPrintExpr($node->name);
      op\metaContext('result')->addIssue($node, 'Using variable variable *$' . $expression . '* is prohibited.');
    }

  }

}
