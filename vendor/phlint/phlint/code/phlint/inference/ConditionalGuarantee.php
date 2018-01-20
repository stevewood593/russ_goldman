<?php

namespace phlint\inference;

use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \phlint\phpLanguage;
use \PhpParser\Node;

class ConditionalGuarantee {

  /**
   * Conditional guarantees defaults to use as a starting point.
   * These defaults means that the guarantee is actually not provided.
   */
  static $conditionalGuaranteesDefaults = [
    'isDefined' => false,
    'isNotDefined' => false,
    'includesTypes' => [],
    'excludesTypes' => [],
    'includesValues' => [],
    'excludesValues' => [],
    'includesConcepts' => [],
    'excludesConcepts' => [],
  ];

  /**
   * Analyzes the conditional execution node and infers the guarantees that can be made
   * for its scopes.
   */
  static function evaluate ($node) {

    $guarantees = [];

    if (NodeConcept::isConditionalExecutionNode($node))
      foreach (inference\ConditionalGuarantee::evaluateCondition($node->cond) as $guarantee)
        $guarantees[] = array_merge($guarantee, [
          'scope' => $node,
        ]);

    if ($node instanceof Node\Expr\BinaryOp\BooleanAnd)
      foreach (inference\ConditionalGuarantee::evaluateCondition($node->left) as $guarantee)
        $guarantees[] = array_merge($guarantee, [
          'scope' => $node->right,
        ]);

    if (($node instanceof Node\Stmt\If_) && $node->else)
      foreach (inference\ConditionalGuarantee::evaluateCondition(new Node\Expr\BooleanNot($node->cond)) as $guarantee)
        $guarantees[] = array_merge($guarantee, [
          'scope' => $node->else,
        ]);

    return $guarantees;

  }

