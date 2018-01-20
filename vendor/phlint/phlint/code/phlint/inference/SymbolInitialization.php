<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\inference\Scope;
use \phlint\Internal;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \PhpParser\Node;

class SymbolInitialization {

  function getIdentifier () {
    return 'symbolInitialization';
  }

  function getDependencies () {
    return [
      'symbol',
      'symbolLink',
      'executionBarrier',
    ];
  }

  function getPass () {
    return 40;
  }

  protected $scopesInitializations = [];

  function resetState () {
    $this->scopesInitializations = [];
  }

  function beforeTraverse ($nodes) {
      $this->resetState();
  }

  function enterNode ($node) {

    if ($node instanceof Node\Stmt\Unset_) {
      foreach ($node->vars as $variable) {
        if ($variable instanceof Node\Expr\Variable) {

            foreach (inference\SymbolLink::get($variable) as $variableSymbol) {

              $variableScope = Scope::symbolScope($variableSymbol);
              $unqualifiedVariableSymbol = Symbol::unqualified($variableSymbol);

              foreach (Scope::visibleScopes($variableScope) as $visibleScope)
                unset($this->scopesInitializations[$visibleScope][$unqualifiedVariableSymbol]);
            }

        }
      }
    }

  }

  function visitNode ($node) {

    self::inferSymbolProbe($node);

    $this->inferConditionalGuarantees($node);

    $this->inferSymbolsInitializations($node);

    $this->inferSymbolsInitialized($node);

  }

  function leaveNode ($node) {

    if (NodeConcept::isContextNode($node))
      foreach (inference\SymbolLink::get($node) as $symbol)
        foreach ($this->scopesInitializations as $scopeInitialization => $_)
          if (strpos($scopeInitialization . '.', $symbol . '.') === 0)
            unset($this->scopesInitializations[$scopeInitialization]);

    $this->inferConditionalGuaranteesBarriers($node);

    if ($node instanceof Node\Stmt\If_) {

      foreach ([$node->getAttribute('scope', '')] as $scope) {

        $subScopes = [];

        foreach (inference\SymbolLink::get($node) as $subScope)
          $subScopes[] = $subScope;

        foreach ($node->elseifs as $elseif)
          foreach (inference\SymbolLink::get($elseif) as $subScope)
            $subScopes[] = $subScope;

        if ($node->else)
          foreach (inference\SymbolLink::get($node->else) as $subScope)
            $subScopes[] = $subScope;
        else
          $subScopes[] = 's_empty';

        $unqualifiedSymbols = [];
        $unqualifiedInitializations = [];

        foreach ($subScopes as $subScope)
          if (!isset($this->scopesInitializations[$subScope]))
            $this->scopesInitializations[$subScope] = [];

        foreach ($subScopes as $subScope)
          $unqualifiedSymbols = array_merge($unqualifiedSymbols, array_keys($this->scopesInitializations[$subScope]));

        foreach ($subScopes as $subScope) {
          $unqualifiedSymbols = array_intersect($unqualifiedSymbols, array_keys($this->scopesInitializations[$subScope]));
          foreach ($this->scopesInitializations[$subScope] as $unqualifiedSymbol => $initializationNodes) {
            if (!isset($unqualifiedInitializations[$unqualifiedSymbol]))
              $unqualifiedInitializations[$unqualifiedSymbol] = [];
            foreach ($initializationNodes as $initializationNode)
              $unqualifiedInitializations[$unqualifiedSymbol][] = $initializationNode;
          }
        }

        foreach (array_unique($unqualifiedSymbols) as $unqualifiedSymbol)

          if (!isset($this->scopesInitializations[$scope][$unqualifiedSymbol]))
            $this->scopesInitializations[$scope][$unqualifiedSymbol] = $unqualifiedInitializations[$unqualifiedSymbol];

      }

    }

    if ($node instanceof Node\Stmt\Namespace_) {
      foreach (inference\SymbolLink::get($node) as $namespaceScope) {
        foreach ([$node->getAttribute('scope', '')] as $parentScope) {
          if (!isset($this->scopesInitializations[$namespaceScope]))
            continue;
          if (!isset($this->scopesInitializations[$parentScope]))
            $this->scopesInitializations[$parentScope] = [];
          foreach (array_keys($this->scopesInitializations[$namespaceScope]) as $unqualifiedSymbol)
            if (!isset($this->scopesInitializations[$parentScope][$unqualifiedSymbol]))
              $this->scopesInitializations[$parentScope][$unqualifiedSymbol] =
                $this->scopesInitializations[$namespaceScope][$unqualifiedSymbol];
        }
      }
    }

    if ($node instanceof Node\Stmt\Switch_)
      foreach ($node->cases as $case_)
        if ($case_->cond === null)
          foreach (inference\SymbolLink::get($node) as $scope)
            if (isset($this->scopesInitializations[$scope]))
              foreach (array_keys($this->scopesInitializations[$scope]) as $unqualifiedSymbol) {
                if (!isset($this->scopesInitializations[Scope::parent($scope)]))
                  $this->scopesInitializations[Scope::parent($scope)][$unqualifiedSymbol] = [];
                foreach ($this->scopesInitializations[$scope][$unqualifiedSymbol] as $initializationNode)
                  $this->scopesInitializations[Scope::parent($scope)][$unqualifiedSymbol][] = $initializationNode;
              }

  }

