<?php

namespace phlint\printer;

use \phlint\inference;
use \PhpParser\Node;

class Display extends \PhpParser\PrettyPrinter\Standard {

  function pClassCommon (Node\Stmt\Class_ $node, $afterClassToken) {
    return $this->pModifiers($node->type) .
      $afterClassToken .
      (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '') .
      (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
    ;
  }

  function pExpr_Array (Node\Expr\Array_ $node) {
    #return 'Not implmented.' . "\n";
    if ($node->getAttribute('kind', Node\Expr\Array_::KIND_SHORT) === Node\Expr\Array_::KIND_LONG)
      return 'array(' . $this->pCommaSeparated($node->items) . ')';
    return '[' . $this->pCommaSeparated($node->items) . ']';
  }

  function pExpr_Closure (Node\Expr\Closure $node) {
    return ($node->byRef ? '&' : '') .
      '(' . $this->pCommaSeparated($node->params) . ')' .
      (!empty($node->uses) ? ' use (' . $this->pCommaSeparated($node->uses) . ')': '') .
      (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
    ;
  }

  function pExpr_ClosureUse (Node\Expr\ClosureUse $node) {

    $values = array_filter($node->getAttribute('values', []), function ($value) {
      return $value['type'] != 't_mixed' && !is_array($value['value']);
    });

    if (count($values) > 0)
      return implode('|', array_map(function ($value) {
        if ($value['type'] == 't_string')
          return $this->pScalar_String(new Node\Scalar\String_($value['value'], ['kind' => 2]));
        return var_export($value['value'], true);
      }, $values));

    $typeDisplay = '';

    if (!$typeDisplay && count($node->getAttribute('typesDisplay', [])) > 0)
      $typeDisplay = implode('|', $node->getAttribute('typesDisplay', []));

    return trim($typeDisplay . ' ' . '$' . $node->var);

  }

  function pExpr_FuncCall (Node\Expr\FuncCall $node) {
    if ($node->getAttribute('displayPrint', ''))
      return $node->getAttribute('displayPrint', '');
    return $this->pCallLhs($node->name) . '(' . $this->pCommaSeparated($node->args) . ')';
  }

  function pExpr_StaticCall (Node\Expr\StaticCall $node) {
    return $this->pDereferenceLhs($node->class) . '::' .
      ($node->name instanceof Node\Expr ? ($node->name instanceof Node\Expr\Variable ?
        $this->p($node->name) : '{' . $this->p($node->name) . '}') : $node->name) .
      '(' . $this->pCommaSeparated($node->args) . ')'
    ;
  }

  function pParam (Node\Param $node) {

    $values = array_filter($node->getAttribute('values', []), function ($value) {
      return $value['type'] != 't_mixed' && !is_array($value['value']);
    });

    if (count($values) > 0)
      return implode('|', array_map(function ($value) {
        if ($value['type'] == 't_string')
          return $this->pScalar_String(new Node\Scalar\String_($value['value'], ['kind' => 2]));
        return var_export($value['value'], true);
      }, $values));

    $typeDisplay = '';

    if (!$typeDisplay && count($node->getAttribute('typesDisplay', [])) > 0)
      $typeDisplay = implode('|', $node->getAttribute('typesDisplay', []));

    if (!$typeDisplay && $node->type)
      $typeDisplay = $this->pType($node->type);

    #return ($node->type ? $this->pType($node->type) . ' ' : '') .
    return $typeDisplay . ' ' .
      #($node->byRef ? '&' : '') .
      #($node->variadic ? '...' : '') .
      '$' . $node->name .
      ($node->default && count($node->getAttribute('values', [])) == 0 ? ' = ' . $this->p($node->default) : '')
    ;
  }

  function pStmts (array $nodes, $indent = true) {
    $printedNodes = [];
    foreach ($nodes as $node)
      $printedNodes[] = $this->p($node);
    return implode('; ', $printedNodes);
  }

  function pStmt_ClassMethod (Node\Stmt\ClassMethod $node) {
    return $this->pModifiers($node->type) .
      'function ' . ($node->byRef ? '&' : '') . $node->name .
      '(' . $this->pCommaSeparated($node->params) . ')' .
      (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
    ;
  }

  function pStmt_Declare (Node\Stmt\Declare_ $node) {
    return 'declare (' . $this->pCommaSeparated($node->declares) . ')';
  }

  function pStmt_Do (Node\Stmt\Do_ $node) {
    return 'do {} while (' . $this->p($node->cond) . ')';
  }

  function pStmt_Else (Node\Stmt\Else_ $node) {
    return 'else';
  }

  function pStmt_ElseIf (Node\Stmt\ElseIf_ $node) {
    return 'elseif (' . $this->p($node->cond) . ')';
  }

  function pStmt_For (Node\Stmt\For_ $node) {
    return 'for ('.
      $this->pCommaSeparated($node->init) . ';' . (!empty($node->cond) ? ' ' : '') .
      $this->pCommaSeparated($node->cond) . ';' . (!empty($node->loop) ? ' ' : '') .
      $this->pCommaSeparated($node->loop) .
      ')'
    ;
  }

  function pStmt_Foreach (Node\Stmt\Foreach_ $node) {
    return 'foreach (' . $this->p($node->expr) . ' as ' .
      (null !== $node->keyVar ? $this->p($node->keyVar) . ' => ' : '') .
      ($node->byRef ? '&' : '') . $this->p($node->valueVar) . ')'
    ;
  }

  function pStmt_Function (Node\Stmt\Function_ $node) {
    return (inference\Isolation::isIsolated($node) ? ' @isolated ' : '') .
      (inference\Purity::isPure($node) ? ' @pure ' : '') .
      ($node->byRef ? '&' : '') . $node->name .
      '(' . $this->pCommaSeparated($node->params) . ')' .
      (null !== $node->returnType ? ' : ' . $this->pType($node->returnType) : '')
    ;
  }

  function pStmt_If (Node\Stmt\If_ $node) {
    return 'if (' . $this->p($node->cond) . ')';
  }

  function pStmt_Interface (Node\Stmt\Interface_ $node) {
    return $node->name .
      (!empty($node->extends) ? ' extends ' . $this->pCommaSeparated($node->extends) : '')
    ;
  }

  function pStmt_Namespace (Node\Stmt\Namespace_ $node) {
    return $node->name !== null ? $this->p($node->name) : '';
  }

  function pStmt_StaticVar (Node\Stmt\StaticVar $node) {
    return '$' . $node->name;
  }

  function pStmt_Switch (Node\Stmt\Switch_ $node) {
    return 'switch (' . $this->p($node->cond) . ')';
  }

  function pStmt_Trait (Node\Stmt\Trait_ $node) {
    return $node->name;
  }

  function pStmt_TryCatch (Node\Stmt\TryCatch $node) {
    return 'try {}' .
      $this->pImplode($node->catches) .
      ($node->finallyStmts !== null ? ' finally {}' : '')
    ;
  }

  function pStmt_Catch (Node\Stmt\Catch_ $node) {
    return $this->pImplode($node->types, '|') . ' $' . $node->var;
  }

  function pStmt_While (Node\Stmt\While_ $node) {
    return 'while (' . $this->p($node->cond) . ')';
  }

}
