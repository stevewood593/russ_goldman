<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \PhpParser\Node;

/**
 * @see /documentation/rule/prohibitRedefining.md
 */
class ProhibitRedefining {

  function getIdentifier () {
    return 'prohibitRedefining';
  }

  function getCategories () {
    return [
      'default',
      'phpFatal',
    ];
  }

  function getInferences () {
    return [
      'symbol',
    ];
  }

  function visitNode ($node) {

    if (NodeConcept::isDefinitionNode($node) && !NodeConcept::isNamespaceNode($node)) {

      $definitionNodes = [];

      foreach (inference\SymbolLink::getUnmangled($node) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode)
            if (!$definitionNode->getAttribute('isSpecialization', false))
            if (!$definitionNode->getAttribute('isSpecializationTemp', false))
            if (!$definitionNode->getAttribute('isConditionalExecutionNode', false))
              $definitionNodes[] = $definitionNode;

      if (!($node instanceof Node\Expr\Closure) && count($definitionNodes) > 1)
        op\metaContext('result')->addIssue(
          $node,
          'Having multiple definitions for *' . NodeConcept::sourcePrint($node->name) . '* is prohibited.'
        );
    }

  }

}
