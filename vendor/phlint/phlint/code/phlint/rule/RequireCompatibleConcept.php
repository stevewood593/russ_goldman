<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \Phlint;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \phlint\Result;
use \PhpParser\Node;
use \PhpParser\NodeVisitorAbstract;

/**
 * Require that all symbol interactions are done through a compatible concept.
 */
class RequireCompatibleConcept extends NodeVisitorAbstract {

  function getIdentifier () {
    return 'requireCompatibleConcept';
  }

  function getCategories () {
    return [
      'default',
    ];
  }

  function getInferences () {
    return [
      'symbol',
      'templateSpecialization',
      'type',
      'value',
    ];
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function visitNode ($node) {

    #if ($node->getAttribute('isCheckedConcept', false))
    #  return;

    #$node->setAttribute('isCheckedConcept', true);

    #if (count(debug_backtrace()) > 300) {
    #  echo new \Error('e');
    #  exit;
    #}

    if (NodeConcept::isInvocationNode($node)) {

      if ($node instanceof Node\Expr\FuncCall || $node instanceof Node\Expr\StaticCall) {

        // Todo: Rewrite
        if (strpos(NodeConcept::sourcePrint($node) . '::', 'parent::') !== false) {
          return;
        }
        // Todo: Rewrite
        if (strpos(NodeConcept::sourcePrint($node) . '::', 'self::') !== false) {
          return;
        }
        // Todo: Rewrite
        if (strpos(NodeConcept::sourcePrint($node) . '::', 'static::') !== false) {
          return;
        }

        // Todo: Rewrite
        if (!($node instanceof Node\Expr\StaticCall))
        if (strpos(NodeConcept::sourcePrint($node), '$') === 0)
          return;

      }

      foreach (inference\SymbolLink::getUnmangled($node) as $symbol)
        if (!isset(op\metaContext('code')->data['symbols'][$symbol]) || count(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']) == 0) {
          $symbolDisplay = isset(op\metaContext('code')->data['symbols'][$symbol]) ? op\metaContext('code')->data['symbols'][$symbol]['phpId'] : $symbol;
          $symbolDisplay = strpos($symbolDisplay, 't_') === 0 ? substr($symbolDisplay, 2) : $symbolDisplay;
          $symbolDisplay = ltrim($symbolDisplay, '\\');

          // Todo: Rewrite
          if (strpos($symbolDisplay . '::', '::') === 0)
            continue;
          if (strpos($symbolDisplay . '::', 'parent::') !== false)
            continue;
          if (strpos($symbolDisplay . '::', 'self::') !== false)
            continue;
          if (strpos($symbolDisplay . '::', 'static::') !== false)
            continue;

          $expressionDisplay = NodeConcept::displayPrint($node);
          if ($symbolDisplay != $expressionDisplay && $symbolDisplay . '()' != ltrim($expressionDisplay, '\\'))
            op\metaContext('result')->addIssue(
              $node,
              'Unable to invoke undefined *' . $symbolDisplay . '*' .
                ' for the expression *' . $expressionDisplay . '*.'
            );
          else
            op\metaContext('result')->addIssue(
              $node,
              'Unable to invoke undefined *' . $expressionDisplay . '*.'
            );
        }

    }

    if ($node instanceof Node\Stmt\Foreach_) {
      foreach (array_unique(array_merge(inference\Type::get($node->expr), array_map(function ($value) { return $value['type']; }, inference\Value::get($node->expr)))) as $type)
        if (!inference\Type::isTraversable($type))
        if (NodeConcept::displayPrint($node->expr) == 'null' || ltrim(isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type, '\\'))
          op\metaContext('result')->addIssue($node,
            'Provided symbol *' . NodeConcept::displayPrint($node->expr) . '* ' .
            'of type *' . ltrim(isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type, '\\') . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

    }

  }

  function enterNode(Node $node) {

    if ($node->getAttribute('isCheckedConcept', false))
      return;

    if (($node instanceof Node\Expr\BinaryOp\Plus) || ($node instanceof Node\Expr\BinaryOp\Minus)) {

      foreach (array_unique(array_merge(inference\Type::get($node->left), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 'mixed'; return $value['type']; }, inference\Value::get($node->left)))) as $type)
        if (!in_array($type, ['', 't_undefined', 't_mixed', 't_integer', 'int', 't_int', 'autoInteger', 't_autoInteger', 'stringInt', 't_stringInt', 'stringBool', 't_stringBool', 'autoFloat', 't_autoFloat', 'stringFloat', 't_stringFloat', 't_float']))
          op\metaContext('result')->addIssue($node,
            'Provided ' . RequireCompatibleConcept::constructTypeName($node->left) . ' *' . NodeConcept::displayPrint($node->left) . '* ' .
            'of type *' . (isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type) . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

      foreach (array_unique(array_merge(inference\Type::get($node->right), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 'mixed'; return $value['type']; }, inference\Value::get($node->right)))) as $type)
        if (!in_array($type, ['', 't_undefined', 't_mixed', 't_integer', 'int', 't_int', 'autoInteger', 't_autoInteger', 'stringInt', 't_stringInt', 'stringBool', 't_stringBool', 'autoFloat', 't_autoFloat', 'stringFloat', 't_stringFloat', 't_float']))
          op\metaContext('result')->addIssue($node,
            'Provided ' . RequireCompatibleConcept::constructTypeName($node->right) . ' *' . NodeConcept::displayPrint($node->right) . '* ' .
            'of type *' . (isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type) . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

    }

    if (($node instanceof Node\Expr\AssignOp\Plus) || ($node instanceof Node\Expr\AssignOp\Minus)) {

      foreach (array_unique(array_merge(inference\Type::get($node->var), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 'mixed'; return $value['type']; }, inference\Value::get($node->var)))) as $type)
        if (!in_array($type, ['', 't_undefined', 't_mixed', 't_integer', 'int', 't_int', 'autoInteger', 't_autoInteger', 'stringInt', 't_stringInt', 'stringBool', 't_stringBool', 'autoFloat', 't_autoFloat', 'stringFloat', 't_stringFloat', 't_float']))
          op\metaContext('result')->addIssue($node,
            'Provided ' . self::constructTypeName($node->var) . ' *' . NodeConcept::displayPrint($node->var) . '* ' .
            'of type *' . (isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type) . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

      foreach (array_unique(array_merge(inference\Type::get($node->expr), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 'mixed'; return $value['type']; }, inference\Value::get($node->expr)))) as $type)
        if (!in_array($type, ['', 't_undefined', 't_mixed', 't_integer', 'int', 't_int', 'autoInteger', 't_autoInteger', 'stringInt', 't_stringInt', 'stringBool', 't_stringBool', 'autoFloat', 't_autoFloat', 'stringFloat', 't_stringFloat', 't_float']))
          op\metaContext('result')->addIssue($node,
            'Provided ' . self::constructTypeName($node->var) . ' *' . NodeConcept::displayPrint($node->expr) . '* ' .
            'of type *' . (isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type) . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

      #var_dump($node);

    }

