<?php

namespace phlint\rule;

use \ArrayObject;
use \luka8088\phops as op;
use \Phlint;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \phlint\Result;
use \PhpParser\Node;

/**
 * Makes sure that @isolated code is indeed isolated.
 *
 * @see /documentation/attribute/isolated.md
 */
class EnforceIsolatedAttribute {

  function getIdentifier () {
    return 'enforceIsolatedAttribute';
  }

  function getCategories () {
    return [
      'attribute',
      'default',
    ];
  }

  function getInferences () {
    return [
      'attribute',
      'isolation',
      'purity',
    ];
  }

  function visitNode ($node) {

    if (!inference\Isolation::isIsolated($node) && !inference\Purity::isPure($node))
      return;

    if ($node instanceof Node\Expr\FuncCall) {
      $isIsolated = true;
      foreach (inference\SymbolLink::get($node) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $declarationNode)
            if (!inference\Isolation::isIsolated($declarationNode))
              $isIsolated = false;
      if (!$isIsolated)
        op\metaContext('result')->addIssue(
          $node,
          'Isolation breach: Calling non-isolated function *' . NodeConcept::displayPrint($node) . '*.'
        );
    }

    if ($node instanceof Node\Expr\StaticPropertyFetch)
      op\metaContext('result')->addIssue(
        $node,
        'Isolation breach: Accessing global variable *' . NodeConcept::displayPrint($node) . '*.'
      );

    if (($node instanceof Node\Expr\Variable) && in_array($node->name, phpLanguage\Fixture::$superglobals))
      op\metaContext('result')->addIssue(
        $node,
        'Isolation breach: Accessing superglobal *' . NodeConcept::displayPrint($node) . '*.'
      );

    if (($node instanceof Node\Scalar\MagicConst\Dir) ||
        ($node instanceof Node\Scalar\MagicConst\File) ||
        ($node instanceof Node\Scalar\MagicConst\Line))
      op\metaContext('result')->addIssue(
        $node,
        'Isolation breach: Using magic constant *' . NodeConcept::displayPrint($node) . '*.'
      );

    if ($node instanceof Node\Stmt\StaticVar)
      op\metaContext('result')->addIssue(
        $node,
        'Isolation breach: Declaring static variable *' . NodeConcept::displayPrint($node) . '*.'
      );

    foreach (inference\Attribute::get($node) as $attribute)
      if ($attribute instanceof Node\Expr\New_ &&
          count($attribute->args) >= 1 &&
          inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => '__isolationBreach']]) {
        op\metaContext('result')->addIssue($node, 'Isolation breach: ' . $attribute->args[1]->value->items[0]->value->value);
      }

  }

}