  /**
   * Analyzes the condition and infers the guarantees that can be made
   * given that the condition evaluates to true.
   *
   * For example:
   *   - For condition `isset($x)` the guarantee is that `$x` is defined.
   *   - For condition `is_numeric($x)` the guarantee is that `$x` is of `autoFloat` type.
   */
  static function evaluateCondition ($node) {

    $guarantees = [];

    $isInvocationGuarantee = function ($node, $symbol) {
      return ($node instanceof Node\Expr\FuncCall) &&
        inference\SymbolLink::getUnmangled($node) == [$symbol] &&
        count($node->args) > 0;
    };

    if ($node instanceof Node\Arg)
      foreach (ConditionalGuarantee::evaluateCondition($node->value) as $guarantee)
        $guarantees[] = $guarantee;

    if (($node instanceof Node\Expr\Assign) || ($node instanceof Node\Expr\AssignRef)) {
      $guarantees[] = [
        'node' => ($node->var instanceof Node\Expr\ArrayDimFetch) ? $node->var->var : $node->var,
        'excludesTypes' => ['t_undefined'],
      ];
      foreach (ConditionalGuarantee::evaluateCondition($node->expr) as $expressionGuarantee)
        $guarantees[] = [
          'node' => $expressionGuarantee['node'],
        ];
    }

    if ($node instanceof Node\Expr\BooleanNot)
      foreach (ConditionalGuarantee::evaluateCondition($node->expr) as $guarantee)
        $guarantees[] = [
          'node' => $guarantee['node'],
          'includesTypes' => $guarantee['excludesTypes'],
          'excludesTypes' => $guarantee['includesTypes'],
          'includesValues' => $guarantee['excludesValues'],
          'excludesValues' => $guarantee['includesValues'],
          'includesConcepts' => $guarantee['excludesConcepts'],
          'excludesConcepts' => $guarantee['includesConcepts'],
        ];

    if ($node instanceof Node\Expr\BinaryOp\BooleanAnd) {
      $leftGuarantees = ConditionalGuarantee::evaluateCondition($node->left);
      $rightGuarantees = ConditionalGuarantee::evaluateCondition($node->right);
      foreach ([[$leftGuarantees, $rightGuarantees], [$rightGuarantees, $leftGuarantees]] as $guaranteesPair)
        foreach (ConditionalGuarantee::mergeAndGuarantees($guaranteesPair[0], $guaranteesPair[1]) as $guarantee)
          $guarantees[] = $guarantee;
    }

    if ($node instanceof Node\Expr\BinaryOp\BooleanOr) {
      $leftGuarantees = ConditionalGuarantee::evaluateCondition($node->left);
      $rightGuarantees = ConditionalGuarantee::evaluateCondition($node->right);
      foreach ([[$leftGuarantees, $rightGuarantees], [$rightGuarantees, $leftGuarantees]] as $guaranteesPair)
        foreach (ConditionalGuarantee::mergeOrGuarantees($guaranteesPair[0], $guaranteesPair[1]) as $guarantee)
          $guarantees[] = $guarantee;
    }

    /** @see http://www.php.net/manual/en/function.empty.php */
    if ($node instanceof Node\Expr\Empty_)
      #if ((($node->expr instanceof Node\Expr\ArrayDimFetch) ? $node->expr->var : $node->expr) instanceof Node\Expr\Variable)
      $guarantees[] = [
        'node' => ($node->expr instanceof Node\Expr\ArrayDimFetch) ? $node->expr->var : $node->expr,
        'includesTypes' => ['t_undefined'],
        'includesValues' => phpLanguage\Fixture::$emptyValues,
      ];

    /** @see http://php.net/manual/en/language.operators.type.php */
    if ($node instanceof Node\Expr\Instanceof_)
      $guarantees[] = [
        'node' => $node->expr,
        'includesTypes' => [$node->class],
      ];


    /** @see http://www.php.net/manual/en/function.isset.php */
    if ($node instanceof Node\Expr\Isset_)
      foreach ($node->vars as $var)
        #if ((($var instanceof Node\Expr\ArrayDimFetch) ? $var->var : $var) instanceof Node\Expr\Variable)
        $guarantees[] = [
          'node' => ($var instanceof Node\Expr\ArrayDimFetch) ? $var->var : $var,
          'excludesTypes' => ['t_undefined'],
          'excludesValues' => [['type' => '', 'value' => null]],
        ];

    if ($node instanceof Node\Expr\Variable)
      $guarantees[] = [
        'node' => $node,
        'excludesTypes' => ['t_undefined'],
        'excludesValues' => phpLanguage\Fixture::$emptyValues,
      ];

    /** @see http://www.php.net/manual/en/function.function-exists.php */
    if ($isInvocationGuarantee($node, 'f_function_exists'))
      $guarantees[] = [
        'node' => $node->args[0],
        'excludesTypes' => ['t_undefined'],
      ];

    /** @see http://www.php.net/manual/en/function.is-array.php */
    if ($isInvocationGuarantee($node, 'f_is_array'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesConcepts' => ['array'],
      ];

    /** @see http://www.php.net/manual/en/function.is-bool.php */
    if ($isInvocationGuarantee($node, 'f_is_bool'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_bool'],
      ];

    /** @see http://www.php.net/manual/en/function.is-double.php */
    if ($isInvocationGuarantee($node, 'f_is_double'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_float'],
      ];

    /** @see http://www.php.net/manual/en/function.is-float.php */
    if ($isInvocationGuarantee($node, 'f_is_float'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_float'],
      ];

    /** @see http://www.php.net/manual/en/function.is-int.php */
    if ($isInvocationGuarantee($node, 'f_is_int'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_int'],
      ];

    /** @see http://www.php.net/manual/en/function.is-integer.php */
    if ($isInvocationGuarantee($node, 'f_is_integer'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_int'],
      ];

    /** @see http://www.php.net/manual/en/function.is-long.php */
    if ($isInvocationGuarantee($node, 'f_is_long'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_int'],
      ];

    /** @see http://www.php.net/manual/en/function.is-null.php */
    if ($isInvocationGuarantee($node, 'f_is_null'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesValues' => [['type' => '', 'value' => null]],
      ];

    /** @see http://www.php.net/manual/en/function.is-numeric.php */
    if ($isInvocationGuarantee($node, 'f_is_numeric'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_autoFloat'],
      ];

    /** @see http://www.php.net/manual/en/function.is-object.php */
    if ($isInvocationGuarantee($node, 'f_is_object'))
      $guarantees[] = [
        'node' => $node->args[0],
        'excludesTypes' => ['t_bool', 't_float', 't_int', 't_string'],
        'excludesValues' => [['type' => '', 'value' => null]],
      ];

    /** @see http://www.php.net/manual/en/function.is-real.php */
    if ($isInvocationGuarantee($node, 'f_is_real'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_float'],
      ];

    /** @see http://www.php.net/manual/en/function.is-scalar.php */
    if ($isInvocationGuarantee($node, 'f_is_scalar'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_bool', 't_float', 't_int', 't_string'],
        'excludesValues' => [['type' => '', 'value' => null]],
      ];

    /** @see http://www.php.net/manual/en/function.is-string.php */
    if ($isInvocationGuarantee($node, 'f_is_string'))
      $guarantees[] = [
        'node' => $node->args[0],
        'includesTypes' => ['t_string'],
      ];

    return ConditionalGuarantee::aggregateGuarantees($guarantees);

  }

  /**
   * A helper function that evaluates guarantees produced from an `&&` condition.
   */
  static function mergeAndGuarantees ($evaluatedGuarantees, $constraintGuarantees) {

    $guarantees = [];

    foreach ($evaluatedGuarantees as $evaluatedGuarantee) {

      $guaranteePersist = array_fill_keys(array_keys(ConditionalGuarantee::$conditionalGuaranteesDefaults), true);

      foreach ($constraintGuarantees as $constraintGuarantee) {
        if (NodeConcept::isSame($evaluatedGuarantee['node'], $constraintGuarantee['node'])) {
          if ($constraintGuarantee['isDefined'])
            $guaranteePersist['isDefined'] = false;
          if ($constraintGuarantee['isNotDefined'])
            $guaranteePersist['isNotDefined'] = false;
          if (count($constraintGuarantee['includesTypes']) > 0)
            $guaranteePersist['includesTypes'] = false;
          if (count($constraintGuarantee['excludesTypes']) > 0)
            $guaranteePersist['excludesTypes'] = false;
          if (count($constraintGuarantee['includesValues']) > 0)
            $guaranteePersist['includesValues'] = false;
          if (count($constraintGuarantee['excludesValues']) > 0)
            $guaranteePersist['excludesValues'] = false;
          if (count($constraintGuarantee['includesConcepts']) > 0)
            $guaranteePersist['includesConcepts'] = false;
          if (count($constraintGuarantee['excludesConcepts']) > 0)
            $guaranteePersist['excludesConcepts'] = false;
        }
      }

      $guarantees[] = [
        'node' => $evaluatedGuarantee['node'],
        'isDefined' => !$guaranteePersist['isDefined'] ? false : $evaluatedGuarantee['isDefined'],
        'isNotDefined' => !$guaranteePersist['isNotDefined'] ? false : $evaluatedGuarantee['isNotDefined'],
        'includesTypes' => !$guaranteePersist['includesTypes'] ? [] : $evaluatedGuarantee['includesTypes'],
        'excludesTypes' => !$guaranteePersist['excludesTypes'] ? [] : $evaluatedGuarantee['excludesTypes'],
        'includesValues' => !$guaranteePersist['includesValues'] ? [] : $evaluatedGuarantee['includesValues'],
        'excludesValues' => !$guaranteePersist['excludesValues'] ? [] : $evaluatedGuarantee['excludesValues'],
        'includesConcepts' => !$guaranteePersist['includesConcepts'] ? [] : $evaluatedGuarantee['includesConcepts'],
        'excludesConcepts' => !$guaranteePersist['excludesConcepts'] ? [] : $evaluatedGuarantee['excludesConcepts'],
      ];

    }

    return $guarantees;

  }

  /**
   * A helper function that evaluates guarantees produced from an `||` condition.
   */
  static function mergeOrGuarantees ($evaluatedGuarantees, $constraintGuarantees) {

    $guarantees = [];

    foreach ($evaluatedGuarantees as $evaluatedGuarantee) {

      $guaranteePersist = array_fill_keys(array_keys(ConditionalGuarantee::$conditionalGuaranteesDefaults), false);

      foreach ($constraintGuarantees as $constraintGuarantee) {
        if (NodeConcept::isSame($evaluatedGuarantee['node'], $constraintGuarantee['node'])) {
          if ($constraintGuarantee['isDefined'])
            $guaranteePersist['isDefined'] = true;
          if ($constraintGuarantee['isNotDefined'])
            $guaranteePersist['isNotDefined'] = true;
          if (count($constraintGuarantee['includesTypes']) > 0)
            $guaranteePersist['includesTypes'] = true;
          if (count($constraintGuarantee['excludesTypes']) > 0)
            $guaranteePersist['excludesTypes'] = true;
          if (count($constraintGuarantee['includesValues']) > 0)
            $guaranteePersist['includesValues'] = true;
          if (count($constraintGuarantee['excludesValues']) > 0)
            $guaranteePersist['excludesValues'] = true;
          if (count($constraintGuarantee['includesConcepts']) > 0)
            $guaranteePersist['includesConcepts'] = true;
          if (count($constraintGuarantee['excludesConcepts']) > 0)
            $guaranteePersist['excludesConcepts'] = true;
        }
      }

      $guarantees[] = [
        'node' => $evaluatedGuarantee['node'],
        'isDefined' => !$guaranteePersist['isDefined'] ? false : $evaluatedGuarantee['isDefined'],
        'isNotDefined' => !$guaranteePersist['isNotDefined'] ? false : $evaluatedGuarantee['isNotDefined'],
        'includesTypes' => !$guaranteePersist['includesTypes'] ? [] : $evaluatedGuarantee['includesTypes'],
        'excludesTypes' => !$guaranteePersist['excludesTypes'] ? [] : $evaluatedGuarantee['excludesTypes'],
        'includesValues' => !$guaranteePersist['includesValues'] ? [] : $evaluatedGuarantee['includesValues'],
        'excludesValues' => !$guaranteePersist['excludesValues'] ? [] : $evaluatedGuarantee['excludesValues'],
        'includesConcepts' => !$guaranteePersist['includesConcepts'] ? [] : $evaluatedGuarantee['includesConcepts'],
        'excludesConcepts' => !$guaranteePersist['excludesConcepts'] ? [] : $evaluatedGuarantee['excludesConcepts'],
      ];

    }

    return $guarantees;

  }

  static function aggregateGuarantees ($guarantees) {

    $aggregatedGuarantees = [];

    foreach ($guarantees as $guarantee) {

      $index = -1;

      foreach ($aggregatedGuarantees as $guaranteeIndex => $aggregatedGuarantee)
        if (NodeConcept::isSame($aggregatedGuarantee['node'], $guarantee['node'])) {
          $index = $guaranteeIndex;
        }

      if ($index == -1) {
        $index = count($aggregatedGuarantees);
        $aggregatedGuarantees[$index] = [
          'node' => $guarantee['node'],
          'isDefined' => false,
          'isNotDefined' => false,
          'includesTypes' => [],
          'excludesTypes' => [],
          'includesValues' => [],
          'excludesValues' => [],
          'includesConcepts' => [],
          'excludesConcepts' => [],
        ];
      }

      $aggregatedGuarantees[$index] = array_merge(
        ['node' => null],
        ConditionalGuarantee::$conditionalGuaranteesDefaults,
        $aggregatedGuarantees[$index]
      );

      $guarantee = array_merge(
        ['node' => null],
        ConditionalGuarantee::$conditionalGuaranteesDefaults,
        $guarantee
      );

      $aggregatedGuarantees[$index]['isDefined'] =
        $aggregatedGuarantees[$index]['isDefined'] || $guarantee['isDefined'];
      $aggregatedGuarantees[$index]['isNotDefined'] =
        $aggregatedGuarantees[$index]['isNotDefined'] || $guarantee['isNotDefined'];
      $aggregatedGuarantees[$index]['includesTypes'] =
        array_merge($aggregatedGuarantees[$index]['includesTypes'], $guarantee['includesTypes']);
      $aggregatedGuarantees[$index]['excludesTypes'] =
        array_merge($aggregatedGuarantees[$index]['excludesTypes'], $guarantee['excludesTypes']);
      $aggregatedGuarantees[$index]['includesValues'] =
        array_merge($aggregatedGuarantees[$index]['includesValues'], $guarantee['includesValues']);
      $aggregatedGuarantees[$index]['excludesValues'] =
        array_merge($aggregatedGuarantees[$index]['excludesValues'], $guarantee['excludesValues']);
      $aggregatedGuarantees[$index]['includesConcepts'] =
        array_merge($aggregatedGuarantees[$index]['includesConcepts'], $guarantee['includesConcepts']);
      $aggregatedGuarantees[$index]['excludesConcepts'] =
        array_merge($aggregatedGuarantees[$index]['excludesConcepts'], $guarantee['excludesConcepts']);

      if (in_array('t_undefined', $aggregatedGuarantees[$index]['includesTypes']))
        $aggregatedGuarantees[$index]['isNotDefined'] = true;

      if (in_array('t_undefined', $aggregatedGuarantees[$index]['excludesTypes']))
        $aggregatedGuarantees[$index]['isDefined'] = true;

    }

    return $aggregatedGuarantees;

  }

}
