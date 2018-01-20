<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\NodeConcept;
use \phlint\SourceNode;
use \PhpParser\Node;

class NodeRelation {

  function getIdentifier () {
    return 'nodeRelation';
  }

  function getPass () {
    return 10;
  }

  function beforeTraverse ($nodes) {

    if (!isset(op\metaContext(IIData::class)['memo:contextNodeStack']))
      op\metaContext(IIData::class)['memo:contextNodeStack'] = [];

    if (!isset(op\metaContext(IIData::class)['memo:scopeNodeStack']))
      op\metaContext(IIData::class)['memo:scopeNodeStack'] = [];

    NodeRelation::inferNodeSequenceRelations($nodes);

  }

  function enterNode ($node) {

    if (NodeConcept::isExecutionContextNode($node))
      op\metaContext(IIData::class)['memo:contextNodeStack'][] = $node;

    if (NodeConcept::isScopeNode($node) && !NodeConcept::isConditionalExecutionNode($node))
      op\metaContext(IIData::class)['memo:scopeNodeStack'][] = $node;

    if ($node instanceof SourceNode) {
      if (!isset(op\metaContext(IIData::class)['memo:sourceNodeStack']))
        op\metaContext(IIData::class)['memo:sourceNodeStack'] = [];
      op\metaContext(IIData::class)['memo:sourceNodeStack'][] = $node;
    }

  }

  function leaveNode ($node) {

    if (count(op\metaContext(IIData::class)['memo:contextNodeStack']) > 0
        && $node === end(op\metaContext(IIData::class)['memo:contextNodeStack']))
      array_pop(op\metaContext(IIData::class)['memo:contextNodeStack']);

    if (count(op\metaContext(IIData::class)['memo:scopeNodeStack']) > 0
        && $node === end(op\metaContext(IIData::class)['memo:scopeNodeStack']))
      array_pop(op\metaContext(IIData::class)['memo:scopeNodeStack']);

    if ($node instanceof SourceNode && $node === end(op\metaContext(IIData::class)['memo:sourceNodeStack'])) {
      array_pop(op\metaContext(IIData::class)['memo:sourceNodeStack']);
    }

  }

  function visitNode ($node) {

    if (NodeConcept::isScopeNode($node) && NodeConcept::isConditionalExecutionNode($node))
      op\metaContext(IIData::class)['memo:scopeNodeStack'][] = $node;

    if (count(op\metaContext(IIData::class)['memo:contextNodeStack']) > 0)
      if (end(op\metaContext(IIData::class)['memo:contextNodeStack']) === $node)
        op\metaContext(IIData::class)['contextNode:' . spl_object_hash($node)]
          = count(op\metaContext(IIData::class)['memo:contextNodeStack']) > 1
          ? current(array_slice(op\metaContext(IIData::class)['memo:contextNodeStack'], -2, 1))
          : null;
      else
        op\metaContext(IIData::class)['contextNode:' . spl_object_hash($node)]
          = end(op\metaContext(IIData::class)['memo:contextNodeStack']);

    if (count(op\metaContext(IIData::class)['memo:scopeNodeStack']) > 0)
      op\metaContext(IIData::class)['scopeNode:' . spl_object_hash($node)]
        = end(op\metaContext(IIData::class)['memo:scopeNodeStack']) === $node
        ? (
          count(op\metaContext(IIData::class)['memo:scopeNodeStack']) > 1
            ? current(array_slice(op\metaContext(IIData::class)['memo:scopeNodeStack'], -2, 1))
            : null
          )
        : end(op\metaContext(IIData::class)['memo:scopeNodeStack'])
      ;

    if (count(op\metaContext(IIData::class)['memo:sourceNodeStack']) > 0)
      op\metaContext(IIData::class)['sourceNode:' . spl_object_hash($node)] =
        end(op\metaContext(IIData::class)['memo:sourceNodeStack']);

    if ($node instanceof Node\Stmt\Function_)
      NodeRelation::inferNodeSequenceRelations($node->stmts);

  }

  static function inferNodeSequenceRelations ($nodeSequence) {
    $previousNode = null;
    foreach ($nodeSequence as $node) {
      if ($previousNode) {
        op\metaContext(IIData::class)['previousNode:' . spl_object_hash($node)] = $previousNode;
        op\metaContext(IIData::class)['followingNode:' . spl_object_hash($previousNode)] = $node;
      }
      $previousNode = $node;
    }
  }

  /**
   * Get analysis-time known previous node.
   * Node is considered a previous one if it is right before the current node
   * in the AST and it is in the same scope.
   *
   * @param object $object Node whose previous node to get.
   * @return Node|null
   */
  static function previousNode ($object) {
    $metaContextKey = 'previousNode:' . spl_object_hash($object);
    if (isset(op\metaContext(IIData::class)[$metaContextKey]) && op\metaContext(IIData::class)[$metaContextKey])
      return op\metaContext(IIData::class)[$metaContextKey];
    return null;
  }

  /**
   * Get analysis-time known following node.
   * Node is considered a following one if it is right after the current node
   * in the AST and it is in the same scope.
   *
   * @param object $object Node whose following node to get.
   * @return Node|null
   */
  static function followingNode ($object) {
    $metaContextKey = 'followingNode:' . spl_object_hash($object);
    if (isset(op\metaContext(IIData::class)[$metaContextKey]) && op\metaContext(IIData::class)[$metaContextKey])
      return op\metaContext(IIData::class)[$metaContextKey];
    return null;
  }

  /**
   * Get analysis-time known scope node.
   * Node is considered a scope one if it is is the node which holes the current scope.
   *
   * @param object $object Node whose scope node to get.
   * @return Node|null
   */
  static function scopeNode ($object) {
    $metaContextKey = 'scopeNode:' . spl_object_hash($object);
    if (isset(op\metaContext(IIData::class)[$metaContextKey]) && op\metaContext(IIData::class)[$metaContextKey])
      return op\metaContext(IIData::class)[$metaContextKey];
    return null;
  }

  /**
   * Get analysis-time known context node.
   *
   * @param object $object Node whose context node to get.
   * @return Node|null
   */
  static function contextNode ($object) {
    $metaContextKey = 'contextNode:' . spl_object_hash($object);
    if (isset(op\metaContext(IIData::class)[$metaContextKey]) && op\metaContext(IIData::class)[$metaContextKey])
      return op\metaContext(IIData::class)[$metaContextKey];
    return null;
  }

  /**
   * Get analysis-time known source node.
   *
   * @param object $object Node whose source node to get.
   * @return Node|null
   */
  static function sourceNode ($object) {
    $metaContextKey = 'sourceNode:' . spl_object_hash($object);
    if (isset(op\metaContext(IIData::class)[$metaContextKey]) && op\metaContext(IIData::class)[$metaContextKey])
      return op\metaContext(IIData::class)[$metaContextKey];
    return null;
  }

}
