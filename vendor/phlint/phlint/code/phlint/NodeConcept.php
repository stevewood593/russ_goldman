<?php

namespace phlint;

use \phlint\NodeTraverser;
use \phlint\printer\Display as DisplayPrinter;
use \phlint\SourceNode;
use \PhpParser\Node;
use \PhpParser\PrettyPrinter\Standard as PrettyPrinter;

class NodeConcept
{

  static function isConditionalExecutionNode (Node $node) {
    return
      ($node instanceof Node\Expr\Ternary) ||
      ($node instanceof Node\Stmt\ElseIf_) ||
      ($node instanceof Node\Stmt\If_) ||
      false;
  }

  static function isContextBarrier (Node $node) {
    return
      ($node instanceof Node\Stmt\Return_) ||
      ($node instanceof Node\Stmt\Throw_) ||
      false;
  }

  static function isScopeNode (Node $node) {
    return
      ($node instanceof Node\Stmt\Namespace_) ||
      /**
       * SourceNode forms a new scope so that certain inferences and rules
       * can detect and prohibit cross-file references - for example
       * referencing a variables that has been initialized in file A
       * from file B.
       */
      #($node instanceof SourceNode) ||
      NodeConcept::isContextNode($node) ||
      NodeConcept::isExecutionBranchNode($node) ||

      /**
       * Some nodes can be scope node depending on their context.
       * For example, an expression node can also be a context node
       * if it's located on the right side of `&&` expression.
       */
      $node->getAttribute('isScope', false) ||

      false;
  }

  static function isDeclarationNode (Node $node) {
    return
      ($node instanceof Node\Const_) ||
      ($node instanceof Node\Stmt\PropertyProperty) ||
      ($node instanceof Node\Stmt\StaticVar) ||
      false;
  }

  static function isDefinitionNode ($node) {
    return
      ($node instanceof Node\Const_) ||
      ($node instanceof Node\Expr\Closure) ||
      ($node instanceof Node\Stmt\Class_) ||
      ($node instanceof Node\Stmt\ClassMethod) ||
      ($node instanceof Node\Stmt\Function_) ||
      ($node instanceof Node\Stmt\Interface_) ||
      ($node instanceof Node\Stmt\Namespace_) ||
      ($node instanceof Node\Stmt\Trait_) ||
      false;
  }

  static function isExecutionBarrier (Node $node) {
    return
      ($node instanceof Node\Expr\Exit_) ||
      ($node instanceof Node\Stmt\Break_) ||
      ($node instanceof Node\Stmt\Continue_) ||
      ($node instanceof Node\Stmt\Return_) ||
      ($node instanceof Node\Stmt\Throw_) ||
      false;
  }

  static function isInvocationNode ($node) {
    return
      ($node instanceof Node\Expr\FuncCall) ||
      ($node instanceof Node\Expr\MethodCall) ||
      ($node instanceof Node\Expr\StaticCall) ||
      false;
  }

  /**
   * The term "interface" in programming is mostly used in context of
   * object orientated programming and most of the times implies that.
   *
   * In Phlint term "interface" also exclusively mean object interface.
   */
  static function isInterfaceNode (Node $node) {
    return
      ($node instanceof Node\Stmt\Class_) ||
      ($node instanceof Node\Stmt\Interface_) ||
      false;
  }

  /**
   * Right-hand side symbol nodes can be used as an expression
   * but can't be assigned values to.
   */
  static function isRhsSymbolNode (Node $node) {
    return
      ($node instanceof Node\Expr\ConstFetch) ||
      NodeConcept::isInvocationNode($node) ||
      NodeConcept::isVariableNode($node) ||
      false;
  }

  static function isValueLiteral (Node $node) {
    return
      ($node instanceof Node\Scalar\DNumber) ||
      ($node instanceof Node\Scalar\LNumber) ||
      ($node instanceof Node\Scalar\String_) ||
      false;
  }

  static function isContextNode (Node $node) {
    return
      ($node instanceof Node\Expr\Closure) ||
      ($node instanceof Node\Stmt\Class_) ||
      ($node instanceof Node\Stmt\ClassMethod) ||
      ($node instanceof Node\Stmt\Function_) ||
      ($node instanceof Node\Stmt\Interface_) ||
      ($node instanceof Node\Stmt\Trait_) ||
      false;
  }

  static function isExecutionContextNode (Node $node) {
    return
      ($node instanceof Node\Stmt\ClassMethod) ||
      ($node instanceof Node\Expr\Closure) ||
      ($node instanceof Node\Stmt\Function_) ||
      false;
  }

  static function isExecutionBranchNode (Node $node) {
    return
      ($node instanceof Node\Expr\Ternary) ||
      ($node instanceof Node\Stmt\Catch_) ||
      ($node instanceof Node\Stmt\Else_) ||
      ($node instanceof Node\Stmt\ElseIf_) ||
      ($node instanceof Node\Stmt\Foreach_) ||
      ($node instanceof Node\Stmt\If_) ||
      ($node instanceof Node\Stmt\Switch_) ||
      ($node instanceof Node\Stmt\While_) ||
      false;
  }

  static function isLoop (Node $node) {
    return
      ($node instanceof Node\Stmt\Do_) ||
      ($node instanceof Node\Stmt\For_) ||
      ($node instanceof Node\Stmt\Foreach_) ||
      ($node instanceof Node\Stmt\While_) ||
      false;
  }

  static function isLoopScopeBarrier (Node $node) {
    return
      ($node instanceof Node\Stmt\Break_) ||
      ($node instanceof Node\Stmt\Continue_) ||
      NodeConcept::isContextBarrier($node) ||
      false;
  }

