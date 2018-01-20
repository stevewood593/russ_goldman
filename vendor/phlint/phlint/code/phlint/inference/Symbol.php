<?php

namespace phlint\inference;

use \ArrayObject;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\inference\Scope;
use \phlint\inference\SymbolLink;
use \phlint\Internal;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \PhpParser\Node;

class Symbol {

  function getIdentifier () {
    return 'symbol';
  }

  function getDependencies () {
    return [
      'include',
      'scope',
      'symbolLink',
    ];
  }

  function getPass () {
    return 20;
  }

  /** @see /documentation/glossary/intermediatelyInferredData.md */
  protected $iiData = null;

  function setIIData ($iiData) {
    $this->iiData = $iiData;
  }

  protected $scopeStack = [];

  protected $symbolVisibilityMap = [];

  function resetState () {
    $this->scopeStack = [new ArrayObject([
      'id' => '',
      'namespaceID' => '',
      'context' => '',
      'phpId' => '',
      'node' => new \phlint\SourceNode([]),
      'idCounter' => 0,
      'isConditionalExecutionNode' => false,
    ])];

    $this->symbolVisibilityMap = [];
  }

  function beforeTraverse ($nodes) {
      $this->resetState();
      if (!isset(op\metaContext('code')->data['symbols']))
        op\metaContext('code')->data['symbols'] = [];
  }

  function enterNode ($node) {

    // @todo: Remove.
    if (($node instanceof Node\Stmt\If_) && !$node->else)
      $node->else = new Node\Stmt\Else_();

    $this->inferContextDependentScopes($node);
    $this->leaveFinishedScopes($node);
    $this->inferScope($node);
    $this->enterScope($node);
  }

  function visitNode ($node) {

    if ($node instanceof Node\Stmt\If_)
      $this->enterScope($node, true);

    $this->inferSymbol($node);
    $this->inferImport($node);

    assert(!isset(op\metaContext('code')->data['symbols']['']));

  }

  function leaveNode ($node) {
    $this->leaveScope($node);
  }

  function cleanNode ($node) {
    $node->setAttribute('scope', '');
    $node->setAttribute('scopeDeclare', '');
    $node->setAttribute('context', '');
    $node->setAttribute('symbols', []);
  }

  function inferContextDependentScopes ($node) {

    /**
     * "&&" condition forms a new scope on the right side as its execution is
     * influenced by the left side.
     *
     * Consider the expression: `$bar = isset($foo) && $foo;`
     *
     * This expression will never produce a "PHP Notice: Undefined variable"
     * and thus a conditional guarantee that `$foo` is defined can be applied
     * to the right side.
     *
     * Also the expression: `isset($foo) && $foo();`
     * Is functionally equal to `if (isset($foo) { $foo(); }`
     *
     * Considering these behaviors it made most sense to make the
     * right side form a new scope.
     */
    if ($node instanceof Node\Expr\BinaryOp\BooleanAnd)
      $node->right->setAttribute('isScope', true);

  }

  function leaveFinishedScopes ($node) {

    /**
     * Node\Stmt\Else_ and Node\Stmt\ElseIf_ form as special case because in AST
     * they are inside Node\Stmt\If_ but when looking at their scopes they are
     * independent (after if scope). For that reason the original (Node\Stmt\If_)
     * scope needs to be closed early.
     */
    if (($node instanceof Node\Stmt\Else_) || ($node instanceof Node\Stmt\ElseIf_))
      if (count($this->scopeStack) > 0 && (end($this->scopeStack)['node'] instanceof Node\Stmt\If_))
        array_pop($this->scopeStack);

  }

  function inferScope ($node) {
    if (!$node->getAttribute('scope', ''))
      $node->setAttribute('scope', end($this->scopeStack)['id']);
    if (!$node->getAttribute('context', ''))
      $node->setAttribute('context', end($this->scopeStack)['context']);
  }

