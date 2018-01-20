<?php

namespace phlint;

class Issue {

  public $message = '';
  public $node = null;
  public $traces = [];

  function __construct ($message, $node, $traces = []) {
    $this->message = $message;
    $this->node = $node;
    $this->traces = $traces;
  }

  function toString () {
    return self::print_($this);
  }

  function getMessage () {
    return self::expandMessageMeta($this);
  }

  function getTraces () {
    return self::printTraces($this);
  }

  static function print_ ($message) {
    $printed = self::expandMessageMeta($message);
    foreach (is_object($message) ? $message->traces : $message['traces'] as $trace) {
      $printed .= "\n  " . 'Trace:';
      foreach (array_reverse($trace) as $offset => $traceMessage)
        $printed .= "\n    " . '#' . ($offset + 1) . ': ' . self::expandMessageMeta($traceMessage);
    }
    return $printed;
  }

  static function printTraces ($message) {
    $printed = '';
    foreach (is_object($message) ? $message->traces : $message['traces'] as $trace) {
      $printed .= "\n  " . 'Trace:';
      foreach (array_reverse($trace) as $offset => $traceMessage)
        $printed .= "\n    " . '#' . ($offset + 1) . ': ' . self::expandMessageMeta($traceMessage);
    }
    return substr($printed, 1);
  }

  static function expandMessageMeta ($message) {
    $node = is_object($message) ? $message->node : $message['node'];
    $message = is_object($message) ? $message->message : $message['message'];
    if ($node && isset($node->getAttributes()['path'])) {
      $path = $node->getAttributes()['path'];
      if (realpath($path))
        $path = realpath($path);
      return rtrim($message, '.') . ' in *' . $path . ($node->getLine() > 0 ? ':' . $node->getLine() : '') . '*.';
    }
    if ($node && $node->getLine() > 0)
      return rtrim($message, '.') . ' on line ' . $node->getLine() . '.';
    return $message;
  }

}
