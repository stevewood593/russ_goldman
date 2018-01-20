<?php

namespace phlint;

class NodeTraverser {

  /**
   * Traverse sub-nodes in the order predefined here which better reflects
   * the order in which code gets executed and therefor is more suited for
   * analysis.
   * In the list of sub-nodes "self" represents the parent node and indicate
   * when the visit to parent node should occur.
   *
   * For example, when traversing a function it makes more sense to first
   * visit the parameters, then the function itself, and then the function
   * body (statements).
   */
  static $simulationTraverseOrder = [
    'PhpParser\Node\Expr\ArrayItem' => ['key', 'value', 'self'],
    'PhpParser\Node\Expr\Assign' => ['expr', 'var', 'self'],
    'PhpParser\Node\Expr\BinaryOp\BooleanAnd' => ['left', 'self', 'right'],
    'PhpParser\Node\Expr\Closure' => ['params', 'uses', 'self', 'stmts'],
    'PhpParser\Node\Expr\FuncCall' => ['name', 'args', 'self'],
    'PhpParser\Node\Expr\MethodCall' => ['var', 'name', 'args', 'self'],
    'PhpParser\Node\Expr\StaticCall' => ['class', 'name', 'args', 'self'],
    'PhpParser\Node\Expr\Ternary' => ['cond', 'self', 'if', 'else'],
    'PhpParser\Node\Stmt\Catch_' => ['types', 'var', 'self', 'stmts'],
    'PhpParser\Node\Stmt\Class_' => ['name', 'extends', 'implements', 'self', 'stmts'],
    'PhpParser\Node\Stmt\ClassMethod' => ['name', 'params', 'self', 'stmts'],
    'PhpParser\Node\Stmt\ElseIf_' => ['cond', 'self', 'stmts'],
    'PhpParser\Node\Stmt\Foreach_' => ['expr', 'keyVar', 'valueVar', 'self', 'stmts'],
    'PhpParser\Node\Stmt\Function_' => ['name', 'params', 'returnType', 'self', 'stmts'],
    'PhpParser\Node\Stmt\If_' => ['cond', 'self', 'stmts', 'elseifs', 'else'],
    'PhpParser\Node\Stmt\Interface_' => ['name', 'extends', 'self', 'stmts'],
    'PhpParser\Node\Stmt\Namespace_' => ['name', 'self', 'stmts'],
    'PhpParser\Node\Stmt\Trait_' => ['name', 'self', 'stmts'],
  ];

  /**
   * @param mixed $node Node or AST to traverse.
   * @param array $visitors
   */
  static function traverse ($node, $visitors) {

    $traverseNode = function ($node) use (&$traverseNode, $visitors) {

      if (is_array($node)) {
        foreach ($node as $subNode)
          $traverseNode($subNode);
        return;
      }

      if (!is_object($node))
        return;

      foreach ($visitors as $visitor) {

        // Provide compatibility for \PhpParser\NodeVisitor
        if (method_exists($visitor, 'enterNode'))
          $visitor->enterNode($node);

      }

      list($enterNodeSubNodes, $leaveNodeSubNodes) = self::getSimulationTraverseOrder($node);

      foreach ($enterNodeSubNodes as $subNodeName)
        if (is_object($node->$subNodeName) || (is_array($node->$subNodeName) && count($node->$subNodeName) > 0))
          $traverseNode($node->$subNodeName);

      foreach ($visitors as $visitor) {

        /**
         * Node visits are done according to "self" in $simulationTraverseOrder
         * or when leaving a node by default.
         */

        if (is_callable($visitor))
          call_user_func($visitor, $node);

        if (method_exists($visitor, 'visitNode'))
          $visitor->visitNode($node);

      }

      foreach ($leaveNodeSubNodes as $subNodeName)
        if (is_object($node->$subNodeName) || (is_array($node->$subNodeName) && count($node->$subNodeName) > 0))
          $traverseNode($node->$subNodeName);

      foreach ($visitors as $visitor) {

        // Provide compatibility for \PhpParser\NodeVisitor
        if (method_exists($visitor, 'leaveNode'))
          $visitor->leaveNode($node);

      }

    };

    // Provide compatibility for \PhpParser\NodeVisitor
    foreach ($visitors as $visitor)
      if (method_exists($visitor, 'beforeTraverse'))
        $visitor->beforeTraverse($node);

    $traverseNode($node);

    // Provide compatibility for \PhpParser\NodeVisitor
    foreach ($visitors as $visitor)
      if (method_exists($visitor, 'afterTraverse'))
        $visitor->afterTraverse($node);

    return $node;
  }

  static function getSimulationTraverseOrder ($node) {

    static $simulationTraverseOrderCache = [];

    if (!isset($simulationTraverseOrderCache[get_class($node)])) {

      $enterNodeSubNodes = [];
      $leaveNodeSubNodes = [];

      if (isset(self::$simulationTraverseOrder[get_class($node)])) {

        $enterNodeSubNodes = self::$simulationTraverseOrder[get_class($node)];

        if (in_array('self', $enterNodeSubNodes))
          $enterNodeSubNodes = array_slice($enterNodeSubNodes, 0, array_search('self', $enterNodeSubNodes));

        $leaveNodeSubNodes = [];

        if (in_array('self', self::$simulationTraverseOrder[get_class($node)])) {
          $leaveNodeSubNodes = self::$simulationTraverseOrder[get_class($node)];
          $leaveNodeSubNodes = array_slice($leaveNodeSubNodes, array_search('self', $leaveNodeSubNodes) + 1);
        }

      }

      $enterNodeSubNodes = array_merge(
        $enterNodeSubNodes,
        array_diff($node->getSubNodeNames(), ['self'], $enterNodeSubNodes, $leaveNodeSubNodes)
      );

      $simulationTraverseOrderCache[get_class($node)] = [$enterNodeSubNodes, $leaveNodeSubNodes];

    }

    return $simulationTraverseOrderCache[get_class($node)];

  }

}
