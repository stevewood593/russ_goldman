<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \PhpParser\Node;

/**
 * Purity inference.
 *
 * @see /documentation/glossary/purity.md
 */
class Purity {

  function getIdentifier () {
    return 'purity';
  }

  function getDependencies () {
    return [
      'attribute',
      'execution',
      'isolation',
      'nodeRelation',
      'value',
    ];
  }

  /**
   * Get node analysis-time known purity information.
   *
   * @param object $node Node whose purity to get.
   * @return boolean
   */
  static function isPure ($node) {

    if (!isset(op\metaContext(IIData::class)['nodeIsPure:' . spl_object_hash($node)])) {

      $isPure = false;

      if (inference\Execution::isReachable($node)) {

        $contextNode = inference\NodeRelation::contextNode($node);

        if ($contextNode) {

          foreach (inference\Attribute::get($contextNode) as $attribute)
            if ($attribute instanceof Node\Expr\New_ &&
                count($attribute->args) > 0 &&
                inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'pure']])
              $isPure = true;

          if (inference\Purity::isPure($contextNode))
            $isPure = true;

        }

      }

      op\metaContext(IIData::class)['nodeIsPure:' . spl_object_hash($node)] = $isPure;

    }

    return op\metaContext(IIData::class)['nodeIsPure:' . spl_object_hash($node)];

  }

}
