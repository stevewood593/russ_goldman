<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \Phlint;
use \phlint\inference;
use \phlint\NodeConcept;

/**
 * Prohibit accessing of *undefined variable" before their initialization.
 */
class RequireVariableInitialization {

  function getIdentifier () {
    return 'requireVariableInitialization';
  }

  function getCategories () {
    return [
      'default',
      'strict',
    ];
  }

  function getInferences () {
    return [
      'symbol',
      'symbolInitialization',
    ];
  }

  function visitNode ($node) {

    /**
     * Only variables can be initialized in PHP.
     */
    if (!NodeConcept::isVariableNode($node))
      return;

    foreach (inference\SymbolLink::get($node) as $symbol) {

      $isSymbolInitialized =
        $node->getAttribute('isProbe', false) ||
        in_array($symbol, $node->getAttribute('isInitialization', [])) ||
        in_array($symbol, $node->getAttribute('isInitialized', [])) ||
        false;

      if (!$isSymbolInitialized)
        op\metaContext('result')->addIssue($node, ucfirst(NodeConcept::referencePrint($node)) . ' used before initialized.');

    }

  }

}
