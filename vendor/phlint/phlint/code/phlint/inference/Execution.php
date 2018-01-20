<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\NodeConcept;

class Execution {

  function getIdentifier () {
    return 'execution';
  }

  function getDependencies () {
    return [
      'nodeRelation',
      'type',
      'value',
    ];
  }

  /**
   * Lookup the node reachability.
   * Note that this call can be significantly expensive.
   * For general purpose it is better to call `::isReachable` which will
   * call lookup implicitly if needed.
   *
   * @param object $node Node whose reachability to lookup.
   * @return bool
   *
   * @internal
   */
  static function lookupIsReachable ($node) {

    $previousNode = inference\NodeRelation::previousNode($node);
    if ($previousNode) {
      if (!inference\Execution::isReachable($previousNode))
        return false;
      if (NodeConcept::isExecutionBarrier($previousNode))
        return false;
    }

    $scopeNode = inference\NodeRelation::scopeNode($node);
    if ($scopeNode) {
      if (!inference\Execution::isReachable($scopeNode))
        return false;
      if (NodeConcept::isConditionalExecutionNode($node) && inference\Value::isFalse($node->cond))
          return false;
    }

    return true;

  }

  /**
   * Get analysis-time known reachability information.
   *
   * @param object $node Node whose reachability to get.
   * @return bool
   */
  static function isReachable ($node) {

    if (!isset(op\metaContext(IIData::class)['isNodeReachable:' . spl_object_hash($node)]))
      op\metaContext(IIData::class)['isNodeReachable:' . spl_object_hash($node)]
        = inference\Execution::lookupIsReachable($node);

    return op\metaContext(IIData::class)['isNodeReachable:' . spl_object_hash($node)];

  }

}
