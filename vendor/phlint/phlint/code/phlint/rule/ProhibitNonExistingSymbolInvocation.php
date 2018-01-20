<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \PhpParser\Node;

/**
 * @see /documentation/rule/prohibitNonExistingSymbolInvocation.md
 */
class ProhibitNonExistingSymbolInvocation {

  function getIdentifier () {
    return 'prohibitNonExistingSymbolInvocation';
  }

  function getCategories () {
    return [
      'default',
      'fatal',
    ];
  }

  function getInferences () {
    return [
      'symbol',
      'templateSpecialization',
      'type',
      'value',
    ];
  }

  function visitNode ($node) {

    if ($node instanceof Node\Expr\FuncCall)
      $this->enforceRule($node, $node);

    if ($node instanceof Node\Expr\MethodCall)
      $this->enforceRule($node, $node);

    if ($node instanceof Node\Expr\New_)
      $this->enforceRule($node, $node);

    if ($node instanceof Node\Expr\StaticCall)
      $this->enforceRule($node, $node);

    if ($node instanceof Node\Stmt\Catch_)
      foreach ($node->types as $type)
        $this->enforceRule($node, $type);

  }

  function enforceRule ($expressionNode, $symbolNode) {

    $symbols = inference\SymbolLink::get($symbolNode);

    foreach ($symbols as $symbol) {

      $declarationNodes = [];

      if (isset(op\metaContext('code')->data['symbols'][$symbol]['definitionNodes']))
        foreach (op\metaContext('code')->data['symbols'][$symbol]['definitionNodes'] as $declarationNode)
          $declarationNodes[] = $declarationNode;

      if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
        foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $declarationNode)
          $declarationNodes[] = $declarationNode;

      if (isset(op\metaContext('code')->data['symbols'][$symbol]) || isset(op\metaContext('code')->data['symbols'][$symbol])) {

        if (count($declarationNodes) == 0) {

          $symbolDisplay = isset(op\metaContext('code')->data['symbols'][$symbol]['phpId']) ? op\metaContext('code')->data['symbols'][$symbol]['phpId'] : (isset(op\metaContext('code')->data['symbols']['phpId']) ? op\metaContext('code')->data['symbols']['phpId'] : $symbol);
          $symbolDisplay = strpos($symbolDisplay, 't_') === 0 ? substr($symbolDisplay, 2) : $symbolDisplay;
          $symbolDisplay = ltrim($symbolDisplay, '\\');
          $expressionDisplay = NodeConcept::displayPrint($expressionNode);

          // Todo: Rewrite
          if (strpos($symbolDisplay . '::', '::') === 0)
            continue;
          if (strpos($symbolDisplay . '::', 'parent::') !== false)
            continue;
          if (strpos($symbolDisplay . '::', 'self::') !== false)
            continue;
          if (strpos($symbolDisplay . '::', 'static::') !== false)
            continue;

          if ($symbolDisplay != $expressionDisplay && $symbolDisplay . '()' != ltrim($expressionDisplay, '\\'))
            $message = 'Unable to invoke undefined *' . $symbolDisplay . '*' .
              ' for the ' . NodeConcept::referencePrint($expressionNode) . '.';
          else
            $message = 'Unable to invoke undefined *' . $expressionDisplay . '*.';

          op\metaContext('result')->addIssue($expressionNode, $message);

        }

      }

    }

  }

}
