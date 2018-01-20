<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;

/**
 * @see /documentation/rule/evaluateConstraints.md
 */
class EvaluateConstraints {

  function getIdentifier () {
    return 'evaluateConstraints';
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
      'constraint',
    ];
  }

  function visitNode ($node) {

    if (NodeConcept::isExecutionContextNode($node))
      foreach ($node->stmts as $statement)
        if ($statement->getAttribute('isConstraint', false))
          foreach (inference\Value::get($statement->args[0]) as $value)
            if ($value['type'] == 't_bool' && !$value['value'])
              op\metaContext('result')->addIssue(
                $statement,
                ucfirst(NodeConcept::referencePrint($statement)) . ' failed for the ' .
                NodeConcept::referencePrint($node) . '.'
              );

  }

}
