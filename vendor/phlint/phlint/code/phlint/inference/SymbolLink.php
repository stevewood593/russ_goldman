<?php

namespace phlint\inference;

use \luka8088\ExtensionCall;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\inference\Symbol;
use \phlint\inference\Type;
use \phlint\inference\Value;
use \phlint\NodeConcept;
use \PhpParser\Node;

/**
 * Links invocation to invocable symbols.
 */
class SymbolLink {

  function getIdentifier () {
    return 'symbolLink';
  }

  protected $importMap = [];

  function getPass () {
    return 30;
  }

  function resetState () {
    $this->importMap = [];
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function beforeTraverse ($nodes) {
      $this->resetState();
  }

  function visitNode ($node) {

    $this->inferSymbolLink($node);

    $this->inferImplicitlyConvertable($node);

    $this->inferConditionalGuarantees($node);
    $this->inferClassTraits($node);

  }

  /**
   * Links a node to a symbol.
   * This method links nodes whose fully qualified symbol identifier is not
   * necessarily known without a lookup - and so it is done in a separate pass
   * from `Symbol` inference.
   *
   * @param mixed $node
   * @return void
   */
  function inferSymbolLink ($node) {

    if ($node instanceof Node\Expr\ClassConstFetch)
      $this->linkNode($node, 'constant');

    if ($node instanceof Node\Expr\ClassConstFetch && $node->name == 'class')
      $this->linkNode($node->class, 'class');

    if ($node instanceof Node\Expr\Closure)
      $this->linkNode($node->returnType, 'class');

    if ($node instanceof Node\Expr\ConstFetch)
      $this->linkNode($node, 'constant');

    if ($node instanceof Node\Expr\FuncCall)
      $this->linkNode($node, 'function');

    if (NodeConcept::isInvocationNode($node) &&
        inference\SymbolLink::getUnmangled($node) == ['f_function_exists'] &&
        count($node->args) > 0) {
      $symbols = [];
      foreach ([$node->getAttribute('scope', '')] as $scope)
        foreach (inference\Value::get($node->args[0]) as $value)
          $symbols[] = Symbol::concat($scope, Symbol::identifier($value['value'], 'function'));
      $node->args[0]->setAttribute('symbols', $symbols);
      op\metaContext(IIData::class)['nodeSymbols:' . spl_object_hash($node->args[0])] = $symbols;
    }

    if ($node instanceof Node\Expr\Instanceof_)
      $this->linkNode($node->class, 'class');

    if ($node instanceof Node\Expr\MethodCall)
      $this->linkNode($node, 'function');

    if ($node instanceof Node\Expr\New_)
      $this->linkNode($node, 'class');

    if ($node instanceof Node\Expr\StaticCall)
      $this->linkNode($node, 'function');

    if ($node instanceof Node\Expr\StaticPropertyFetch)
      $this->linkNode($node, 'variable');

    if ($node instanceof Node\Param)
      $this->linkNode($node->type, 'class');

    if ($node instanceof Node\Stmt\Catch_)
      foreach ($node->types as $type)
        $this->linkNode($type, 'class');

    if ($node instanceof Node\Stmt\Class_) {
      $this->linkNode($node->extends, 'class');
      foreach ($node->implements as $interface_)
        $this->linkNode($interface_, 'class');
    }

    if ($node instanceof Node\Stmt\ClassMethod)
      $this->linkNode($node->returnType, 'class');

    if ($node instanceof Node\Stmt\Function_)
      $this->linkNode($node->returnType, 'class');

    if ($node instanceof Node\Stmt\Interface_)
      foreach ($node->extends as $interface_)
        $this->linkNode($interface_, 'class');

    if ($node instanceof Node\Stmt\TraitUse)
      foreach ($node->traits as $traitName)
        $this->linkNode($traitName, 'class');

  }

  function linkNode ($node, $symbolIdentifierGroup = '') {
    if (!$node || !is_object($node))
      return;
    $this->extensionInterface['phlint.analysis.inference.symbolLink']($node, $symbolIdentifierGroup);
  }

  /**
   * @ExtensionCall("phlint.analysis.inference.symbolLink/default")
   */
  function symbolLink ($node, $symbolIdentifierGroup = '') {

    if (count($node->getAttribute('symbols', [])) > 0)
      return;

    $symbols = [];

    $this->extensionInterface['phlint.analysis.inference.symbolLookup']($symbols, $node, $symbolIdentifierGroup);

    $node->setAttribute('symbols', $symbols);

    op\metaContext(IIData::class)['nodeSymbols:' . spl_object_hash($node)] = $symbols;

    $this->registerLink($node);

  }

  function registerLink ($node) {

    foreach (inference\SymbolLink::get($node) as $symbol) {

      assert(is_string($symbol) && $symbol != '');
      assert(strpos($symbol, '.') !== 0);
      assert($symbol != 'f_');

      if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
        op\metaContext('code')->data['symbols'][$symbol] = [
          'id' => $symbol,
          'phpId' => '',
          'aliasOf' => '',
          'definitionNodes' => [],
          'declarationNodes' => [],
          'linkedNodes' => [],
        ];

      if (!isset(op\metaContext('code')->data['symbols'][$symbol]['linkedNodes']))
        op\metaContext('code')->data['symbols'][$symbol]['linkedNodes'] = [];

      if ($node) {

        $isNodeAttached = false;
        foreach (op\metaContext('code')->data['symbols'][$symbol]['linkedNodes'] as $existingLinkedNode)
          if ($existingLinkedNode === $node)
            $isNodeAttached = true;
        if (!$isNodeAttached)
          op\metaContext('code')->data['symbols'][$symbol]['linkedNodes'][] = $node;

        if (!op\metaContext('code')->data['symbols'][$symbol]['phpId']) {

          $phpId = op\metaContext('code')->data['symbols'][$symbol]['phpId'];

          /**
           * Added only for optimization purposes.
           * Namespaces in PHP are always top level.
           */
          if (!$phpId && ($node instanceof Node\Stmt\Namespace_))
            $phpId = inference\Symbol::name($node);

          if (!$phpId) {
            $namedScope = $symbol;
            while ($namedScope) {
              $namedScope = inference\Scope::namedAncestor($namedScope);
              if (isset(op\metaContext('code')->data['symbols'][$namedScope]))
                break;
            }
            $phpId = ($namedScope ? op\metaContext('code')->data['symbols'][$namedScope]['phpId'] : '') . '\\' . inference\Symbol::name($node);
          }

          op\metaContext('code')->data['symbols'][$symbol]['phpId'] = ltrim($phpId, '\\');

        }

      }

    }

  }

  function inferImplicitlyConvertable ($node) {

    if ($node instanceof Node\Stmt\Class_)
      if ($node->extends)
        foreach (inference\SymbolLink::get($node) as $symbol)
          foreach (inference\SymbolLink::get($node->extends) as $implicitlyConvertableSymbol) {
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'][] = $implicitlyConvertableSymbol;
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'] = array_unique(op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable']);
          }

