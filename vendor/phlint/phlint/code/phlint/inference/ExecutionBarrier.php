<?php

namespace phlint\inference;

use \phlint\NodeConcept;
use \PhpParser\Node;

class ExecutionBarrier {

  function getIdentifier () {
    return 'executionBarrier';
  }

  function getPass () {
    return 10;
  }

  protected $scopeStack = [];

  protected function resetState () {
    $this->scopeStack = [];
  }

  function beforeTraverse ($nodes) {
    $this->resetState();
  }

  function enterNode ($node) {

    if (NodeConcept::isScopeNode($node))
      $this->scopeStack[] = $node;

  }

  function visitNode ($node) {

    if (NodeConcept::isExecutionBarrier($node))
      if (count($this->scopeStack) > 0)
        end($this->scopeStack)->setAttribute('hasExecutionBarrier', true);

  }

  function leaveNode ($node) {

    if (count($this->scopeStack) > 0 && end($this->scopeStack) === $node)
      array_pop($this->scopeStack);

    if ($node instanceof Node\Stmt\If_) {

      $hasExecutionBarrier = true;

      if (!$node->getAttribute('hasExecutionBarrier', false))
        $hasExecutionBarrier = false;

      foreach ($node->elseifs as $elseifNode)
        if (!$elseifNode->getAttribute('hasExecutionBarrier', false))
          $hasExecutionBarrier = false;

      if (!$node->else || !$node->else->getAttribute('hasExecutionBarrier', false))
        $hasExecutionBarrier = false;

      if ($hasExecutionBarrier)
        if (count($this->scopeStack) > 0)
          end($this->scopeStack)->setAttribute('hasExecutionBarrier', true);

    }

  }

}