  function inferConditionalGuarantees ($node) {

    foreach (inference\ConditionalGuarantee::evaluate($node) as $guarantee) {

      if (in_array('t_undefined', $guarantee['excludesTypes']))
        foreach (inference\SymbolLink::get($guarantee['scope']) as $scope)
          foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

            $unqualifiedSymbol = Symbol::unqualified($symbol);

            if (!isset($this->scopesInitializations[$scope][$unqualifiedSymbol]))
              $this->scopesInitializations[$scope][$unqualifiedSymbol] = [$guarantee['node']];

          }

    }

  }

  function inferConditionalGuaranteesBarriers ($node) {

    if (NodeConcept::isConditionalExecutionNode($node) && $node->getAttribute('hasExecutionBarrier', false))
      foreach (inference\ConditionalGuarantee::evaluateCondition(new Node\Expr\BooleanNot($node->cond)) as $guarantee) {

        if (in_array('t_undefined', $guarantee['excludesTypes']))
          foreach (inference\SymbolLink::get($node) as $scope)
            foreach (inference\SymbolLink::get($guarantee['node']) as $symbol) {

              $guaranteeScope = $node->getAttribute('scope', '');
              $unqualifiedSymbol = Symbol::unqualified($symbol);

              if (!isset($this->scopesInitializations[$guaranteeScope][$unqualifiedSymbol]))
                $this->scopesInitializations[$guaranteeScope][$unqualifiedSymbol] = [$guarantee['node']];

              foreach ($this->scopesInitializations[$guaranteeScope][$unqualifiedSymbol] as $initializationNode)
                if ($initializationNode === $guarantee['node'])
                  $guarantee['node']->setAttribute(
                    'isInitialization',
                    array_unique(array_merge($guarantee['node']->getAttribute('isInitialization', []), [$symbol]))
                  );

            }

        }

  }

  function inferSymbolsInitializations ($node) {

    if (($node instanceof Node\Expr\Assign) || ($node instanceof Node\Expr\AssignRef))
      $this->registerInitializations($node->var);

    if ($node instanceof Node\Expr\FuncCall) {

      foreach (inference\SymbolLink::get($node) as $symbol) {

        $definitionNodes = [];

        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode)
            $definitionNodes[] = $definitionNode;

        foreach ($node->args as $index => $argument) {

          $isInitialization = count($definitionNodes) > 0;

          foreach ($definitionNodes as $definitionNode) {
            if (!isset($definitionNode->params[$index])) {
              $isInitialization = false;
              break;
            }

            $hasOut = false;

            foreach (inference\Attribute::get($definitionNode->params[$index]) as $attribute)
              if ($attribute instanceof Node\Expr\New_ &&
                  count($attribute->args) > 0 &&
                  inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'out']]) {
                $hasOut = true;
                break;
              }

            if (!$hasOut) {
              $isInitialization = false;
              break;
            }
          }

          if ($isInitialization) {

            foreach (inference\SymbolLink::get($argument) as $argumentSymbol) {

              $argumentScope = Scope::symbolScope($argumentSymbol);
              $argumentUnqualifiedSymbol = Symbol::unqualified($argumentSymbol);

              if (!isset($this->scopesInitializations[$argumentScope][$argumentUnqualifiedSymbol]))
                $this->scopesInitializations[$argumentScope][$argumentUnqualifiedSymbol] = [$argument->value];

              foreach (Scope::visibleScopes($argumentScope) as $visibleScope)
                $this->scopesInitializations[$visibleScope][$argumentUnqualifiedSymbol] = [$argument->value];

              foreach ($this->scopesInitializations[$argumentScope][$argumentUnqualifiedSymbol] as $initializationNode)
                if ($initializationNode === $argument->value)
                  $argument->value->setAttribute(
                    'isInitialization',
                    array_unique(array_merge($argument->value->getAttribute('isInitialization', []), [$argumentSymbol]))
                  );

            }

          }

        }


      }

    }

    if ($node instanceof Node\Param)
      $this->registerInitializations($node, true);

    if ($node instanceof Node\Stmt\Catch_)
      $this->registerInitializations($node, true);

    if (($node instanceof Node\Expr\ClosureUse) && !$node->byRef)
      $this->registerInitializations($node, true);

    if ($node instanceof Node\Stmt\Foreach_) {
      $this->registerInitializations($node->valueVar, true);
      if ($node->keyVar)
        $this->registerInitializations($node->keyVar, true);
    }

    if ($node instanceof Node\Stmt\StaticVar)
      $this->registerInitializations($node, true);

  }

  function registerInitializations ($node, $isDefiniteInitialization = false) {

    if ($node instanceof Node\Expr\List_) {
      foreach ($node->items as $variableNode)
        if ($variableNode && $variableNode->value)
          $this->registerInitializations($variableNode->value);
      return;
    }

    if ($node instanceof Node\Expr\ArrayDimFetch) {
      $this->registerInitializations($node->var);
      return;
    }

    $isInitialization = $node->getAttribute('isInitialization', []);

    foreach (inference\SymbolLink::get($node) as $symbol) {

      $scope = Scope::symbolScope($symbol);
      $unqualifiedSymbol = Symbol::unqualified($symbol);

      foreach (Scope::visibleScopes($scope) as $visibleScope) {
        if (isset($this->scopesInitializations[$scope][$unqualifiedSymbol]))
          break;
        if (isset($this->scopesInitializations[$visibleScope][$unqualifiedSymbol]))
          $this->scopesInitializations[$scope][$unqualifiedSymbol] =
            $this->scopesInitializations[$visibleScope][$unqualifiedSymbol];
      }

      if (!isset($this->scopesInitializations[$scope][$unqualifiedSymbol]))
        $this->scopesInitializations[$scope][$unqualifiedSymbol] = [$node];

      foreach ($this->scopesInitializations[$scope][$unqualifiedSymbol] as $initializationNode)
        if ($initializationNode === $node)
          $isInitialization[] = $symbol;

    }

    $node->setAttribute('isInitialization', array_unique($isInitialization));

  }

  function inferSymbolsInitialized ($node) {

    if (NodeConcept::isVariableNode($node)) {

      if (Symbol::identifier($node) == 'v_this')
        $node->setAttribute('isInitialized', inference\SymbolLink::get($node));

      $superglobals = array_map(function ($name) { return '.v_' . $name; }, phpLanguage\Fixture::$superglobals);

      if (in_array(Symbol::identifier($node), $superglobals))
        $node->setAttribute('isInitialized', inference\SymbolLink::get($node));

      $this->registerInitialized($node, $node instanceof Node\Expr\ClosureUse);

    }

  }

  function registerInitialized ($node, $lookupParentContext = false) {

    $isInitialized = $node->getAttribute('isInitialized', []);

    foreach (inference\SymbolLink::get($node) as $symbol) {

      $scope = Scope::symbolScope($symbol);
      $unqualifiedSymbol = Symbol::unqualified($symbol);

      if ($lookupParentContext)
        $scope = Scope::parent(Scope::contextScope($scope));

      foreach (Scope::visibleScopes($scope) as $visibleScope) {
        if (isset($this->scopesInitializations[$visibleScope][$unqualifiedSymbol]) && $this->scopesInitializations[$visibleScope][$unqualifiedSymbol] !== $node) {
          $isInitialized[] = $symbol;
          if ($lookupParentContext) {
            if (!isset($this->scopesInitializations[Scope::symbolScope($symbol)][$unqualifiedSymbol]))
              $this->scopesInitializations[Scope::symbolScope($symbol)][$unqualifiedSymbol] = [$node];
            // @todo: isInitialization also.
          }
          break;
        }
      }

    }

    $node->setAttribute('isInitialized', array_unique($isInitialized));

  }

  static function inferSymbolProbe ($node) {

    if ($node instanceof Node\Expr\Isset_) {
      foreach ($node->vars as $variable) {

        if ($variable instanceof Node\Expr\Variable)
          $variable->setAttribute('isProbe', true);

        if (($variable instanceof Node\Expr\ArrayDimFetch) && ($variable->var instanceof Node\Expr\Variable))
          $variable->var->setAttribute('isProbe', true);

      }
    }

    if ($node instanceof Node\Stmt\Unset_) {
      foreach ($node->vars as $variable) {
        if ($variable instanceof Node\Expr\Variable)
          $variable->setAttribute('isProbe', true);
      }
    }

    if ($node instanceof Node\Expr\Empty_) {

      if ($node->expr instanceof Node\Expr\Variable)
        $node->expr->setAttribute('isProbe', true);

      if (($node->expr instanceof Node\Expr\ArrayDimFetch) && ($node->expr->var instanceof Node\Expr\Variable))
        $node->expr->var->setAttribute('isProbe', true);

    }

  }

}
