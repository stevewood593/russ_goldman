<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;

/**
 * @see /documentation/rule/evaluateAsserts.md
 */
class EvaluateAsserts {

  function getIdentifier () {
    return 'evaluateAsserts';
  }

  function getCategories () {
    return [
      'default',
      'strict',
    ];
  }

  function getInferences () {
    return [
      'type',
      'value',
    ];
  }

  function visitNode ($node) {

    if ($node->getAttribute('isGenerated', false))
      return;

    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_assert'])
      if (count($node->args) > 0)
        foreach (inference\Value::get($node->args[0]) as $value)
          if ($value['type'] == 't_bool' && !$value['value'])
            op\metaContext('result')->addIssue(
              $node,
              'Assertion failure for the ' . NodeConcept::referencePrint($node) . '.'
            );

  }

}