    if ($node instanceof Node\Stmt\Class_)
      foreach ($node->implements as $interface_)
        foreach (inference\SymbolLink::get($node) as $symbol)
          foreach (inference\SymbolLink::get($interface_) as $implicitlyConvertableSymbol) {
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'][] = $implicitlyConvertableSymbol;
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'] = array_unique(op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable']);
          }

    if ($node instanceof Node\Stmt\Interface_)
      foreach ($node->extends as $interface_)
        foreach (inference\SymbolLink::get($node) as $symbol)
          foreach (inference\SymbolLink::get($interface_) as $implicitlyConvertableSymbol) {
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'][] = $implicitlyConvertableSymbol;
            op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable'] = array_unique(op\metaContext('code')->data['symbols'][$symbol]['implicitlyConvertable']);
          }

    if (NodeConcept::isInvocationNode($node))
      foreach (inference\SymbolLink::get($node) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $declarationNode)
            foreach ($node->args as $argumentOffset => $argument) {

              if (!isset($declarationNode->params[$argumentOffset]))
                continue;

              foreach (inference\Type::get($argument) as $argumentType)
                foreach (inference\Type::get($declarationNode->params[$argumentOffset]) as $parameterType) {
                  if ($declarationNode->params[$argumentOffset]->variadic)
                    $parameterType = substr($parameterType, -2) == '[]' ? substr($parameterType, 0, -2) : '';

                  $this->loadImplicitlyConvertible([$argumentType], $parameterType);

                }

            }

  }

  function loadImplicitlyConvertible ($fromTypes, $toType, $redundants = []) {

    foreach ($fromTypes as $fromType)
      if (inference\Type::isImplicitlyConvertible($fromType, $toType))
        return;

    foreach ($fromTypes as $fromType)
      if (isset(op\metaContext('code')->data['symbols'][$fromType]['phpId'])) {
        Symbol::autoload(op\metaContext('code')->data['symbols'][$fromType]['phpId']);
        if (inference\Type::isImplicitlyConvertible($fromType, $toType))
          return;
      }

    $implicitlyConvertableTypes = [];

    foreach ($fromTypes as $fromType)
      if (isset(op\metaContext('code')->data['symbols'][$fromType]['implicitlyConvertable']))
        foreach (op\metaContext('code')->data['symbols'][$fromType]['implicitlyConvertable'] as $implicitlyConvertableType)
          $implicitlyConvertableTypes[] = $implicitlyConvertableType;

    if (count($implicitlyConvertableTypes) > 0)
      $this->loadImplicitlyConvertible(
        array_diff($implicitlyConvertableTypes, $redundants),
        $toType,
        array_merge($redundants, $fromTypes)
      );

  }

  function inferConditionalGuarantees ($node) {

    if (($node instanceof Node\Stmt\If_) || ($node instanceof Node\Stmt\ElseIf_) || ($node instanceof Node\Expr\Ternary)) {

      $guarantees = inference\ConditionalGuarantee::evaluateCondition($node->cond);

      foreach ($guarantees as $guarantee) {

        foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

          $symbol = Symbol::concat($node->getAttribute('scopeDeclare', ''), Symbol::unqualified($symbol));

          if (in_array('t_undefined', $guarantee['excludesTypes'])) {

            if (Symbol::symbolIdentifierGroup($symbol) == 'function') {
              assert(is_string($symbol) && $symbol != '');
              if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
                op\metaContext('code')->data['symbols'][$symbol] = [
                  'id' => $symbol,
                  'phpId' => '',
                  'aliasOf' => '',
                  'declarationNodes' => [],
                ];
              op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'][] = new Node\Stmt\Function_('', [], [
                'isSpecializationTemp' => true,
                'symbols' => [$symbol],
                'scopeDeclare' => $symbol,
              ]);
            }

          }

        }

      }

    }

  }

  /**
   * Link the current class that has the `Node\Stmt\TraitUse` statement
   * to the referenced trait.
   *
   * class A {
   *   use T1;
   *   use T2;
   * }
   *
   * Will produce: $classNode->setAttribute('traits', ['c_t1', 'c_t2']);
   */
  function inferClassTraits ($node) {

    if (!($node instanceof Node\Stmt\TraitUse))
      return;

    foreach ([$node->getAttribute('context', '')] as $context)
      foreach (op\metaContext('code')->data['symbols'][$context]['declarationNodes'] as $classNode) {
        assert(($classNode instanceof Node\Stmt\Class_) || ($classNode instanceof Node\Stmt\Trait_));
        $traits = $classNode->getAttribute('traits', []);
        foreach ($node->traits as $traitNameNode)
          foreach (inference\SymbolLink::get($traitNameNode) as $traitSymbol)
            $traits[] = $traitSymbol;
        $classNode->setAttribute('traits', $traits);
      }

  }

  /** @ExtensionCall("phlint.analysis.inference.symbolLookup/default") */
  function symbolLookup (&$symbols, $node, $symbolIdentifierGroup = '') {
    $symbols = $this->lookup($node, $symbolIdentifierGroup, '', $node->getAttribute('inAnalysisScope', true));
  }

  /**
   * @see /documentation/glossary/symbolIdentifierGroup.md
   * @see http://www.php.net/manual/en/language.namespaces.fallback.php
   */
  function lookup ($node, $symbolIdentifierGroup = '', $scope = '', $inAnalysisScope = true) {

    assert($node instanceof Node);
    assert(is_string($symbolIdentifierGroup));
    assert(is_string($scope));
    assert(is_bool($inAnalysisScope));

    if (($node instanceof Node) && !$scope)
      $scope = $node->getAttribute('scope', '');

    if ($scope == 'n_')
      $scope = '';

    $symbols = [];

    if ($node instanceof Node\Arg)
      return $this->lookup($node->value, $symbolIdentifierGroup, $scope, $inAnalysisScope);

    if ($node instanceof Node\Expr\ConstFetch)
      return $this->lookup($node->name, $symbolIdentifierGroup, $scope, $inAnalysisScope);

    if ($node instanceof Node\Expr\FuncCall) {
      #var_dump($this->lookup($node->name, $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'));
      return $this->lookup($node->name, $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function', '', $inAnalysisScope);
    }

    if ($node instanceof Node\Expr\MethodCall) {

      $containerSymbols = [];

      foreach (inference\Value::get($node->var) as $containerValue) {
        if ($containerValue['type'] == 't_mixed')
          continue;
        if ($containerValue['type'] == '' && $containerValue['value'] === null) {
          $containerSymbols[] = 't_mixed';
          continue;
        }
        $containerSymbols[] = $containerValue['type'];
      }

      foreach (inference\Type::get($node->var) as $containerType) {
        if ($containerType == 't_mixed')
          continue;
        $containerSymbols[] = $containerType;
      }

      #var_dump(inference\Type::get($node->var));

      #var_dump($this->lookup($node->var, 'class', $scope));
      #var_dump($node->class, 'class', $scope);
      #var_dump($this->lookup($node->class, 'class', $scope));
      #foreach (inference\Type::get($node->var) as $container)
      foreach ($containerSymbols as $container)
        foreach (inference\Value::get($node->name) as $member) {

          #var_dump($container);

          $memberSymbol = Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function');

          #var_dump($memberSymbol);

          #var_dump(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')));
          #var_dump($member);
          #var_dump(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')));
          #$symbol = SymbolLink::lookupSingle(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')), $inAnalysisScope);

          if ($inAnalysisScope)
          if (isset(op\metaContext('code')->data['symbols'][$container]['phpId']))
            Symbol::autoload(op\metaContext('code')->data['symbols'][$container]['phpId']);

          $symbol = SymbolLink::lookupSingle(Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'), $container, '', $inAnalysisScope);

          // @todo: Remove
          if (strpos($container, 't_') === 0 || $symbol == Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'))
            $symbol = Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'));

          #var_dump($symbol);

          if (!$symbol)
            $symbol = Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'));

          #var_dump($symbol);
          #var_dump($container);
          #var_dump(op\metaContext('code')->symbols[$container]['phpId']);
          #var_dump((isset(op\metaContext('code')->symbols[$container]) ? op\metaContext('code')->symbols[$container]['phpId'] : $container) . '::' . $member['value']);

          assert(is_string($symbol) && $symbol != '');

          // @todo: Rewrite
          if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
            op\metaContext('code')->data['symbols'][$symbol] = [
              'id' => $symbol,
              'phpId' => (isset(op\metaContext('code')->data['symbols'][$container]) ? op\metaContext('code')->data['symbols'][$container]['phpId'] : $container) . '::' . $member['value'],
              'aliasOf' => '',
              'declarationNodes' => [],
            ];

          $symbols[] = $symbol;
        }
    }

    if ($node instanceof Node\Expr\New_) {
      foreach (SymbolLink::lookup($node->class, $symbolIdentifierGroup, $scope, $inAnalysisScope) as $symbol)
        $symbols[] = $symbol;
    }

    if ($node instanceof Node\Expr\StaticCall) {
      #var_dump($node->class, 'class', $scope);
      #var_dump($this->lookup($node->class, 'class', $scope));
      #foreach ($this->lookup($node->class, 'class', $scope) as $container)

      $containerSymbols = [];

      if ($node->class instanceof Node\Name) {
        #$symbol = SymbolLink::lookupSingle(Symbol::identifier($node->class, 'class'), $node->class->getAttribute('scope', ''), '', $node);
        #if ($symbol)
        #  $containerSymbols[] = $symbol;
        foreach ($this->lookup($node->class, 'class', '', $inAnalysisScope) as $symbol)
          $containerSymbols[] = $symbol;
      }

      foreach (inference\Value::get($node->class) as $containerValue) {
        if ($inAnalysisScope)
        Symbol::autoload($containerValue['value']);
        $containerSymbols[] = Symbol::identifier($containerValue['value'], 'class');
      }

      #var_dump($node->class);
      #exit;

      foreach ($containerSymbols as $container) {
      #foreach (inference\Value::get($node->class) as $classValue) {
        #$container = Symbol::identifier($classValue['value'], 'class');
        foreach (inference\Value::get($node->name) as $member) {

          $memberSymbol = Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function');

          #var_dump(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')));
          #var_dump($member);
          #var_dump(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')));
          #$symbol = SymbolLink::lookupSingle(Symbol::concat($container, Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function')), $inAnalysisScope);

          $symbol = SymbolLink::lookupSingle(Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'), $container, '', $inAnalysisScope);

          if (!$symbol)
            $symbol = Symbol::concat(ltrim($container, '.'), Symbol::identifier($member['value'], $symbolIdentifierGroup ? $symbolIdentifierGroup : 'function'));

          assert(is_string($symbol) && $symbol != '');

          // @todo: Rewrite
          if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
            op\metaContext('code')->data['symbols'][$symbol] = [
              'id' => $symbol,
              'phpId' => (isset(op\metaContext('code')->data['symbols'][$container]) ? op\metaContext('code')->data['symbols'][$container]['phpId'] : $container) . '::' . $member['value'],
              'aliasOf' => '',
              'declarationNodes' => [],
            ];

          $symbols[] = $symbol;
        }
      }
    }

    if ($node instanceof Node\Expr\Variable) {
      if (in_array($symbolIdentifierGroup, ['function'])) {
        foreach (inference\Type::get($node) as $type) {
          if ($type == 't_string')
            continue;
          if ($type == 't_mixed')
            continue;
          $symbols[] = $type;
        }
      }
      if (in_array($symbolIdentifierGroup, ['class', 'constant', 'function', 'namespace', 'type'])) {
        #var_dump(inference\Value::get($node));
        foreach (inference\Value::get($node) as $value) {

          if ($value['type'] != 't_string')
            continue;

          $symbols[] = ltrim(Symbol::identifier($value['value'], $symbolIdentifierGroup), '.');
          #var_dump($symbols);

          $symbol = ltrim(Symbol::identifier($value['value'], $symbolIdentifierGroup), '.');

          assert(is_string($symbol) && $symbol != '');

          // @todo: Rewrite
          if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
            op\metaContext('code')->data['symbols'][$symbol] = [
              'id' => $symbol,
              'phpId' => $value['value'],
              'aliasOf' => '',
              'declarationNodes' => [],
            ];

        }
      }
    }

    if ($node instanceof Node\Name) {

      // @todo: Optimize and re-enable
      if (false)
      if (strtolower($node->toString()) == 'self') {
        foreach (Scope::get($node) as $scope) {
          while ($scope) {
            if (Symbol::symbolIdentifierGroup($scope) == 'class')
                break;
            $scope = Scope::parent($scope);
          }
          if ($scope)
            if (isset(op\metaContext('code')->data['symbols'][$scope]['definitionNodes']))
              foreach (op\metaContext('code')->data['symbols'][$scope]['definitionNodes'] as $definitionNode)
                foreach (inference\SymbolLink::get($definitionNode) as $symbol)
                  $symbols[] = $symbol;
        }
      }

      if (strtolower($node->toString()) != 'self') {

      $namespaceSymbol = Scope::namespaceScope($scope);

      if ($inAnalysisScope)
      Symbol::autoload(ltrim((isset(op\metaContext('code')->data['symbols'][$namespaceSymbol]) ? op\metaContext('code')->data['symbols'][$namespaceSymbol]['phpId'] : $namespaceSymbol) . '\\' . Symbol::name($node), '\\'));

      #var_dump($node, Symbol::identifier($node, $symbolIdentifierGroup), $scope);
      $symbol = SymbolLink::lookupSingle(Symbol::identifier($node, $symbolIdentifierGroup), $scope, $node->toString(), $inAnalysisScope);
      #var_dump($symbol);
      #if ($symbol)
      #  return [$symbol];
      #return [];

      if (!$symbol) {

        $namespaceSymbol = Scope::namespaceScope($scope);

        $symbol = Symbol::isAbsolute(Symbol::identifier($node, $symbolIdentifierGroup)) ? Symbol::identifier($node, $symbolIdentifierGroup) : Symbol::concat($namespaceSymbol, Symbol::identifier($node, $symbolIdentifierGroup));

        if (!isset(op\metaContext('code')->data['symbols'][$symbol]['aliasOf']) || !op\metaContext('code')->data['symbols'][$symbol]['aliasOf'])
          $namespaceSymbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $namespaceSymbol), '.');

        $symbol = Symbol::isAbsolute(Symbol::identifier($node, $symbolIdentifierGroup)) ? Symbol::identifier($node, $symbolIdentifierGroup) : Symbol::concat($namespaceSymbol, Symbol::identifier($node, $symbolIdentifierGroup));

        $symbol = ltrim($symbol, '.');

        assert(is_string($symbol) && $symbol != '');

        // @todo: Rewrite
        if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
          op\metaContext('code')->data['symbols'][$symbol] = [
            'id' => $symbol,
            'phpId' => ltrim((isset(op\metaContext('code')->data['symbols'][$namespaceSymbol]) ? op\metaContext('code')->data['symbols'][$namespaceSymbol]['phpId'] : $namespaceSymbol) . '\\' . Symbol::name($node), '\\'),
            'aliasOf' => '',
            'declarationNodes' => [],
          ];

        #Symbol::autoload(op\metaContext('code')->symbols[$symbol]['phpId']);

      }

      $symbols[] = $symbol;

      }

    }

    if (NodeConcept::isValueLiteral($node))
      foreach (inference\Value::get($node) as $value)
        $symbols[] = Symbol::identifier($value['value'], $symbolIdentifierGroup);

    return $symbols;

  }

  static function lookupSingle ($symbol, $scope = '', $phpId = '', $inAnalysisScope = true) {

    assert(is_string($scope));
    assert(is_string($phpId));
    assert(is_bool($inAnalysisScope));

    if (!$symbol)
      return '';

    if (Symbol::isAbsolute($symbol))
      return SymbolLink::lookupSingle(substr($symbol, 1), '', $phpId, $inAnalysisScope);

    if (substr($symbol, 0, 2) == 't_')
      return $symbol;

    if ($scope == 'n_')
      $scope = '';

    $symbolIdentifierGroup = Symbol::symbolIdentifierGroup($symbol);

    #if (false)
    if (!$scope) {

      if (isset(op\metaContext('code')->data['symbols'][$symbol]['aliasOf']) && op\metaContext('code')->data['symbols'][$symbol]['aliasOf']) {

        $importedSymbol = Symbol::concat(
          op\metaContext('code')->data['symbols'][$symbol]['aliasOf'],
          substr($symbol, strpos($symbol . '.', '.') + 1)
        );

        $importedPhpId = op\metaContext('code')->data['symbols'][$symbol]['phpId'] . '\\' .
          substr($phpId, strpos($phpId . '\\', '\\') + 1);

        $linkedSymbol = SymbolLink::lookupSingle($importedSymbol, '', $importedPhpId, $inAnalysisScope);
        if ($linkedSymbol)
          return $linkedSymbol;

      }

      #var_dump($node . ' -> ' . op\metaContext('code')->symbols[$node]['phpId'] . ' / ' . $symbolIdentifierGroup);
      #if ($inAnalysisScope)
      #if (in_array($symbolIdentifierGroup, ['class']))
      #  Symbol::autoload(op\metaContext('code')->symbols[$node]['phpId']);
      #var_dump($symbol);
      #var_dump((isset(op\metaContext('code')->symbols[$symbol]) && count(op\metaContext('code')->symbols[$symbol]['definitionNodes']) > 0));
      #var_dump(array_keys(op\metaContext('code')->symbols));
      if (substr($symbol, 0, 2) == 't_')
        return $symbol;
      if ($inAnalysisScope)
      if ($phpId)
        Symbol::autoload($phpId);
      assert(is_string($symbol) && $symbol != '');
      if ($inAnalysisScope)
      if (isset(op\metaContext('code')->data['symbols'][$symbol]['phpId']))
        Symbol::autoload(op\metaContext('code')->data['symbols'][$symbol]['phpId']);
      if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']) && count(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']) > 0)
        return $symbol;
      $symbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $symbol), '.');
      if ($inAnalysisScope)
      if (isset(op\metaContext('code')->data['symbols'][$symbol]))
        Symbol::autoload(op\metaContext('code')->data['symbols'][$symbol]['phpId']);
      if (isset(op\metaContext('code')->data['symbols'][$symbol]) && count(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']) > 0)
        return $symbol;
      return '';
    }

    /**
     * Functions and constants fallback to global only.
     * As documented in http://ie2.php.net/manual/en/language.namespaces.fallback.php#example-258
     */
    if (in_array(Symbol::symbolIdentifierGroup($symbol), ['constant', 'function']) && !in_array(Symbol::symbolIdentifierGroup($scope), ['class']))
      #var_dump($symbol, $scope);
      foreach ([Symbol::concat($scope, $symbol), $symbol] as $potentialSymbol) {
        $linkedSymbol = SymbolLink::lookupSingle($potentialSymbol, '', '', $inAnalysisScope);
        if ($linkedSymbol)
          return $linkedSymbol;
        }

    if (in_array(Symbol::symbolIdentifierGroup($symbol), ['constant', 'function', 'variable'])) {

      #$containerSymbol = Symbol::parent($symbol);
      #$memberSymbol = Symbol::unqualified($symbol);

      $containerSymbol = $scope;
      $memberSymbol = $symbol;

      if (in_array(Symbol::symbolIdentifierGroup($containerSymbol), ['class'])) {
        $linkedSymbol = SymbolLink::lookupSingle(Symbol::concat($containerSymbol, $memberSymbol), '', '', $inAnalysisScope);
        if ($linkedSymbol)
          return $linkedSymbol;
        }

      if (isset(op\metaContext('code')->data['symbols'][$containerSymbol]))
        foreach (op\metaContext('code')->data['symbols'][$containerSymbol]['declarationNodes'] as $containerNode) {

          foreach ($containerNode->getAttribute('traits', []) as $traitSymbol) {

            if (isset(op\metaContext('code')->data['symbols'][$traitSymbol]))
              Symbol::autoload(op\metaContext('code')->data['symbols'][$traitSymbol]['phpId']);

            $linkedSymbol = SymbolLink::lookupSingle(Symbol::concat($traitSymbol, $memberSymbol), '', '', $inAnalysisScope);
            if ($linkedSymbol)
              return $linkedSymbol;
            }

          if (isset($containerNode->extends))
            foreach (is_array($containerNode->extends) ? $containerNode->extends : [$containerNode->extends] as $extendsNode) {

              $simbolx = Symbol::fullyQualifiedIdentifier($extendsNode, 'class', $extendsNode->getAttribute('scope', ''));

              assert(is_string($simbolx) && $simbolx != '');

              // @todo: Rewrite
              if (!isset(op\metaContext('code')->data['symbols'][$simbolx]))
                op\metaContext('code')->data['symbols'][$simbolx] = [
                  'id' => $simbolx,
                  'phpId' => ltrim((!Symbol::isAbsolute(Symbol::identifier($extendsNode, 'class')) ? (isset(op\metaContext('code')->data['symbols'][$extendsNode->getAttribute('scope', '')]['phpId']) ? op\metaContext('code')->data['symbols'][$extendsNode->getAttribute('scope', '')]['phpId'] : $extendsNode->getAttribute('scope', '')) : '') . '\\' . $extendsNode->toString(), '\\'),
                  'aliasOf' => '',
                  'declarationNodes' => [],
                ];

              if ($inAnalysisScope)
              if (isset(op\metaContext('code')->data['symbols'][$simbolx]))
                Symbol::autoload(op\metaContext('code')->data['symbols'][$simbolx]['phpId']);

              $extendsSymbol = SymbolLink::lookupSingle(Symbol::identifier($extendsNode, 'class'), $extendsNode->getAttribute('scope', ''), $extendsNode->toString(), $inAnalysisScope);
              if ($extendsSymbol) {
                $linkedSymbol = SymbolLink::lookupSingle($memberSymbol, $extendsSymbol, '', $inAnalysisScope);
                if ($linkedSymbol)
                  return $linkedSymbol;
                }

              if (false)
              foreach ($this->lookup($extendsNode, 'class', Scope::parent($containerNode->getAttribute('scope', '')), '', $inAnalysisScope) as $extendsSymbol) {
                $linkedSymbol = SymbolLink::lookupSingle(Symbol::concat($extendsSymbol, $memberSymbol), '', '', $inAnalysisScope);
                if ($linkedSymbol)
                  return $linkedSymbol;
                }
              }
            }
        }


    if (in_array(Symbol::symbolIdentifierGroup($symbol), ['class', 'namespace'])) {

      $namespaceScope = Scope::namespaceScope($scope);

      if ($inAnalysisScope)
      if ($phpId)
      if (isset(op\metaContext('code')->data['symbols'][$namespaceScope]['phpId']))
        Symbol::autoload(($namespaceScope ? trim(op\metaContext('code')->data['symbols'][$namespaceScope]['phpId'], '\\') . '\\' : '') . trim($phpId, '\\'));

      $linkedSymbol = SymbolLink::lookupSingle(Symbol::concat(Scope::namespaceScope($scope), $symbol), '', '', $inAnalysisScope);
      if ($linkedSymbol)
        return $linkedSymbol;

      /**
       * Alias to import is a symbol in the current scope that represents an alias
       * to a symbol in another scope that needs to be imported.
       *
       * See also Symbol::inferImport()
       *
       * For example:
       *
       *   namespace A\B {
       *     use \C\D as E;
       *     $x = new E(); // $aliasSymbol = 'n_a.n_b.c_e'; $importedSymbol = 'n_c.c_d';
       *     $x = new E\F(); // $aliasSymbol = 'n_a.n_b.n_e'; $importedSymbol = 'n_c.n_d.c_f';
       *   }
       */
      $aliasSymbol = Symbol::concat($namespaceScope, substr($symbol . '.', 0, strpos($symbol . '.', '.')));

      if (isset(op\metaContext('code')->data['symbols'][$aliasSymbol])) {

        $importedSymbol = Symbol::concat(
          op\metaContext('code')->data['symbols'][$aliasSymbol]['aliasOf'],
          substr($symbol, strpos($symbol . '.', '.') + 1)
        );

        $importedPhpId = op\metaContext('code')->data['symbols'][$aliasSymbol]['phpId'] . '\\' .
          substr($phpId, strpos($phpId . '\\', '\\') + 1);

        $linkedSymbol = SymbolLink::lookupSingle($importedSymbol, '', $importedPhpId, $inAnalysisScope);
        if ($linkedSymbol)
          return $linkedSymbol;

      }
    }

    return '';

  }

  static function lookupSinglePhpId ($phpId, $scope = '', $inAnalysisScope = true) {
    return SymbolLink::lookupSingle(Symbol::identifier($phpId, 'auto'), $scope, $phpId, $inAnalysisScope);
  }

  /**
   * Get node analysis-time known symbols.
   *
   * @param object|string $node Node whose symbols to get or a php keyword.
   * @return string[]
   */
  static function get ($node) {

    if (is_string($node)) {
      $symbol = inference\Symbol::identifier($node, 'auto');
      return $symbol ? [$symbol] : [];
    }

    if (isset(op\metaContext(IIData::class)['nodeSymbols:' . spl_object_hash($node)]))
      return op\metaContext(IIData::class)['nodeSymbols:' . spl_object_hash($node)];

    if (NodeConcept::isVariableNode($node))
      return $node->getAttribute('symbols', []);

    if (NodeConcept::isInvocationNode($node))
      op\metaContext(ExtensionInterface::class)['phlint.analysis.inference.symbolLink']($node, '');

    $symbols = [];

    if ($node instanceof Node)
      foreach ($node->getAttribute('symbols', []) as $symbol)
        $symbols[] = strpos($symbol, 'n_.') === 0 ? substr($symbol, 3) : $symbol;

    if ($node instanceof Node\Arg)
      foreach (self::get($node->value) as $symbol)
        $symbols[] = $symbol;

    if ($node instanceof Node\Expr\ConstFetch)
      foreach (self::get($node->name) as $symbol)
        $symbols[] = $symbol;

    op\metaContext(IIData::class)['nodeSymbols:' . spl_object_hash($node)] = array_unique($symbols);

    return array_unique($symbols);

  }

  /**
   * Get node analysis-time known symbols without extra mangle information.
   *
   * @param object|string $node Node whose symbols to get or a literal symbol.
   * @return string[]
   */
  static function getUnmangled ($node) {
    return array_map(function ($symbol) {
      return preg_replace('/\{[^\}]*\}/', '', $symbol);
    }, \phlint\inference\SymbolLink::get($node));
  }

}