  function enterScope ($node, $special = false) {

    if (true
        && !NodeConcept::isDeclarationNode($node)
        && !NodeConcept::isDefinitionNode($node)
        && !NodeConcept::isExecutionBranchNode($node)
        && !NodeConcept::isScopeNode($node)
        && !NodeConcept::isVariableNode($node)
        && !$node->getAttribute('isScope', false)
    )
      return;

    if (!$special)
    if ($node instanceof Node\Stmt\If_)
      return;

    $scopeId = '';
    $namespaceID = '';
    $scopePhpId = '';

    if (!$scopeId && $node->getAttribute('scopeDeclare', '')) {
      $scopeId = $node->getAttribute('scopeDeclare', '');
      if (isset(op\metaContext(IIData::class)['scopeNamespace:' . preg_replace('/\{[^\}]*\}/', '', $scopeId)]))
        $namespaceID = op\metaContext(IIData::class)['scopeNamespace:' . preg_replace('/\{[^\}]*\}/', '', $scopeId)];
    }

    if (!$scopeId && count($node->getAttribute('symbols', [])) > 0) {
      assert(count($node->getAttribute('symbols', [])) == 1);
      $scopeId = $node->getAttribute('symbols', [])[0];
      if (isset(op\metaContext('code')->data['symbols'][$scopeId]))
        $scopePhpId = op\metaContext('code')->data['symbols'][$scopeId]['phpId'];
    }

    if (!$scopeId && !NodeConcept::isInvocationNode($node) && !($node instanceof Node\Stmt\Catch_) && Symbol::identifier($node)) {
      $scopeId = Symbol::fullyQualifiedIdentifier($node, '', end($this->scopeStack)['id']);
      if ($node instanceof Node\Stmt\Namespace_) {
        end($this->scopeStack)['idCounter'] += 1;
        $scopeId = Symbol::concat($scopeId, 's_' . end($this->scopeStack)['idCounter']);
      }
      $scopePhpId = ltrim(end($this->scopeStack)['phpId']
        . (end($this->scopeStack)['id'] && Symbol::symbolIdentifierGroup(end($this->scopeStack)['id']) == 'class' ? '::' : '\\')
        . Symbol::name($node));
    }

    if (!$scopeId) {
      end($this->scopeStack)['idCounter'] += 1;
      $scopeId = Symbol::concat(end($this->scopeStack)['id'], 's_' . end($this->scopeStack)['idCounter']);
    }

    if (!$namespaceID)
      $namespaceID = $node instanceof Node\Stmt\Namespace_ ? $scopeId : end($this->scopeStack)['namespaceID'];

    if ($node instanceof Node\Stmt\Function_)
      $scopeId = Symbol::concat($namespaceID, Symbol::unqualified($scopeId));

    assert($scopeId != '');

    $this->scopeStack[] = new ArrayObject([
      'id' => $scopeId,
      'namespaceID' => $namespaceID,
      'context' => rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', Scope::contextScope($scopeId)), '.'),
      'phpId' => $scopePhpId,
      'node' => $node,
      'idCounter' => 0,
      'isConditionalExecutionNode' => NodeConcept::isConditionalExecutionNode($node) || end($this->scopeStack)['isConditionalExecutionNode'],
    ]);

    op\metaContext(IIData::class)['scopeNamespace:' . $scopeId] = $namespaceID;

  }

  function leaveScope ($node) {
    if (count($this->scopeStack) > 0 && end($this->scopeStack)['node'] === $node)
      array_pop($this->scopeStack);
  }

