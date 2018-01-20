<?php

namespace phlint\inference;

use \luka8088\ExtensionCall;
use \luka8088\ExtensionCallExtends;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\inference\Scope;
use \phlint\inference\Symbol;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \PhpParser\Node;

class Value {

  function getIdentifier () {
    return 'value';
  }

  function getPass () {
    return 30;
  }

  /** @see /documentation/glossary/intermediatelyInferredData.md */
  protected $iiData = null;

  function setIIData ($iiData) {
    assert(is_object($iiData));
    $this->iiData = $iiData;
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function visitNode ($node) {

    $this->inferExpressionValuesUsed($node);

    $this->inferConditionalGuarantees($node);

    $this->inferSymbolValues($node);

    $this->inferValues($node);

    $this->evaluateExpression($node);

    $this->evaluateArrayLiteral($node);

  }

  function leaveNode ($node) {

    $this->inferExecutionBranchEffect($node);

    $this->inferReturnValues($node);

    $this->inferConditionalGuaranteesBarrier($node);

  }

  function cleanNode ($node) {
    // @todo: Rewrite.
    if (!($node instanceof Node\Expr\ClosureUse) && count($node->getAttribute('values', [])) > 0)
      $node->setAttribute('values', []);
  }

  function inferExpressionValuesUsed ($node) {

    if ($node instanceof Node\Expr\Assign)
      $this->markExpressionValuesUsed($node->expr);

    if ($node instanceof Node\Expr\AssignOp)
      $this->markExpressionValuesUsed($node->expr);

    if ($node instanceof Node\Expr\AssignRef)
      $this->markExpressionValuesUsed($node->expr);

  }

  function markExpressionValuesUsed ($node) {

    if ($node instanceof Node\Expr\BinaryOp) {
      $this->markExpressionValuesUsed($node->left);
      $this->markExpressionValuesUsed($node->right);
      return;
    }

    if ($node instanceof Node\Expr\BooleanNot) {
      $this->markExpressionValuesUsed($node->expr);
      return;
    }

    $node->setAttribute('expressionValuesUsed', true);

  }

  function inferConditionalGuarantees ($node) {

    foreach (inference\ConditionalGuarantee::evaluate($node) as $guarantee) {

      foreach ($guarantee['excludesValues'] as $value) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['values:' . $scopedSymbol]))
              $this->iiData['values:' . $scopedSymbol] = [];
            unset($this->iiData['values:' . $scopedSymbol][Value::valueKey($value)]);
          }

      }

      if (count($guarantee['includesTypes']) > 0) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            $this->iiData['values:' . $scopedSymbol] = [];
          }

      }

      foreach ($guarantee['includesConcepts'] as $concept) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['values:' . $scopedSymbol]))
              $this->iiData['values:' . $scopedSymbol] = [];
            #var_dump($concept);
            #var_dump($this->iiData['values:' . $scopedSymbol]);
            #unset($this->iiData['values:' . $scopedSymbol][Value::valueKey($value)]);
          }

      }

      foreach ($guarantee['excludesConcepts'] as $concept) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['values:' . $scopedSymbol]))
              $this->iiData['values:' . $scopedSymbol] = [];
            #var_dump($concept);
            #var_dump($this->iiData['values:' . $scopedSymbol]);
            #unset($this->iiData['values:' . $scopedSymbol][Value::valueKey($value)]);
          }

      }

    }

  }

  function inferExecutionBranchEffect ($node) {

    if ($node instanceof Node\Stmt\If_) {

      $guarantees = inference\ConditionalGuarantee::evaluateCondition($node->cond);

      foreach ($guarantees as $guarantee) {

        $scope = $node->getAttribute('scope', '');

        foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

          $symbolx = Symbol::concat($scope, Symbol::unqualified($symbol));

          if (isset($this->iiData['types:' . $symbol]) && count($this->iiData['types:' . $symbol]) > 0) {
            foreach ($guarantee['includesValues'] as $value) {
              if (isset($this->iiData['values:' . $symbolx][Value::valueKey($value)]))
                unset($this->iiData['values:' . $symbolx][Value::valueKey($value)]);
            }
          }

        }

      }

    }

  }

  function inferConditionalGuaranteesBarrier ($node) {

    if ($node instanceof Node\Stmt\If_) {

      $hasBarrier = false;

      foreach ($node->stmts as $statementNode)
        if (NodeConcept::isLoopScopeBarrier($statementNode))
          $hasBarrier = true;

      if ($hasBarrier) {

        $guarantees = inference\ConditionalGuarantee::evaluateCondition(new Node\Expr\BooleanNot($node->cond));

        foreach ($guarantees as $guarantee) {

          foreach ($guarantee['excludesValues'] as $value) {
            foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

              $scope = $node->getAttribute('scope', '');

              while (true) {

                $symbolx = Symbol::concat($scope, Symbol::unqualified($symbol));

                #var_dump('guarantee: ' . $symbolx);

                assert(substr($symbolx, 0, 1) != '.');
                assert(substr($symbolx, -1) != '.');

                if (!isset($this->iiData['values:' . $symbolx]))
                  $this->iiData['values:' . $symbolx] = [];

                unset($this->iiData['values:' . $symbolx][Value::valueKey($value)]);

                if (isset(op\metaContext('code')->data['symbols'][$scope]['declarationNodes']))
                  if (count(op\metaContext('code')->data['symbols'][$scope]['declarationNodes']) > 0)
                    if (count(array_filter(op\metaContext('code')->data['symbols'][$scope]['declarationNodes'], function ($node) { return !NodeConcept::isLoop($node); })) == 0)
                      break;

                if (Symbol::isContext($scope))
                  break;

                $scope = Scope::parent($scope);

                if (!$scope)
                  break;

              }

            }
          }

        }

      }

    }

  }

  function inferSymbolValues ($node) {

    if ($node instanceof Node\Expr\Assign)
      $this->registerSymbolsValues($node->var, inference\Value::get($node->expr));

    if ($node instanceof Node\Expr\Closure)
      foreach ($node->uses as $use_)
        $this->registerSymbolsValues($use_, $this->lookup(Symbol::concat($node->getAttribute('scope', ''), Symbol::identifier($use_->var, 'variable'))));

    if ($node instanceof Node\Expr\ClosureUse)
      $this->registerSymbolsValues($node, inference\Value::get($node));

    /**
     * Constants need to be registered globally as they are available outside
     * of the registered scope.
     */
    if ($node instanceof Node\Stmt\Const_)
      foreach ($node->consts as $constantNode)
        foreach (inference\SymbolLink::get($constantNode) as $constantSymbol)
          if (isset(op\metaContext('code')->data['symbols'][$constantSymbol]))
            foreach (inference\Value::get($constantNode) as $value)
              op\metaContext('code')->data['symbols'][$constantSymbol]['values'][Value::valueKey($value)] = $value;

    if (($node instanceof Node\Expr\FuncCall) && inference\SymbolLink::getUnmangled($node) == ['f_define'] && count($node->args) >= 2)
      foreach (inference\Value::get($node->args[0]) as $constantName) {
        #if ($constantName['type'] != 't_string')
        #  continue;
        $symbol = Symbol::fullyQualifiedIdentifier('\\' . ltrim($constantName['value'], '\\'), 'constant');
        if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
          continue;
        foreach (inference\Value::get($node->args[1]) as $value)
          op\metaContext('code')->data['symbols'][$symbol]['values'][Value::valueKey($value)] = $value;
      }

    if ($node instanceof Node\Param)
      $this->registerSymbolsValues($node, inference\Value::get($node));

    if ($node instanceof Node\Stmt\Return_) {
      $scope = Scope::contextScope($node->getAttribute('scope', ''));
      if ($scope)
        $this->registerSymbolsValues([$scope . '.r'], inference\Value::get($node->expr));
    }

  }

  function inferValues ($node) {

    if (NodeConcept::isRhsSymbolNode($node))
      $node->setAttribute('values', $this->lookup($node));

    if ($node instanceof Node\Expr\ClassConstFetch && $node->name == 'class') {
      $values = [];
      foreach (inference\SymbolLink::get($node->class) as $symbol) {
        $value = [
          'type' => 't_string',
          'value' => op\metaContext('code')->data['symbols'][$symbol]['phpId'],
        ];
        $values[Value::valueKey($value)] = $value;
      }
      $node->setAttribute('values', array_values($values));
    }

  }

  function inferReturnValues ($node) {

    if (NodeConcept::isExecutionContextNode($node)) {
      $returnValues = [];
      foreach (inference\SymbolLink::get($node) as $symbol)
        foreach ($this->lookup($symbol . '.r') as $returnValue)
          $returnValues[] = $returnValue;
      $node->setAttribute('returnValues', $returnValues);
    }

  }

  /**
   * @ExtensionCall("phlint.analysis.inference.symbolLink/inferValues")
   * @ExtensionCallExtends("phlint.analysis.inference.symbolLink/default")
   */
  function symbolLink ($node, $symbolIdentifierGroup = '') {
    if (count($node->getAttribute('values', [])) > 0)
      return;
    $node->setAttribute('values', $this->lookup($node));
  }

  function evaluateExpression ($node) {

    if ($node instanceof Node\Expr\BinaryOp\Identical) {
      $valuesKnown = count(inference\Value::get($node->left)) > 0 && count(inference\Value::get($node->right)) > 0 ;
      $allSatisfied = true;
      foreach (inference\Value::get($node->left) as $value1)
        foreach (inference\Value::get($node->right) as $value2)
          if ($value1['type'] != 't_mixed' && $value2['type'] != 't_mixed')
            if ($value1['type'] != $value2['type'] || $value1['value'] !== $value2['value'])
              $allSatisfied = false;
      if ($valuesKnown && !$allSatisfied)
        $node->setAttribute('values', [['type' => 't_bool', 'value' => false]]);
    }

  }

  function registerSymbolsValues ($symbols, $values) {

    $node = $symbols;

    if ($symbols instanceof Node)
      $symbols = inference\SymbolLink::get($symbols);

    foreach ($symbols as $symbol) {
      $this->iiData['values:' . $symbol] = [];
      foreach (Symbol::visibleScopes($symbol) as $scopeSymbol) {
        if (!isset($this->iiData['values:' . $scopeSymbol]))
          $this->iiData['values:' . $scopeSymbol] = [];
        foreach ($values as $value)
          $this->iiData['values:' . $scopeSymbol][Value::valueKey($value)] = $value;
      }
    }

  }

  static function lookup ($symbol) {

    if ($symbol instanceof Node && NodeConcept::isInvocationNode($symbol) &&
        inference\SymbolLink::getUnmangled($symbol) == ['f_class_exists'] &&
        isset($symbol->args[0])) {

      $symbols = [];
      foreach ([$symbol->getAttribute('scope', '')] as $scope)
        foreach (inference\Value::get($symbol->args[0]) as $value) {
          if ($value['type'] != 't_string')
            $value = ['type' => 't_string', 'value' => ''];
          $symbols[] = SymbolLink::lookupSinglePhpId($value['value'], $scope);
        }

      $exists = false;
      foreach ($symbols as $sx) {
        if (isset(op\metaContext('code')->data['symbols'][$sx]['declarationNodes'])
            && count(op\metaContext('code')->data['symbols'][$sx]['declarationNodes']) > 0) {
          $exists = true;
        }

        return [['type' => 't_bool', 'value' => $exists]];
      }
    }

    if (($symbol instanceof Node) && NodeConcept::isInvocationNode($symbol)) {

      $values = [];

      foreach (inference\SymbolLink::get($symbol) as $symbolx)
        if (isset(op\metaContext('code')->data['symbols'][$symbolx]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbolx]['declarationNodes'] as $definitionNode)
              if (NodeConcept::isExecutionContextNode($definitionNode)) {
                foreach ($definitionNode->getAttribute('returnValues', []) as $value)
                  $values[Value::valueKey($value)] = $value;
                foreach (inference\SymbolLink::get($definitionNode) as $symbolz)
                    foreach (Value::lookup($symbolz . '.r') as $value)
                      $values[Value::valueKey($value)] = $value;
              }

      return array_values($values);

    }

    if ($symbol instanceof Node\Expr\ClosureUse) {
      $values = [];
      foreach (inference\SymbolLink::get($symbol) as $symbolx)
        foreach (Value::lookup($symbolx) as $value)
          $values[Value::valueKey($value)] = $value;
      foreach (inference\SymbolLink::get($symbol) as $symbolx) {
        $symbolx = Symbol::concat(Scope::parent(Scope::symbolScope($symbolx)), Symbol::unqualified($symbolx));
        foreach (Value::lookup($symbolx) as $value)
          $values[Value::valueKey($value)] = $value;
      }
      foreach (inference\Value::get($symbol) as $value)
        $values[Value::valueKey($value)] = $value;
      return array_values($values);
    }

    if ($symbol instanceof Node) {
      $values = [];
      foreach (inference\SymbolLink::get($symbol) as $symbolx)
        foreach (Value::lookup($symbolx) as $value)
          $values[Value::valueKey($value)] = $value;
      foreach (inference\Value::get($symbol) as $value)
        $values[Value::valueKey($value)] = $value;
      return array_values($values);
    }

    if (!$symbol)
      return [];

    $unqualifiedSymbol = Symbol::unqualified($symbol);

    foreach (Scope::visibleScopes(Scope::symbolScope($symbol)) as $visibleScope) {
      $lookupSymbol = Symbol::concat($visibleScope, $unqualifiedSymbol);
      if (isset(op\metaContext(IIData::class)['values:' . $lookupSymbol]))
        return array_values(op\metaContext(IIData::class)['values:' . $lookupSymbol]);
    }

    /**
     * Constants can fallback to same namespace and global namespace.
     * @see http://www.php.net/manual/en/language.namespaces.fallback.php#example-258
     */
    if (in_array(Symbol::symbolIdentifierGroup($symbol), ['constant'])) {

      if (isset(op\metaContext('code')->data['symbols'][$symbol]['values']))
        return op\metaContext('code')->data['symbols'][$symbol]['values'];

      foreach ([Scope::namespaceScope($symbol), ''] as $lookupScope) {
        $lookupSymbol = Symbol::concat($lookupScope, $unqualifiedSymbol);
        if (isset(op\metaContext(IIData::class)['values:' .$lookupSymbol]))
          return array_values(op\metaContext(IIData::class)['values:' .$lookupSymbol]);
      }
    }

    return [];

  }

  function evaluateArrayLiteral ($node) {

    if ($node instanceof Node\Expr\Array_)
      foreach ($node->items as $item) {

        if ($item->key) {
          $keys = [];
          foreach (inference\Value::get($item->key) as $keyValue)
            $keys[] = [
              'type' => '',
              'value' => Value::convertToArrayKeyValue($keyValue['value']),
            ];
          if (count($keys) > 0)
            $item->key->setAttribute('values', $keys);
        }

      }

  }

  /**
   * Convert to a key value following PHP array key conversion rules.
   *
   * @see http://php.net/manual/en/language.types.array.php
   */
  static function convertToArrayKeyValue ($keyValue) {

    /**
     * Strings containing valid integers will be cast to the integer type.
     * E.g. the key "8" will actually be stored under 8. On the other
     * hand "08" will not be cast, as it isn't a valid decimal integer.
     */
    if (is_string($keyValue) && (((string) ((int) $keyValue)) === (string) $keyValue))
      return (int) $keyValue;

    /**
     * Floats are also cast to integers, which means that the fractional part
     * will be truncated. E.g. the key 8.7 will actually be stored under 8.
     */
    if (is_float($keyValue))
      return (int) floor($keyValue);

    /**
     * Bools are cast to integers, too, i.e. the key true will actually be
     * stored under 1 and the key false under 0.
     */
    if (is_bool($keyValue))
      return $keyValue ? 1 : 0;

    /**
     * Null will be cast to the empty string, i.e. the key null will
     * actually be stored under "".
     */
    if (is_null($keyValue))
      return '';

    return $keyValue;

  }

  static function valueKey ($value) {
    return $value['type'] . '/' . json_encode($value['value']);
  }

  /**
   * Get node analysis-time known values.
   *
   * @param mixed $node Node whose type to get or a literal value.
   * @return string[]
   */
  static function get ($node) {

    if (!$node)
      return [];

    if (is_bool($node))
      return [[
        'type' => 't_bool',
        'value' => $node,
      ]];

    if (is_int($node))
      return [[
        'type' => 't_int',
        'value' => $node,
      ]];

    if (is_string($node))
      return [[
        'type' => 't_string',
        'value' => $node,
      ]];

    if (is_array($node)) {
      $values = [];
      foreach ($node as $nodeEntry)
        foreach (self::get($nodeEntry) as $value)
          $values[] = $value;
      return $values;
    }

    if ($node instanceof Node\Arg)
      return Value::get($node->value);

    if ($node instanceof Node && count($node->getAttribute('values', [])) > 0)
      return $node->getAttribute('values', []);

    $values = [];

    if ($node instanceof Node\Expr\Array_) {
      $valueTypes = [];
      $value = [];
      foreach ($node->items as $item) {
        foreach (Value::get($item->key) as $keyValue)
          foreach (Value::get($item->value) as $valueValue) {
            $valueTypes[] = $valueValue['type'];
            $value[$keyValue['value']] = $valueValue['value'];
          }
      }
      $values[] = [
        'type' => count(array_unique($valueTypes)) == 1 ? $valueTypes[0] . '[]' : '',
        'value' => $value,
      ];
    }


    if ($node instanceof Node)
      foreach (inference\Evaluation::evaluate($node) as $valueNode)
        if ($valueNode !== $node)
          foreach (Value::get($valueNode) as $value)
            $values[] = $value;

    if ($node instanceof Node\Expr\BinaryOp\Concat)
      foreach (self::get($node->left) as $leftValue)
        foreach (self::get($node->right) as $rightValue) {
          $type = 't_string';
          if (in_array($leftValue['type'], ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool'])
              && in_array($rightValue['type'], ['t_float', 't_autoFloat', 't_stringFloat']))
            $type = 't_stringFloat';
          if (in_array($leftValue['type'], ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool'])
              && in_array($rightValue['type'], ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool']))
            $type = 't_stringInt';
          $values[] = [
            'type' => $type,
            'value' => $leftValue['value'] . $rightValue['value'],
          ];
        }

    if ($node instanceof Node\Expr\ConstFetch) {
      if (strtolower($node->name->toString()) == 'null')
        $values[] = [
          'type' => '',
          'value' => null,
        ];
      if (strtolower($node->name->toString()) == 'true')
        $values[] = [
          'type' => 't_bool',
          'value' => true,
        ];
      if (strtolower($node->name->toString()) == 'false')
        $values[] = [
          'type' => 't_bool',
          'value' => false,
        ];
    }

    if ($node instanceof Node\Const_)
      foreach (self::get($node->value) as $value)
        $values[] = $value;

    if ($node instanceof Node\Expr\Ternary) {
      if ($node->if)
        foreach (self::get($node->if) as $value)
          $values[] = $value;
      foreach (self::get($node->else) as $value)
        $values[] = $value;
    }

    if ($node instanceof Node\Param)
      if ((count($values) == 0) && $node->default instanceof Node)
        foreach (self::get($node->default) as $value)
          $values[] = $value;

    if ($node instanceof Node\Scalar\DNumber)
      $values[] = [
        'type' => 't_float',
        'value' => $node->value,
      ];

    if ($node instanceof Node\Scalar\LNumber)
      $values[] = [
        'type' => 't_int',
        'value' => $node->value,
      ];

    if ($node instanceof Node\Scalar\MagicConst\Dir && $node->getAttribute('path', ''))
      $values[] = [
        'type' => 't_string',
        'value' => dirname($node->getAttribute('path', '')),
      ];

    if ($node instanceof Node\Scalar\String_) {
      $type = 't_string';
      if (is_numeric($node->value))
        $type = 't_stringFloat';
      if (strlen($node->value) > 0 && ltrim((int) $node->value, '0') === ltrim($node->value, '0'))
        $type = 't_stringInt';
      if ($node->value === '0' || $node->value === '1')
        $type = 't_stringBool';
      $values[] = [
        'type' => $type,
        'value' => $node->value,
      ];
    }

    $uniqueValues = function ($values) {
      $valueMap = [];
      foreach ($values as $value)
        $valueMap[$value['type'] . '/' . json_encode($value['value'])] = $value;
      return array_values($valueMap);
    };

    $node->setAttribute('values', $uniqueValues($values));

    return $uniqueValues($values);

  }

  static function getReturn ($node) {

    $returnValues = [];

    if (NodeConcept::isExecutionContextNode($node))
      foreach (inference\SymbolLink::get($node) as $symbol)
        foreach (Value::lookup($symbol . '.r') as $returnValue)
          $returnValues[] = $returnValue;

    return $returnValues;

  }

  /**
   * Does the node always evaluate to `true`?
   *
   * @param object $node Value holding node.
   * @return bool
   */
  static function isTrue ($node) {
    $values = inference\Value::get($node);
    if (count($values) == 1 && $values[0]['type'] == 't_bool' && $values[0]['value'] == true)
      return true;
    return false;
  }

  /**
   * Does the node always evaluate to `false`?
   *
   * @param object $node Value holding node.
   * @return bool
   */
  static function isFalse ($node) {
    $values = inference\Value::get($node);
    if (count($values) == 1 && $values[0]['type'] == 't_bool' && $values[0]['value'] == false)
      return true;
    return false;
  }

}
