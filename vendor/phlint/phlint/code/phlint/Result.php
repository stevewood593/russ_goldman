<?php

namespace phlint;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\Issue;

class Result {

  protected $issues = [];

  public $libraryIssues = [];

  function addIssue ($node, $message) {

    assert(count(func_get_args()) == 2);

    $originalMessage = $message;

    if ($node && $node->getAttribute('isTrusted', false))
      return;

    if ($node && !inference\Execution::isReachable($node))
      return;

    $message = Result::expandMessageMeta($message, $node);

    if (in_array($message, $this->issues))
      return;

    if (in_array($message, $this->libraryIssues))
      return;

    if ($node) {
      $traces = $this->generateTraces($node);
      #var_dump($traces);

      if (count($traces) > 0) {
        $isNew = false;
        foreach ($traces as $trace) {
          $msg = $message;
          foreach (array_reverse($trace) as $offset => $traceMessage)
            $msg .= "\n  " . 'Trace #' . ($offset + 1) . ': ' . Result::expandMessageMeta($traceMessage['message'], $traceMessage['node']);

          if (in_array($msg, $this->issues))
            return;
          $isNew = true;
          $this->issues[] = $msg;
          op\metaContext('output')->__invoke($msg . "\n");
        }
        if ($isNew)
          $this->extensionInterface['phlint.analysis.issueFound'](new Issue($originalMessage, $node, $traces));
        return;
      }
    }

    $this->issues[] = $message;
    $this->extensionInterface['phlint.analysis.issueFound'](new Issue($originalMessage, $node, []));
    op\metaContext('output')->__invoke($message . "\n");
  }

  function getIssues () {
    return $this->issues;
  }

  public $traceStack = [];

  function pushTrace ($node, $message) {
    $this->traceStack[] = Result::expandMessageMeta($message, $node);
  }

  function popTrace () {
    array_pop($this->traceStack);
  }

  function toString () {
    return implode("\n", array_map(function ($entry) {
          return $entry;
      }, array_slice(array_unique($this->getIssues()), 0, 100))) .
      "\n" . (count(array_unique($this->getIssues())) > 100 ? '...' : '') .
      "\n(" . count(array_unique($this->getIssues())) . ' issue(s) found)';
  }

  static function mergeMessages ($messages) {
    $map = [];
    foreach ($messages as $message) {
      if (!isset($map[$message['message']]))
        $map[$message['message']] = $message;
    }
    return array_values($map);
  }

  static function printMessage ($message) {
    if (is_string($message))
      return $message;
    $printed = $message['message'];
    return $printed;
  }

  static function expandMessageMeta ($message, $node) {
    if (!is_object($node))
      return $message;

    if (isset($node->getAttributes()['path'])) {
      $path = $node->getAttributes()['path'];
      if (realpath($path))
        $path = realpath($path);
      return rtrim($message, '.') . ' in *' . $path . ($node->getLine() > 0 ? ':' . $node->getLine() : '') . '*.';
    }

    if ($node->getLine() > 0)
      return rtrim($message, '.') . ' on line ' . $node->getLine() . '.';

    return $message;
  }

  protected $memo = [];

  function generateTraces ($node, $participatingInvocationNodes = []) {

    if (isset($this->memo[spl_object_hash($node)]))
      return $this->memo[spl_object_hash($node)];

    $traces = [];

    $definitionNodes = [];
    $contextSymbol = inference\Scope::contextScope($node->getAttribute('scope', []));
    $contextSymbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $contextSymbol), '.');

    while ($contextSymbol) {
      if (strpos(inference\Symbol::unqualified($contextSymbol), '{') !== false)
        break;
      $contextSymbol = inference\Symbol::parent($contextSymbol);
    }

    if (isset(op\metaContext('code')->data['symbols'][$contextSymbol])) {
      $definitionNodes = op\metaContext('code')->data['symbols'][$contextSymbol]['declarationNodes'];
    }

    foreach ($definitionNodes as $definitionNode) {

      if (!$definitionNode->getAttribute('isSpecialization', false))
        continue;

    $isRecursion = false;
    foreach ($participatingInvocationNodes as $participatingDefinitionNode)
      if ($participatingDefinitionNode === $definitionNode)
        $isRecursion = true;


    if (!$isRecursion) {

      foreach (inference\SymbolLink::get($definitionNode) as $symbol) {

        $symbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $symbol), '.');

        $definitionContextTraces = [];

        $definitionContextSymbol = inference\Scope::contextScope(inference\Scope::parent($symbol));
        $definitionContextSymbol = rtrim(preg_replace('/(?is)((?<=\.)|\A)s[a-z0-9]*_[^\.]*(\.|\z)/', '', $definitionContextSymbol), '.');

        if (isset(op\metaContext('code')->data['symbols'][$symbol]['linkedNodes']))

          foreach (op\metaContext('code')->data['symbols'][$symbol]['linkedNodes'] as $linkedNode) {

            if (!isset($this->memo[spl_object_hash($linkedNode)]))
              $this->memo[spl_object_hash($linkedNode)] = $this->generateTraces($linkedNode, array_merge($participatingInvocationNodes, [$definitionNode]));

            $linkedNodeTraces = $this->memo[spl_object_hash($linkedNode)];

            $message = ucfirst(NodeConcept::referencePrint($definitionNode)) .
              ' specialized for the ' . NodeConcept::referencePrint($linkedNode) . '.' . ($isRecursion ? 'recursion detected!' : '');

            foreach (count($linkedNodeTraces) > 0 ? $linkedNodeTraces : [0 => ''] as $traceIndex => $_) {
              if (!isset($linkedNodeTraces[$traceIndex]))
                $linkedNodeTraces[$traceIndex] = [];
              $linkedNodeTraces[$traceIndex][] = ['message' => $message, 'node' => $linkedNode];
            }

            foreach ($linkedNodeTraces as $linkedNodeTrace)
              $traces[] = $linkedNodeTrace;

          }

      }

    }

    }

    // @todo: Remove or rewrite.
    $traces = array_slice($traces, 0, 5);

    return $traces;

  }

}