  function inferSymbol ($node) {

    if (true
        && !NodeConcept::isDeclarationNode($node)
        && !NodeConcept::isDefinitionNode($node)
        && !NodeConcept::isExecutionBranchNode($node)
        && !NodeConcept::isVariableNode($node)
        && !$node->getAttribute('isScope', false)
    )
      return;

    $node->setAttribute('isConditionalExecutionNode', end($this->scopeStack)['isConditionalExecutionNode']);

    $symbol = end($this->scopeStack)['id'];

    if ($node instanceof Node\Stmt\Function_)
      $symbol = Symbol::concat(end($this->scopeStack)['namespaceID'], Symbol::unqualified($symbol));

    if ($node instanceof Node\Stmt\Catch_)
      $symbol = Symbol::concat($symbol, Symbol::identifier($node));

    #if (NodeConcept::isVariableNode($node) && strpos($symbol, '.') !== false) {
    #  $symbol = preg_replace('/(?is)((?<=\.)|\A)v[a-z0-9]*_[^\.]*(\.|\z)/', '', $symbol);
    #  $symbol = Symbol::concat($symbol, Symbol::identifier($node));
    #}

    // VariableVariable quickfix.
    // @todo: Remove
    $symbol = preg_replace('/(?is)((?<=\.)|\A)v[a-z0-9]*_[^\.]*(\.)/', '', $symbol);

    if (!$symbol)
      return;

    $node->setAttribute('scopeDeclare', $symbol);

    if (NodeConcept::isInvocationNode($node))
      return;

    if (NodeConcept::isInterfaceNode($node))
      $symbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $symbol), '.');

    #$node->setAttribute('symbols', [Symbol::fullyQualifiedIdentifier($node)]);
    $node->setAttribute('symbols', [$symbol]);

    #return;

    if (NodeConcept::isNamedNode($node))
    $symbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $symbol), '.');

    if (!$symbol)
      return;

    assert(is_string($symbol));
    assert($symbol != '');
    assert(strpos($symbol, '.') !== 0);

    if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
      op\metaContext('code')->data['symbols'][$symbol] = [
        'id' => $symbol,
        //'context' => Scope::contextScope(Scope::parent(end($this->scopeStack)['context'])),
        'phpId' => end($this->scopeStack)['phpId'],
        'aliasOf' => '',
        'declarationNodes' => [],
        'linkedNodes' => [],
      ];

    $this->iiData['phpID:' . $symbol] = end($this->scopeStack)['phpId'];

    $isNodeAttached = false;
    foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $existingDeclarationNode)
      if ($existingDeclarationNode === $node)
        $isNodeAttached = true;
    if (!$isNodeAttached)
      op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'][] = $node;

    #op\metaContext('code')->data['symbols'][$symbol]['phpId'] = end($this->scopeStack)['phpId'];

  }

  /**
   * Register import aliases in the current scope and sets the to alias another scope.
   *
   * For example:
   *
   *   namespace A\B {
   *     class C {}
   *   }
   *
   *   namespace D\E {
   *
   *     // Registers 'n_d.n_e.n_a.n_b' as aliasOf 'n_a.n_b'.
   *     // Registers 'n_d.n_e.n_a.c_b' as aliasOf 'n_a.c_b'.
   *     // Either might be valid.
   *     use \A\B;
   *
   *     // Registers 'n_d.n_e.n_a.n_b.n_c' as aliasOf 'n_a.n_b.n_c'.
   *     // Registers 'n_d.n_e.n_a.n_b.c_c' as aliasOf 'n_a.n_b.c_c'.
   *     // Either might be valid.
   *     use \A\B\C;
   *
   *   }
   */
  function inferImport ($node) {

    if ($node instanceof Node\Stmt\Use_) {

      foreach ($node->uses as $useNode) {

        $importTypes = [];

        switch ($useNode->type ? $useNode->type : $node->type) {
          case Node\Stmt\Use_::TYPE_NORMAL:  $importTypes = ['namespace', 'class']; break;
          case Node\Stmt\Use_::TYPE_FUNCTION:  $importTypes = ['function']; break;
          case Node\Stmt\Use_::TYPE_CONSTANT:  $importTypes = ['constant']; break;
          default: assert(false);
        }

        foreach ($importTypes as $importType) {
          #$aliasSymbol = Symbol::concat(
          #  Scope::namespaceScope($node->getAttribute('scope', '')),
          #  Symbol::identifier($useNode->alias, $importType)
          #);
          #if ($aliasSymbol != Symbol::fullyQualifiedIdentifier($useNode->alias, $importType, $node->getAttribute('scope'))) {
          #var_dump($node->getAttribute('scope', ''));
          #var_dump(Scope::namespaceScope($node->getAttribute('scope', '')));
          #var_dump(Symbol::identifier($useNode->alias, $importType));
          #var_dump($aliasSymbol);
          #var_dump(Symbol::fullyQualifiedIdentifier($useNode->alias, $importType, $node->getAttribute('scope')));
          #var_dump('------------------------------');
          #}
          $aliasSymbol = Symbol::fullyQualifiedIdentifier($useNode->alias, $importType, $node->getAttribute('scope'));
          assert(is_string($aliasSymbol) && $aliasSymbol != '');
          #$this->register($aliasSymbol);
          if (!isset(op\metaContext('code')->data['symbols'][$aliasSymbol]))
            op\metaContext('code')->data['symbols'][$aliasSymbol] = [
              'id' => $aliasSymbol,
              'phpId' => $useNode->name->toString(),
              'aliasOf' => Symbol::identifier($useNode->name, $importType),
              'declarationNodes' => [],
              'linkedNodes' => [],
            ];
          $this->iiData['phpID:' . $aliasSymbol] = $useNode->name->toString();
          #op\metaContext('code')->data['symbols'][$aliasSymbol]['aliasOf'] = Symbol::identifier($useNode->name, $importType);
          #op\metaContext('code')->data['symbols'][$aliasSymbol]['phpId'] = $useNode->name->toString();
        }

      }

    }

  }

  static function name ($node) {

    if (is_string($node))
      return $node;

    if ($node instanceof Node\Name)
      return $node->toString();

    if ($node instanceof Node\Stmt\Class_)
      return Symbol::name($node->name);

    if ($node instanceof Node\Stmt\ClassMethod)
      return Symbol::name($node->name);

    if ($node instanceof Node\Stmt\Function_)
      return Symbol::name($node->name);

    if ($node instanceof Node\Expr\FuncCall)
      return Symbol::name($node->name);

    if ($node instanceof Node\Expr\Variable)
      return '$' . Symbol::name($node->name);

    if ($node instanceof Node\Stmt\StaticVar)
      return '$' . Symbol::name($node->name);

    if ($node instanceof Node\Expr\ClosureUse)
      return '$' . Symbol::name($node->var);

    if ($node instanceof Node\Stmt\Interface_)
      return Symbol::name($node->name);

    if ($node instanceof Node\Stmt\Namespace_)
      return Symbol::name($node->name);

    return '';

  }

  static function fullyQualifiedIdentifier ($node, $symbolIdentifierGroup = '', $scope = '') {

    $identifier = Symbol::identifier($node, $symbolIdentifierGroup);

    $isRelative = function ($identifier) { return strpos($identifier, '.') !== 0; };

    if ($isRelative($identifier)) {
      if (!$scope && ($node instanceof Node))
        $scope = $node->getAttribute('scope', '');
      if ($scope == 'n_')
        $scope = '';
      $identifier = '.' . ($scope ? $scope . '.' : '') . $identifier;
    }

    return substr($identifier, 1);

  }

  /**
   * Generates a static symbol identifier from a Node or a node name.
   *
   * @param Node|string $node Node or a node name.
   * @param string $group If $node is a `Node\Name` it is not always possible
   *   to determinate the symbol identifier group solely based on it. That is
   *   why this argument can be used for hinting.
   *
   * @see /documentation/glossary/symbolIdentifierGroup.md
   */
  static function identifier ($node, $group = '') {

    if (is_string($node)) {
      if (strpos($node, '\\') !== false && in_array($group, ['class', 'constant', 'function', 'namespace'])) {
        $namespace = array_map(function ($part) {
          return Symbol::identifier($part, 'namespace');
        }, array_slice(explode('\\', trim($node, '\\')), 0, -1));
        $construct = array_map(function ($part) use ($group) {
          return Symbol::identifier($part, $group);
        }, array_slice(explode('\\', trim($node, '\\')), -1));
        return (strpos($node, '\\') === 0 ? '.' : '') . implode('.', array_merge($namespace, $construct));
      }
      if ($group == 'auto') {
        if (in_array(strtolower(str_replace('[]', '', $node)), phpLanguage\Fixture::$typeDeclarationNonClassKeywords))
          return Symbol::identifier($node, 'type');
        // @todo: Rewrite
        if (in_array(str_replace('[]', '', $node), ['autoFloat', 'autoString', 'autoInteger', 'stringInt', 'stringFloat', 'stringBool', 'autoBool']))
          return Symbol::identifier($node, 'type');
        return Symbol::identifier($node, 'class');
      }
      if ($group == 'class')
        return 'c_' . strtolower($node);
      if ($group == 'constant')
        return 'd_' . $node;
      if ($group == 'function')
        return 'f_' . strtolower($node);
      if ($group == 'namespace')
        return 'n_' . strtolower($node);
      if ($group == 'type')
        if (strtolower(str_replace('[]', '', $node)) == strtolower('autoFloat'))
          return 't_autoFloat';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('autoString'))
          return 't_autoString';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('autoInteger'))
          return 't_autoInteger';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('stringInt'))
          return 't_stringInt';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('stringFloat'))
          return 't_stringFloat';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('stringBool'))
          return 't_stringBool';
        else if (strtolower(str_replace('[]', '', $node)) == strtolower('autoBool'))
          return 't_autoBool';
        else
          return 't_' . strtolower($node);
      if ($group == 'variable') {
        if (in_array($node, phpLanguage\Fixture::$superglobals))
          return '.v_' . $node;
        return 'v_' . $node;
      }
      return '';
    }

    if ($node instanceof Node\Const_)
      return Symbol::identifier($node->name, 'constant');

    if ($node instanceof Node\Expr\Closure) {
      \phlint\NodeTraverser::traverse($node, [function ($node) {
        // @todo: Remove.
        if (($node instanceof Node\Stmt\If_) && !$node->else)
          $node->else = new Node\Stmt\Else_();
      }]);
      #var_dump(NodeConcept::sourcePrint($node));
      #var_dump(sha1(NodeConcept::sourcePrint($node)));
      return Symbol::identifier('anonymous_' . sha1(NodeConcept::sourcePrint($node)), 'function');
    }

    if ($node instanceof Node\Expr\ClosureUse)
      return Symbol::identifier($node->var, 'variable');

    if ($node instanceof Node\Expr\ConstFetch)
      return Symbol::identifier($node->name, 'constant');

    /**
     * Symbol invocations share the symbol identifier with the invoked symbol.
     * In expression `$x = $x;` both symbols have the same identifier.
     * Hence `function x () {}` has the same symbol identifier as `x();`.
     */
    if ($node instanceof Node\Expr\FuncCall)
      return Symbol::identifier($node->name, 'function');

    if ($node instanceof Node\Expr\New_)
      return Symbol::identifier($node->class, 'class');

    if ($node instanceof Node\Expr\StaticCall)
      return Symbol::identifier($node->class, 'class') . '.' . Symbol::identifier($node->name, 'function');

    if ($node instanceof Node\Expr\StaticPropertyFetch)
      return Symbol::identifier($node->class, 'class') . '.' . Symbol::identifier($node->name, 'variable');

    if ($node instanceof Node\Expr\Variable)
      return Symbol::identifier($node->name, 'variable');

    if ($node instanceof Node\Name) {
      if ($node instanceof Node\Name\FullyQualified)
        return '.' . Symbol::identifier(new Node\Name($node->parts), $group);
      $namespace = array_map(function ($part) {
        return Symbol::identifier($part, 'namespace');
      }, array_slice($node->parts, 0, -1));
      $construct = array_map(function ($part) use ($group) {
        return Symbol::identifier($part, $group);
      }, array_slice($node->parts, -1));
      return implode('.', array_merge($namespace, $construct));
    }

    if ($node instanceof Node\Param)
      return Symbol::identifier($node->name, 'variable');

    if ($node instanceof Node\Stmt\Catch_)
      return Symbol::identifier($node->var, 'variable');

    if ($node instanceof Node\Stmt\Class_)
      return Symbol::identifier($node->name, 'class');

    if ($node instanceof Node\Stmt\ClassMethod)
      return Symbol::identifier($node->name, 'function');

    if ($node instanceof Node\Stmt\Function_)
      return Symbol::identifier($node->name, 'function');

    if ($node instanceof Node\Stmt\Interface_)
      return Symbol::identifier($node->name, 'class');

    if ($node instanceof Node\Stmt\Namespace_)
      return '.' . Symbol::identifier($node->name ? $node->name : '', 'namespace');

    if ($node instanceof Node\Stmt\PropertyProperty)
      return Symbol::identifier($node->name, 'variable');

    if ($node instanceof Node\Stmt\StaticVar)
      return Symbol::identifier($node->name, 'variable');

    if ($node instanceof Node\Stmt\Trait_)
      return Symbol::identifier($node->name, 'class');

    if ($node instanceof Node\Stmt\UseUse)
      return Symbol::identifier($node->name, $group);

    return '';

  }

  static function symbolIdentifierGroup ($symbol) {
    $symbol = Symbol::unqualified($symbol);
    if (strpos($symbol, 'c') === 0)
      return 'class';
    if (strpos($symbol, 'd') === 0)
      return 'constant';
    if (strpos($symbol, 'f') === 0)
      return 'function';
    if (strpos($symbol, 'n') === 0)
      return 'namespace';
    if (strpos($symbol, 'r') === 0)
      return '';
    if (strpos($symbol, 's') === 0)
      return '';
    if (strpos($symbol, 't') === 0)
      return 'type';
    if (strpos($symbol, 'v') === 0)
      return 'variable';
    assert(false);
  }

  static function concat ($a, $b, $glue = '.') {
    // @todo: Remove
    $a = $a == 'n_' ? '' : $a;
    return $a . ($a && $b ? $glue : '') . $b;
  }

  static function isAbsolute ($symbol) {
    return substr($symbol, 0, 1) == '.';
  }

  static function isContext ($scope) {
    return strpos($scope, '.') !== false && in_array(substr($scope, strrpos($scope, '.') + 1, 1), ['f', 'c']);
  }

  static function autoload ($symbol) {
    if ($symbol instanceof Node)
      $symbol = $symbol->getAttribute('phpId');
    $symbol = ltrim($symbol, '\\');
    #var_dump('TRY LOAD ..................: ' . $symbol);
    if (isset(op\metaContext('code')->autoloadLookups[$symbol]))
      return;
    #var_dump('NEW LOAD ..................: ' . $symbol);
    op\metaContext('code')->autoloadLookups[$symbol] = true;
    foreach (op\metaContext('code')->autoloaders as $autoloader) {
      $autoloader['autoloader']->__invoke($symbol);
    }
    op\metaContext('code')->extensionInterface['phlint.phpAutoloadClass']($symbol, op\metaContext('code'));
  }

  static function parent ($scope) {
    return strpos($scope, '.') === false ? '' : substr($scope, 0, strrpos($scope, '.'));
  }

  /**
   * Get unqualified symbol identifier.
   * This function is meant to be used by rules and other inferences.
   *
   * @param string $symbol
   * @return string
   */
  static function unqualified ($symbol) {
    return substr('.' . $symbol, strrpos('.' . $symbol, '.') + 1);
  }

  /**
   * Return potential symbols that would be visible if $symbol would be looked up.
   *
   * @param string $symbol
   * @return Traversable
   */
  static function visibleScopes ($symbol) {
    $unqualifiedSymbol = Symbol::unqualified($symbol);
    foreach (Scope::visibleScopes(Scope::symbolScope($symbol)) as $visibleScope)
      yield Symbol::concat($visibleScope, $unqualifiedSymbol);
  }

  /**
   * Get declaration node analysis-time known symbol.
   *
   * @param object|string $node Node whose symbol to get or a php keyword.
   * @return string
   */
  static function get ($node) {

    if (is_string($node))
      return inference\Symbol::identifier($node, 'auto');

    assert(NodeConcept::isDeclarationNode($node) || NodeConcept::isDefinitionNode($node));
    assert(count($node->getAttribute('symbols', [])) == 1);

    return $node->getAttribute('symbols', [])[0];

  }

}
