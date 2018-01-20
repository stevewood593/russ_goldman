<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \PhpParser\Node;

/**
 * Isolation inference.
 *
 * @see /documentation/glossary/isolation.md
 */
class Isolation {

  function getIdentifier () {
    return 'isolation';
  }

  function getDependencies () {
    return [
      'attribute',
      'execution',
      'nodeRelation',
      'value',
    ];
  }

  /**
   * Get node analysis-time known isolation information.
   *
   * @param object $node Node whose isolation to get.
   * @return boolean
   */
  static function isIsolated ($node) {

    if (!isset(op\metaContext(IIData::class)['nodeIsIsolated:' . spl_object_hash($node)])) {

      $isIsolated = false;

      if (inference\Execution::isReachable($node)) {

        $contextNode = inference\NodeRelation::contextNode($node);

        if ($contextNode) {

          foreach (inference\Attribute::get($contextNode) as $attribute)
            if ($attribute instanceof Node\Expr\New_ &&
                count($attribute->args) > 0 &&
                inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'isolated']])
              $isIsolated = true;

          if (inference\Isolation::isIsolated($contextNode))
            $isIsolated = true;

        }

      }

      op\metaContext(IIData::class)['nodeIsIsolated:' . spl_object_hash($node)] = $isIsolated;

    }

    return op\metaContext(IIData::class)['nodeIsIsolated:' . spl_object_hash($node)];

  }

}
