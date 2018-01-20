<?php

namespace phlint\inference;

use \ArrayObject;
use \phlint\inference\Symbol;
use \phlint\NodeConcept;
use \PhpParser\Node;
use \Traversable;

/**
 * Scope inference.
 *
 * Two overlapping terms can easily be confusing - `scope` and `context`.
 * Contexts are isolated (and they form a new isolated scope)
 * while other scopes are not isolated.
 *
 * For example:
 *   function x () {
 *     $y = 0;
 *     if (rand(0, 1)) {
 *       $y = 2;
 *     }
 *   }
 *
 * In the example function `x` forms it's own context and variables cannot be
 * implicitly referenced outside of the function.
 * On the other hand, `if` forms it's own scope but it's not isolated
 * and referencing variables that are outside of it can be referenced.
 * Technically in PHP `if` doesn't actually form a new scope, and
 * any initialization/referencing works in the exact same way as it would
 * if the `if` would not be there. Even so these two have been split up
 * here on purpose as some analyses require it.
 */
class Scope {

  function getIdentifier () {
    return 'scope';
  }

  /**
   * Is it a context scope?
   * Context scopes are scopes of functions, classes, etc. In other words scopes which
   * are isolated in a way that, for example, variables outside of them are not directly
   * accessible.
   *
   * @param string $scope
   * @return bool
   */
  static function isContext ($scope) {
    return in_array(substr('.' . $scope, strrpos('.' . $scope, '.') + 1, 1), ['f', 'c', '']);
  }

  static function parent ($scope) {
    return strpos($scope, '.') === false ? '' : substr($scope, 0, strrpos($scope, '.'));
  }

  /**
   * Return a closest named ancestor scope.
   *
   * @param string $symbol
   * @return string
   */
  static function namedAncestor ($scope) {
    $ancestorScope = $scope;
    while (true) {
      $ancestorScope = Scope::parent($ancestorScope);
      if (!$ancestorScope)
        break;
      if (in_array(substr(Symbol::unqualified($ancestorScope), 0, 1), ['', 'c', 'd', 'f', 'n']))
        return $ancestorScope;
    }
    return '';
  }

  static function symbolScope ($symbol) {
    return ltrim(substr('.' . $symbol, 0, strrpos('.' . $symbol, '.')), '.');
  }

  /**
   * Return context scope for $scope.
   *
   * @param string $scope
   * @return string
   */
  static function contextScope ($scope) {
    $currentScope = $scope;
    while ($currentScope) {
      if (in_array(Symbol::symbolIdentifierGroup($currentScope), ['class', 'function']))
        return $currentScope;
      $currentScope = Scope::parent($currentScope);
    }
    return '';
  }

  /**
   * Return namespace scope for $scope.
   *
   * @param string $scope
   * @return string
   */
  static function namespaceScope ($scope) {
    $currentScope = $scope;
    while ($currentScope) {
      if (Scope::parent($currentScope) && Symbol::symbolIdentifierGroup(Scope::parent($currentScope)) == 'namespace')
        return $currentScope;
      if (!Scope::parent($currentScope) && substr($currentScope, 0, 2) == 's_')
        return $currentScope;
      $currentScope = Scope::parent($currentScope);
    }
    return '';
  }

  /**
   * Return scopes which are visible from $scope.
   *
   * @param string $scope
   * @return Traversable
   */
  static function visibleScopes ($scope) {
    $visibleScope = $scope;
    while (true) {
      yield $visibleScope;
      if (Scope::isContext($visibleScope))
        break;
      if (!$visibleScope)
        break;
      $visibleScope = Scope::parent($visibleScope);
    }
  }

}
