<?php

namespace phlint\inference;

use \ArrayObject;
use \luka8088\ExtensionCall;
use \luka8088\ExtensionCallExtends;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\inference\Attribute;
use \phlint\inference\Scope;
use \phlint\inference\Symbol;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \phlint\phpLanguage;
use \PhpParser\Comment;
use \PhpParser\Node;

class Type {

  function getIdentifier () {
    return 'type';
  }

  function getPass () {
    return 30;
  }

  function getDependencies () {
    return [
      'attribute',
      'scope',
      'symbol',
      'typeLiteral',
    ];
  }

  /** @see /documentation/glossary/intermediatelyInferredData.md */
  protected $iiData = null;

  function setIIData ($iiData) {
    $this->iiData = $iiData;
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function beforeTraverse () {

    op\metaContext('code')->data['symbols']['t_mixed'] = [
      'id' => 't_mixed',
      'phpId' => 'mixed',
      'aliasOf' => '',
      'definitionNodes' => [],
    ];

    // @todo: Remove
    foreach (phpLanguage\Fixture::$implicitTypeConversions as $type1 => $types2) {
      if (!isset(op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type1, 'type')]))
        op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type1, 'type')] = [
          'id' => Symbol::fullyQualifiedIdentifier($type1, 'type'),
          'phpId' => $type1,
          'aliasOf' => '',
          'definitionNodes' => [],
        ];
      foreach ($types2 as $type2)
        if (!isset(op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type2, 'type')]))
          op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type2, 'type')] = [
            'id' => Symbol::fullyQualifiedIdentifier($type2, 'type'),
            'phpId' => $type2,
            'aliasOf' => '',
            'definitionNodes' => [],
          ];
    }

    op\metaContext('code')->data['symbols']['t_mixed'] = [
      'id' => 't_mixed',
      'phpId' => 'mixed',
      'aliasOf' => '',
      'declarationNodes' => [],
    ];

    foreach (phpLanguage\Fixture::$implicitTypeConversions as $type1 => $types2) {
      if (!isset(op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type1, 'type')]))
        op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type1, 'type')] = [
          'id' => Symbol::fullyQualifiedIdentifier($type1, 'type'),
          'phpId' => $type1,
          'aliasOf' => '',
          'declarationNodes' => [],
        ];
      foreach ($types2 as $type2)
        if (!isset(op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type2, 'type')]))
          op\metaContext('code')->data['symbols'][Symbol::fullyQualifiedIdentifier($type2, 'type')] = [
            'id' => Symbol::fullyQualifiedIdentifier($type2, 'type'),
            'phpId' => $type2,
            'aliasOf' => '',
            'declarationNodes' => [],
          ];
    }

    foreach (phpLanguage\Fixture::$implicitTypeConversions as $type => $implicitlyConvertableTypes) {
      $symbol = inference\Symbol::identifier($type, 'type');
      assert(is_string($symbol) && $symbol != '');
      if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
        op\metaContext('code')->data['symbols'][$symbol] = [
          'id' => $symbol,
          'phpId' => $type,
          'aliasOf' => '',
          'declarationNodes' => [],
          'linkedNodes' => [],
          'implicitlyConvertable' => [],
        ];
      foreach ($implicitlyConvertableTypes as $implicitlyConvertableType)
        op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'][] =
          inference\Symbol::identifier($implicitlyConvertableType, 'type');
      if (isset(op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable']))
        op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'] = array_unique(op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable']);
    }

  }

  function visitNode ($node) {

    $this->inferConditionalGuarantees($node);

    $this->inferSymbolTypes($node);

    $this->inferTypes($node);

    $this->interTypesDisplay($node);

  }

  function leaveNode ($node) {

    $this->inferExecutionBranchEffect($node);

  }

  function inferConditionalGuarantees ($node) {

    foreach (inference\ConditionalGuarantee::evaluate($node) as $guarantee) {

      foreach ($guarantee['excludesTypes'] as $type) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['types:' . $scopedSymbol])) {
              $d = [];
              $p = $scope;
              while (true) {
                $p = Scope::parent($p);
                $pa = Symbol::concat($p, Symbol::unqualified($symbol));
                if (isset($this->iiData['types:' . $pa])) {
                  $d = $this->iiData['types:' . $pa];
                  break;
                }
                if (!$p)
                  break;
              }

              $this->iiData['types:' . $scopedSymbol] = $d;

            }

            $this->iiData['types:' . $scopedSymbol] = array_filter($this->iiData['types:' . $scopedSymbol], function ($t) use ($type) { return $t != $type; });

          }

      }

      if (count($guarantee['includesTypes']) > 0) {

        $types = [];

        foreach ($guarantee['includesTypes'] as $includesTypesNode)
          foreach (inference\SymbolLink::get($includesTypesNode) as $type)
            $types[] = $type;

        foreach ([$guarantee['scope']->getAttribute('scopeDeclare', '')] as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            $this->iiData['types:' . $scopedSymbol] = $types;
          }

      }

      foreach ($guarantee['includesConcepts'] as $concept) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['types:' . $scopedSymbol])) {
              $d = [];
              $p = $scope;
              while (true) {
                $p = Scope::parent($p);
                $pa = Symbol::concat($p, Symbol::unqualified($symbol));
                if (isset($this->iiData['types:' . $pa])) {
                  $d = $this->iiData['types:' . $pa];
                  break;
                }
                if (!$p)
                  break;
              }

              $this->iiData['types:' . $scopedSymbol] = $d;

            }

            $this->iiData['types:' . $scopedSymbol] = array_filter($this->iiData['types:' . $scopedSymbol], function ($t) use ($concept) { if ($concept == 'array') return substr($t, -2) == '[]'; return true; });

          }

      }

      foreach ($guarantee['excludesConcepts'] as $concept) {

        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {
            $scopedSymbol = Symbol::concat($scope, Symbol::unqualified($symbol));
            if (!isset($this->iiData['types:' . $scopedSymbol])) {
              $d = [];
              $p = $scope;
              while (true) {
                $p = Scope::parent($p);
                $pa = Symbol::concat($p, Symbol::unqualified($symbol));
                if (isset($this->iiData['types:' . $pa])) {
                  $d = $this->iiData['types:' . $pa];
                  break;
                }
                if (!$p)
                  break;
              }

              $this->iiData['types:' . $scopedSymbol] = $d;

            }

            $this->iiData['types:' . $scopedSymbol] = array_filter($this->iiData['types:' . $scopedSymbol], function ($t) use ($concept) { if ($concept == 'array') return substr($t, -2) == '[]'; return true; });

          }

      }

    }

  }

  function inferExecutionBranchEffect ($node) {

    if ($node instanceof Node\Stmt\If_) {

      $guarantees = inference\ConditionalGuarantee::evaluateCondition($node->cond);

      foreach ($guarantees as $guarantee) {

        $scope = $node->getAttribute('scope', '');

        $types = [];

        foreach ($guarantee['includesTypes'] as $includesTypesNode)
          foreach (inference\SymbolLink::get($includesTypesNode) as $type)
            $types[] = $type;

        foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

          $symbolx = Symbol::concat($scope, Symbol::unqualified($symbol));

          if (isset($this->iiData['types:' . $symbol]) && count($this->iiData['types:' . $symbol]) > 0)
            if (isset($this->iiData['types:' . $symbolx]))
              $this->iiData['types:' . $symbolx] = array_diff(
                $this->iiData['types:' . $symbolx],
                $types
              );

        }

      }

    }

  }

  function inferSymbolTypes ($node) {

    if ($node instanceof Node\Expr\Assign)
      $this->registerSymbolsTypes($node->var, inference\Type::get($node->expr));

    if ($node instanceof Node\Expr\Assign) {

      if ($node->var instanceof Node\Expr\ArrayDimFetch) {

        if (!$node->var->dim || count($node->var->var->getAttribute('isInitialization', [])) > 0)
        $this->registerSymbolsTypes($node->var->var, array_filter(array_map(function ($type) { if ($type == 't_undefined') return ''; return $type . '[]'; }, inference\Type::get($node->expr))));

      }

      if ($node->var instanceof Node\Expr\Variable) {
        foreach (inference\Attribute::get($node->var) as $attribute) {

          if ($attribute instanceof Node\Expr\New_ &&
              count($attribute->args) >= 1 &&
              inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'var']]) {

            foreach ([$node->var->getAttribute('scope', '')] as $scope) {
              $typeSymbol = SymbolLink::lookupSinglePhpId($attribute->args[1]->value->items[0]->value->value, $scope);
              if ($typeSymbol)
                $this->registerSymbolsTypes($node->var, [$typeSymbol]);
            }
          }
        }
      }

    }

    if ($node instanceof Node\Expr\Closure)
      foreach ($node->uses as $use_)
        $this->registerSymbolsTypes($use_, inference\Type::lookupSymbol(
          Symbol::concat($node->getAttribute('scope', ''), Symbol::identifier($use_->var, 'variable'))
        ));

    if ($node instanceof Node\Expr\ClosureUse)
      $this->registerSymbolsTypes($node, inference\Type::get($node));

    if ($node instanceof Node\Param)
      $this->registerSymbolsTypes($node, inference\Type::get($node));

    if ($node instanceof Node\Param)
      foreach (inference\Attribute::get($node) as $attribute) {
        if ($attribute instanceof Node\Expr\New_ &&
            count($attribute->args) >= 1 &&
            inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'var']])
        if (isset($attribute->args[1]->value->items[0])) {
          foreach ([$node->getAttribute('scope', '')] as $scope) {
            $typeSymbol = SymbolLink::lookupSinglePhpId($attribute->args[1]->value->items[0]->value->value, $scope);
            if ($typeSymbol)
              $this->registerSymbolsTypes($node, [$typeSymbol]);
          }
        }
      }

    if ($node instanceof Node\Stmt\Foreach_) {
      if ($node->keyVar)
        $this->registerSymbolsTypes($node->keyVar, inference\Type::get($node->keyVar));
      if ($node->expr && $node->valueVar) {
        $valueTypes = [];
        foreach (inference\Type::get($node->expr) as $type)
          if (substr($type, -2) == '[]')
            $valueTypes[] = substr($type, 0, -2);
        $this->registerSymbolsTypes($node->valueVar, array_unique(array_merge(inference\Type::get($node->valueVar), $valueTypes)));
      }
    }

    if (inference\Execution::isReachable($node))
    if ($node instanceof Node\Stmt\Return_) {
      foreach ([$node->getAttribute('scope', '')] as $scope) {
        $scope = Scope::contextScope($scope);
        if ($scope) {
          $this->registerSymbolsTypes([$scope . '.r'], array_unique(array_merge(
            isset($this->iiData['types:' . $scope . '.r']) ? $this->iiData['types:' . $scope . '.r'] : [],
            inference\Type::get($node->expr)
          )));
        }
      }
    }

  }

  function inferTypes ($node) {

    if ($node instanceof Node\Expr\Array_) {
      $itemTypes = [];
      foreach ($node->items as $itemNode) {
        $itemNodeTypes = inference\Type::get($itemNode);
        if (count($itemNodeTypes) == 0)
          $itemTypes[] = '';
        foreach ($itemNodeTypes as $itemNodeType)
          $itemTypes[] = $itemNodeType;
      }
      $common = Type::common(array_unique($itemTypes));
      if ($common)
        op\metaContext(IIData::class)['nodeTypes:' . spl_object_hash($node)] = [$common . '[]'];
    }

  }

  function registerSymbolsTypes ($symbols, $types) {

    if ($symbols instanceof Node)
      $symbols = inference\SymbolLink::get($symbols);

    foreach ($symbols as $symbol) {

      if (!$symbol)
        continue;

      $this->iiData['types:' . $symbol] = [];

      foreach (Symbol::visibleScopes($symbol) as $scopeSymbol) {
        if (!isset($this->iiData['types:' . $scopeSymbol]))
          $this->iiData['types:' . $scopeSymbol] = [];
        $this->iiData['types:' . $scopeSymbol] = array_unique(array_merge($this->iiData['types:' . $scopeSymbol], $types));
      }

    }

  }

  /**
   * Lookup the node types.
   * Note that this call can be significantly expensive.
   * For general purpose it is better to call `::get` which will
   * call lookup implicitly if needed.
   *
   * @internal
   */
  static function lookup ($node) {

    if ($node instanceof Node\Expr\BinaryOp\Concat) {
      $types = [];
      foreach (Type::get($node->left) as $leftType)
        foreach (Type::get($node->right) as $rightType) {
          if (in_array($leftType, ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool']) && in_array($rightType, ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool'])) {
            $types[] = 't_stringInt';
            continue;
          }
          if (in_array($leftType, ['t_int', 't_autoInteger', 't_stringInt', 't_autoBool', 't_stringBool']) && in_array($rightType, ['t_float', 't_autoFloat', 't_stringFloat'])) {
            $types[] = 't_stringFloat';
            continue;
          }
          $types[] = 't_string';
        }
      return array_unique($types);
    }

    if ($node instanceof Node\Expr\Closure)
      return inference\SymbolLink::get($node);

    if ($node instanceof Node\Expr\New_)
      return inference\SymbolLink::get($node);

    if ($node instanceof Node\Name)
      return inference\SymbolLink::get($node);

    if (NodeConcept::isInvocationNode($node)) {
      $types = [];
      foreach (inference\SymbolLink::get($node) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $declarationNode)
              if (NodeConcept::isExecutionContextNode($declarationNode))
                foreach (inference\Type::getReturn($declarationNode) as $type)
                  $types[] = $type;
      return array_unique($types);
    }

    // @todo: Remove
    if ($node instanceof Node\Param && $node->type)
      return array_filter(inference\SymbolLink::get($node->type), function ($z) {
        return $z == 't_float' || strpos($z, '[]') !== false;
      });

    if ($node instanceof Node\Param && $node->type)
      return inference\SymbolLink::get($node->type);

    if ($node instanceof Node\Param) {
      $types = [];
      foreach (inference\Attribute::get($node) as $attribute)
      if ($attribute instanceof Node\Expr\New_ &&
          count($attribute->args) >= 1 &&
          inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'param']])
      if (isset($attribute->args[1], $attribute->args[1]->value->items[0])) {
        $parameterTypeRaw = inference\Value::get($attribute->args[1]->value->items[0]->value->value);
        assert(count($parameterTypeRaw) == 1);
        if (strpos($parameterTypeRaw[0]['value'], '$') !== 0) {
          // @todo: Rewrite.
          if (count(array_intersect(explode('|', $parameterTypeRaw[0]['value']), ['', 'array', 'mixed', 'object'])) == 0)
            foreach (array_filter(array_map(function ($phpDocType) use ($node) { return SymbolLink::lookupSinglePhpId($phpDocType, $node->getAttribute('scope', '')); }, explode('|', $parameterTypeRaw[0]['value']))) as $type)
              $types[] = $type;
        }
      }
      return $types;
    }

    if ($node instanceof Node) {
      $types = [];
      foreach (inference\SymbolLink::get($node) as $symbol)
        foreach (inference\Type::lookupSymbol($symbol) as $type)
          $types[] = $type;
      return array_unique($types);
    }

    return [];

  }

  /**
   * Lookup the types that a symbol would yield if it
   * would be, for example, evaluated as an expression.
   *
   * @param string $symbol
   * @return string[]
   */
  static function lookupSymbol ($symbol) {

    $types = [];

    $unqualifiedSymbol = Symbol::unqualified($symbol);
    foreach (Scope::visibleScopes(Scope::symbolScope($symbol)) as $visibleScope) {
      $lookupSymbol = Symbol::concat($visibleScope, $unqualifiedSymbol);
      if (isset(op\metaContext(IIData::class)['types:' . $lookupSymbol])) {
        foreach (op\metaContext(IIData::class)['types:' . $lookupSymbol] as $type)
          $types[] = $type;
        break;
      }
    }

    return array_unique($types);

  }

  static $_implicitTypeConversionsMap = [];

  static function implicitTypeConversionsMap () {

    if (count(Type::$_implicitTypeConversionsMap) == 0) {

      $populate = function ($type, $implicitlyConvertibleTypes) use (&$populate) {
        foreach ($implicitlyConvertibleTypes as $implicitlyConvertibleType) {
          Type::$_implicitTypeConversionsMap[Symbol::identifier($type, 'auto')] = array_unique(array_merge(
            Type::$_implicitTypeConversionsMap[Symbol::identifier($type, 'auto')],
            [Symbol::identifier($implicitlyConvertibleType, 'auto')]
          ));
          if (isset(phpLanguage\Fixture::$implicitTypeConversions[$implicitlyConvertibleType]))
            $populate($type, phpLanguage\Fixture::$implicitTypeConversions[$implicitlyConvertibleType]);
        }
      };

      foreach (phpLanguage\Fixture::$implicitTypeConversions as $type => $implicitlyConvertibleTypes) {
        Type::$_implicitTypeConversionsMap[Symbol::identifier($type, 'auto')] = [
          Symbol::identifier($type, 'auto'),
        ];
        $populate($type, $implicitlyConvertibleTypes);
      }

    }

    return Type::$_implicitTypeConversionsMap;

  }

  static function common ($types) {

    $types = array_unique($types);

    if (count($types) == 0)
      return '';

    if (count($types) > count(array_filter($types)))
      return '';

    static $cachex = [];

    if (isset($cachex[serialize($types)]))
      return $cachex[serialize($types)];

    foreach ($types as $t)
      if ($t)
        assert(substr($t, 1, 1) == '_', $t);

    $isCompatibleCandidate = function ($typeCandidate) use ($types) {
      foreach ($types as $type) {
        if (!inference\Type::isImplicitlyConvertible($type, $typeCandidate))
          return false;
      }
      return true;
    };

    $compatibleCandidates = [];

    foreach ($types as $type) {
      if ($isCompatibleCandidate($type))
        $compatibleCandidates[] = $type;
      if (isset(Type::implicitTypeConversionsMap()[$type]))
        foreach (Type::implicitTypeConversionsMap()[$type] as $typeCandidate)
          if ($isCompatibleCandidate($typeCandidate))
            $compatibleCandidates[] = $typeCandidate;
    }

    $compatibleCandidates = array_unique($compatibleCandidates);

    $tooGeneralcandidates = [];

    foreach ($compatibleCandidates as $compatibleCandidate1)
      foreach ($compatibleCandidates as $compatibleCandidate2)
        if ($compatibleCandidate1 != $compatibleCandidate2)
          if (inference\Type::isImplicitlyConvertible($compatibleCandidate1, $compatibleCandidate2))
            $tooGeneralcandidates[] = $compatibleCandidate2;

    $tooGeneralcandidates = array_unique($tooGeneralcandidates);

    $successfulCandidates = array_values(array_diff($compatibleCandidates, $tooGeneralcandidates));

    if (count($successfulCandidates) > 0) {
      $cachex[serialize($types)] = $successfulCandidates[0];
      return $successfulCandidates[0];
    }

    $cachex[serialize($types)] = '';
    return '';

  }

  function interTypesDisplay ($node) {

    if (!($node instanceof Node\Expr\ClosureUse) && !($node instanceof Node\Param))
      return;

    $typesDisplay = [];

    foreach (inference\Type::get($node) as $type) {

      if (isset(op\metaContext('code')->data['symbols'][$type]) && op\metaContext('code')->data['symbols'][$type]['phpId']) {
        $typesDisplay[] = ltrim(op\metaContext('code')->data['symbols'][$type]['phpId'], '\\');
        continue;
      }

      $typesDisplay[] = str_replace('t_', '', $type);

    }

    if (count($typesDisplay) > 0)
      $node->setAttribute('typesDisplay', array_unique($typesDisplay));

  }

  /**
   * Get node analysis-time known types.
   *
   * @param object $node Node whose type to get.
   * @return string[]
   */
  static function get ($node) {

    if ($node === null)
      return [];

    assert(is_object($node));

    if ($node instanceof Node\Arg)
      return self::get($node->value);

    if ($node instanceof Node\Expr\ArrayDimFetch)
      return array_map(function ($arrayType) {
        return substr($arrayType, -2) == '[]' ? substr($arrayType, 0, -2) : 't_mixed';
      }, self::get($node->var));

    if ($node instanceof Node\Expr\ArrayItem)
      return self::get($node->value);

    if ($node instanceof Node\Expr\BinaryOp\BooleanAnd)
      return ['t_bool'];

    if ($node instanceof Node\Expr\Ternary)
      return array_unique(array_merge(self::get($node->if), self::get($node->else)));

    if ($node instanceof Node\Param && is_string($node->type) && in_array($node->type, ['string', 'int', 'bool']))
      return ['t_' . strtolower($node->type)];

    if ($node instanceof Node\Param && $node->type instanceof Node\Name)
      return self::get($node->type);

    if ($node instanceof Node\Scalar\DNumber)
      return ['t_float'];

    if ($node instanceof Node\Scalar\LNumber)
      return ['t_int'];

    if ($node instanceof Node\Scalar\MagicConst) {
      if ($node instanceof Node\Scalar\MagicConst\Line)
        return ['t_int'];
      return ['t_string'];
    }

    if ($node instanceof Node\Scalar\String_) {
      if ($node->value === '0' || $node->value === '1')
        return ['t_stringBool'];
      if (((string) ((int) $node->value)) === (string) $node->value)
        return ['t_stringInt'];
      if (is_numeric($node->value))
        return ['t_stringFloat'];
      return ['t_string'];
    }

    if (!isset(op\metaContext(IIData::class)['nodeTypes:' . spl_object_hash($node)]))
      op\metaContext(IIData::class)['nodeTypes:' . spl_object_hash($node)] = Type::lookup($node);

    return op\metaContext(IIData::class)['nodeTypes:' . spl_object_hash($node)];

  }

  /**
   * Get node analysis-time known return types.
   *
   * @param object $node Node whose return types to get.
   * @return string[]
   */
  static function getReturn ($node) {

    if (!isset(op\metaContext(IIData::class)['returnTypes:' . spl_object_hash($node)])) {

      $returnTypes = [];

      if ($node->returnType)
        foreach (inference\SymbolLink::get($node->returnType) as $returnType)
          $returnTypes[] = $returnType;

      // @todo: Refactor.
      foreach (inference\Attribute::get($node) as $attribute) {
        if ($attribute instanceof Node\Expr\New_ &&
            count($attribute->args) >= 1 &&
            inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'return']])
        if (isset($attribute->args[1]->value->items[0]))
          foreach (inference\Value::get($attribute->args[1]->value->items[0]->value->value) as $value)
            $returnTypes[] = SymbolLink::lookupSinglePhpId(
              $value['value'],
              $node->getAttribute('scope', ''),
              $node->getAttribute('inAnalysisScope', true)
            );
      }

      foreach (inference\Type::lookupSymbol(inference\Symbol::get($node) . '.r') as $returnType)
        $returnTypes[] = $returnType;

      op\metaContext(IIData::class)['returnTypes:' . spl_object_hash($node)] = array_unique($returnTypes);

    }

    return op\metaContext(IIData::class)['returnTypes:' . spl_object_hash($node)];

  }

  /**
   * Is type `$type1` implicitly convertible to type `$type2`.
   *
   * Strict type comparison is done according to rules of `declare(strict_types=1);`.
   * @see http://php.net/manual/en/functions.arguments.php#functions.arguments.type-declaration.strict
   *
   * @param string $type1
   * @param string $type2
   * @param bool $strictType Do a strict type comparison.
   * @return bool
   */
  static function isImplicitlyConvertible ($type1, $type2, $strictType = false, $redundants = []) {

    if ($type1 == $type2)
      return true;

    if (!isset(op\metaContext('code')->data['symbols'][$type1]))
      return false;

    if (isset(op\metaContext('code')->data['symbols'][$type1]['aliasOf'])
        && op\metaContext('code')->data['symbols'][$type1]['aliasOf'])
      return self::isImplicitlyConvertible(
        op\metaContext('code')->data['symbols'][$type1]['aliasOf'],
        $type2,
        array_merge($redundants, [$type1])
      );

    if (!isset(op\metaContext('code')->data['symbols'][$type1]['implicitlyConvertable']))
      return false;

    foreach (array_diff(op\metaContext('code')->data['symbols'][$type1]['implicitlyConvertable'], $redundants)
        as $implicitlyConvertableType)
      if (self::isImplicitlyConvertible(
            $implicitlyConvertableType,
            $type2,
            array_merge($redundants, op\metaContext('code')->data['symbols'][$type1]['implicitlyConvertable'])
          ))
        return true;

    return false;

  }

  /**
   * Is type traversable?
   *
   * @param string $type Type to check.
   * @return bool
   */
  static function isTraversable ($type) {

    // @todo: Remove.
    if (in_array($type, ['t_mixed', 't_array']))
      return true;

    if (substr($type, -2) == '[]')
      return true;

    if (inference\Type::isImplicitlyConvertible($type, 'c_traversable'))
      return true;

    return false;

  }

}
