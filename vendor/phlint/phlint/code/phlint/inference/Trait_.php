<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \PhpParser\Node;

class Trait_ {

  function getIdentifier () {
    return 'trait';
  }

  function getDependencies () {
    return [
      'nodeRelation',
    ];
  }

  /**
   * Should the strict implicit type conversion rules be applied when
   * converting from `$node` types to other types?
   *
   * @param object $node Node whose type to consider.
   * @return bool
   */
  static function useStrictTypeConversion ($node) {
    $sourceNode = inference\NodeRelation::sourceNode($node);
    if (!$sourceNode)
      return false;
    if (!isset(op\metaContext(IIData::class)['hasDeclareStrictTypes:' . spl_object_hash($sourceNode)])) {
      $hasDeclareStrictTypes = false;
      if (count($sourceNode->stmts) > 0 && $sourceNode->stmts[0] instanceof Node\Stmt\Declare_)
        foreach ($sourceNode->stmts[0]->declares as $declareNode)
          if ($declareNode instanceof Node\Stmt\DeclareDeclare && $declareNode->key == 'strict_types'
              && inference\Value::get($declareNode->value) == [['type' => 't_int', 'value' => 1]])
            $hasDeclareStrictTypes = true;
      op\metaContext(IIData::class)['hasDeclareStrictTypes:' . spl_object_hash($sourceNode)] = $hasDeclareStrictTypes;
    }
    return op\metaContext(IIData::class)['hasDeclareStrictTypes:' . spl_object_hash($sourceNode)];
  }

}
