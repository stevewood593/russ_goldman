<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \PhpParser\Node;

/**
 * Prohibit initializing variables by using the append operator.
 *
 * For example:
 *   $x = ['a' => 'b']; // Ok.
 *   $x[] = 'b'; // Prohibited.
 *
 */
class ProhibitVariableAppendInitialization {

  function getIdentifier () {
    return 'prohibitVariableAppendInitialization';
  }

  function getInferences () {
    return [
      'symbol',
      'symbolInitialization',
    ];
  }

  function visitNode($node) {

    if (($node instanceof Node\Expr\Assign) || ($node instanceof Node\Expr\AssignRef))
      if ($node->var instanceof Node\Expr\ArrayDimFetch)
        if (NodeConcept::isVariableNode($node->var->var)) {
          foreach (inference\SymbolLink::get($node->var->var) as $symbol) {
            if (in_array($symbol, $node->var->var->getAttribute('isInitialization', [])))
              op\metaContext('result')->addIssue(
                $node,
                ucfirst(NodeConcept::referencePrint($node->var->var)) . ' initialized using append operator.'
              );
          }
        }

  }

}