  static function isNamedNode (Node $node) {
    return
      ($node instanceof Node\Expr\ClosureUse) ||
      ($node instanceof Node\Expr\StaticPropertyFetch) ||
      ($node instanceof Node\Expr\Variable) ||
      ($node instanceof Node\Param) ||
      ($node instanceof Node\Stmt\Class_) ||
      ($node instanceof Node\Stmt\ClassMethod) ||
      ($node instanceof Node\Stmt\Function_) ||
      ($node instanceof Node\Stmt\Interface_) ||
      ($node instanceof Node\Stmt\Namespace_) ||
      ($node instanceof Node\Stmt\PropertyProperty) ||
      ($node instanceof Node\Stmt\Trait_) ||
      false;
  }

  static function isNamespaceNode (Node $node) {
    return
      ($node instanceof Node\Stmt\Namespace_) ||
      false;
  }

  static function isVariableNode ($node) {
    return
      ($node instanceof Node\Expr\ClosureUse) ||
      ($node instanceof Node\Expr\Variable) ||
      ($node instanceof Node\Param) ||
      /**
       * In case of `Node\Stmt\Catch_` $node->var is a string representing the variable
       * in question - there is no intermediate `Node\Expr\Variable` node. Hence catch
       * is a variable node itself.
       */
      ($node instanceof Node\Stmt\Catch_) ||
      ($node instanceof Node\Stmt\StaticVar) ||
      false;
  }

  static function deepClone ($node) {

    if (is_array($node)) {
      $clonedNode = [];
      foreach ($node as $index => $subNode)
        $clonedNode[$index] = self::deepClone($subNode);
      return $clonedNode;
    }

    if (!is_object($node))
      return $node;

    $clonedNode = clone $node;

    foreach ($node->getSubNodeNames() as $subNodeName)
      $clonedNode->$subNodeName = self::deepClone($node->$subNodeName);

    return $clonedNode;
  }

  static function deepCount ($node) {

    $count = 0;

    if (is_array($node)) {
      foreach ($node as $index => $subNode)
        $count += self::deepCount($subNode);
      return $count;
    }

    if (!is_object($node))
      return $count;

    $count += 1;

    foreach ($node->getSubNodeNames() as $subNodeName)
      $count += self::deepCount($node->$subNodeName);

    return $count;
  }

  /**
   * Does $nodeA and nodeB represent the same thing.
   */
  static function isSame ($nodeA, $nodeB) {
    if (($nodeA instanceof Node\Expr\Variable) && ($nodeB instanceof Node\Expr\Variable))
      if (is_string($nodeA->name) && is_string($nodeB->name) && $nodeA->name == $nodeB->name)
        return true;
    return false;
  }

  static function sourcePrint ($node) {
    if (is_string($node))
      return $node;
    $prettyPrinter = new PrettyPrinter();
    if ($node instanceof Node\Name) {
      try {
        $comments = $node->getAttribute('comments', []);
        $node->setAttribute('comments', []);
        $printed = $prettyPrinter->prettyPrint([$node]);
      } finally {
        $node->setAttribute('comments', $comments);
      }
      return $printed;
    }
    return $prettyPrinter->prettyPrint([$node]);
  }

  static function displayPrint ($node) {

    $printer = function ($node) {
      if (is_string($node))
        return $node;
      $printer = new DisplayPrinter();
      return $printer->prettyPrint([$node]);
    };

    $printed = $printer($node);

    if (false)
    assert(
      strpos($printed, "\n") === false,
      '*' . $printed . '* (' . (is_object($node) ? get_class($node) : gettype($node)) . ') contains a new line.'
    );

    if (false)
    assert(
      strlen($printed) <= 1000,
      '*' . $printed . '* (' . (is_object($node) ? get_class($node) : gettype($node)) . ') is too long.'
    );

    return $printed;

  }

  static function referencePrint ($node) {
    $constructTypeName = '';
    if (NodeConcept::constructTypeName($node))
      $constructTypeName = NodeConcept::constructTypeName($node);
    return ($constructTypeName ? $constructTypeName . ' ' : '') . '*' . NodeConcept::displayPrint($node) . '*';
  }

  static function constructTypeName ($node) {

    if ($node->getAttribute('constructTypeName', ''))
      return $node->getAttribute('constructTypeName', '');

    if ($node instanceof Node\Arg)
      return 'argument';

    if ($node instanceof Node\Expr\Closure)
      return 'function';

    if ($node instanceof Node\Expr\ClosureUse)
      return 'variable';

    if ($node instanceof Node\Expr\FuncCall)
      return 'expression';

    if ($node instanceof Node\Expr\New_)
      return 'expression';

    if ($node instanceof Node\Expr\MethodCall)
      return 'expression';

    if ($node instanceof Node\Expr\StaticCall)
      return 'expression';

    if ($node instanceof Node\Expr\Variable)
      return 'variable';

    if ($node instanceof Node\Name)
      return '';

    if ($node instanceof Node\Stmt\Catch_)
      return 'catch';

    if ($node instanceof Node\Stmt\Class_)
      return 'class';

    if ($node instanceof Node\Stmt\ClassMethod)
      return 'method';

    if ($node instanceof Node\Stmt\Function_)
      return 'function';

    if ($node instanceof Node\Stmt\Interface_)
      return 'interface';

    if ($node instanceof Node\Stmt\Namespace_)
      return 'namespace';

    if ($node instanceof Node\Stmt\StaticVar)
      return 'variable';

    return '__' . get_class($node) . '__';

  }

}