    if ($node instanceof Node\Expr\BinaryOp\Concat) {

      foreach (array_unique(array_merge(inference\Type::get($node->left), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 't_mixed'; return $value['type']; }, inference\Value::get($node->left)))) as $type)
        if (!in_array($type, ['', 't_undefined']))
        if (substr($type, 0, 2) != 't_' && strpos($type, '[]') === false)
          op\metaContext('result')->addIssue($node,
            'Provided ' . RequireCompatibleConcept::constructTypeName($node->left) . ' *' . NodeConcept::displayPrint($node->left) . '* ' .
            'of type *' . ltrim(isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type, '\\') . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

      foreach (array_unique(array_merge(inference\Type::get($node->right), array_map(function ($value) { if ($value['type'] == '' && $value['value'] === null) return 't_mixed'; return $value['type']; }, inference\Value::get($node->right)))) as $type)
        if (!in_array($type, ['', 't_undefined']))
        if (substr($type, 0, 2) != 't_' && strpos($type, '[]') === false)
          op\metaContext('result')->addIssue($node,
            'Provided ' . RequireCompatibleConcept::constructTypeName($node->right) . ' *' . NodeConcept::displayPrint($node->right) . '* ' .
            'of type *' . ltrim(isset(op\metaContext('code')->data['symbols'][$type]) ? op\metaContext('code')->data['symbols'][$type]['phpId'] : $type, '\\') . '* ' .
            'is not compatible in the expression *' . NodeConcept::displayPrint($node) . '*.'
          );

    }

    // PhpParser\Node\Expr\Variable
    // PhpParser\Node\Expr\BinaryOp\Plus

  }

  /** Some concepts implicitly support other concepts. */
  static $implicitMap = [
    'numeric' => 'opPlus',
    'int' => 'opPlus',
  ];

  static function constructTypeName ($node) {

    if ($node instanceof Node\Expr\FuncCall)
      return 'expression';

    if ($node instanceof Node\Expr\Variable)
      return 'variable';

    if ($node instanceof Node\Stmt\Function_)
      return 'function';

    #var_dump($node);
    #return get_class($node);

    return 'symbol';
  }

}
