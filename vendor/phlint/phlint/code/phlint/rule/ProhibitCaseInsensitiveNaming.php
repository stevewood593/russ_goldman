<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \PhpParser\Node;

/**
 * @see /documentation/rule/prohibitCaseInsensitiveNaming.md
 */
class ProhibitCaseInsensitiveNaming {

  function getIdentifier () {
    return 'prohibitCaseInsensitiveNaming';
  }

  function getCategories () {
    return [
      'default',
      'tidy',
    ];
  }

  function getInferences () {
    return [
      'symbol',
    ];
  }

  function visitNode ($node) {

    if (NodeConcept::isInvocationNode($node))
      $this->enforceRule($node, inference\SymbolLink::get($node), $node->name);

    if ($node instanceof Node\Expr\New_)
      $this->enforceRule($node, inference\SymbolLink::get($node), $node->class);

    if ($node instanceof Node\Stmt\Catch_)
      foreach ($node->types as $type)
        $this->enforceRule($node, inference\SymbolLink::get($type), $type);

  }

  function enforceRule ($node, $symbols, $nameNode) {

    foreach ($symbols as $symbol)
      if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
        foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode) {
          if (!NodeConcept::isNamedNode($definitionNode))
            continue;
          foreach (array_filter($nameNode instanceof Node\Expr\Variable ? array_map(function ($v) { if ($v['type'] != 't_string') return ''; return $v['value']; }, inference\Value::get($nameNode)) : [$nameNode]) as $nn) {
          $name = NodeConcept::displayPrint($nn);
          $definitionName = NodeConcept::displayPrint($definitionNode->name);
          $checkLength = min(strlen($name), strlen($definitionName));
          if ($checkLength > 0 && strtolower(substr($name, -$checkLength)) == strtolower(substr($definitionName, -$checkLength)) && substr($name, -$checkLength) != substr($definitionName, -$checkLength))
            op\metaContext('result')->addIssue(
              $node,
              ucfirst(NodeConcept::referencePrint($node)) . ' is not using the same letter casing as '
                . trim(NodeConcept::constructTypeName($definitionNode) . ' *')
                . ltrim(op\metaContext('code')->data['symbols'][$symbol]['phpId'], '\\') . '*.'
            );
          }

        }

  }

}
