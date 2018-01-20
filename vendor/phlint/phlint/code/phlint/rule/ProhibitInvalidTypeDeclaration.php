<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \PhpParser\Node;

/**
 * @see /documentation/rule/prohibitInvalidTypeDeclaration.md
 */
class ProhibitInvalidTypeDeclaration {

  function getIdentifier () {
    return 'prohibitInvalidTypeDeclaration';
  }

  function getCategories () {
    return [
      'default',
    ];
  }

  function getInferences () {
    return [
      'symbol',
      'templateSpecialization',
      'type',
    ];
  }

  function visitNode ($node) {

    if ($node instanceof Node\Stmt\Function_) {
      $this->checkType($node->returnType, $node);
      foreach ($node->params as $argument)
        $this->checkType($argument->type, $argument);
    }

  }

  function checkType ($type, $node) {

    if (is_string($type) && !in_array(strtolower($type), phpLanguage\Fixture::$typeDeclarationNonClassKeywords))
      op\metaContext('result')->addIssue(
        $node,
        'Invalid type *' . $type . '* in declaration.'
      );

    if (is_object($type)) {
      $definitionNodes = [];
      foreach (inference\SymbolLink::get($type) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode)
              $definitionNodes[] = $definitionNode;
      if (count($definitionNodes) == 0)
        op\metaContext('result')->addIssue(
          $node,
          'Type declaration requires undefined ' . NodeConcept::referencePrint($type) . '.'
        );
    }

  }

}
