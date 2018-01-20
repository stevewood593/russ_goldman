<?php

namespace phlint;

use \phlint\Code;
use \phlint\NodeTraverser;
use \phlint\SourceNode;

/**
 * This class handles PHP internal and standard library information that
 * is not available out of the box or needs to be available in a
 * specific format.
 */
class Internal {

  static function importDefinitions ($id) {

    static $cache = [];

    if (!isset($cache[$id])) {

      $cache[$id] = [new SourceNode(Code::parse(\Phif::importDeclarations($id)))];

      NodeTraverser::traverse($cache[$id], [function ($node) {
        #$node->setAttribute('isSourceAvailable', false);
        $node->setAttribute('startLine', 0);
        $node->setAttribute('endLine', 0);
        foreach ($node->getAttribute('comments', []) as $attribute)
          if (method_exists($attribute, 'setLine'))
            $attribute->setLine(0);
      }]);

    }

    // @todo: Clone.
    return $cache[$id];

  }

  /**
   * Disable XDebug due to performance issues with it.
   * It seems that for data processing heavy code XDebug has
   * massive performance implications.
   */
  static function disableXDebug()
  {
      if (isset($GLOBALS["__xdebug_disable_attempt_made"]))
        return;

      if (!in_array('xdebug', array_map(function ($name) { return strtolower($name); }, get_loaded_extensions(true))))
        return;

      $process = new \Symfony\Component\Process\PhpProcess('<?php
        $GLOBALS["__xdebug_disable_attempt_made"] = true;
        $GLOBALS["argv"] = ' . var_export($GLOBALS['argv'], true) . ';
        require ' . var_export(debug_backtrace()[0]['file'], true) . ';
      ');

      $process->setTimeout(null);

      /**
       * Don't load the default ini file. This is the main reason why we are running
       * a sub-process in the first place - to disable XDebug.
       * It seems that XDebug can't be disabled during runtime, nor can an extension
       * defined in the php.ini be excluded from loading with parameters.
       * So far this seems to be the only way not to load XDebug.
       */
      $process->setCommandLine($process->getCommandLine() . ' -n');

      /**
       * For some reason these two extensions are not statically linked
       * on *nix systems so we need to load the explicitly.
       */
      if (PHP_SHLIB_SUFFIX == 'so')
        $process->setCommandLine($process->getCommandLine() . ' -dextension=tokenizer.so -dextension=json.so');

      $output = fopen('php://stdout', 'w');
      $error = fopen('php://stderr', 'w');

      $process->run(function ($type, $buffer) use ($output, $error) {
        if ($type == \Symfony\Component\Process\Process::OUT)
          fwrite($output, $buffer);
        if ($type == \Symfony\Component\Process\Process::ERR)
          fwrite($error, $buffer);
      });

      /**
       * In case the sub process is successful it runs the original
       * code - without XDebug.
       * In that case continuing to execute would produce a duplicate.
       * If it's not successful the parent process does not exit and
       * continues to fallback with XDebug.
       */
      if ($process->isSuccessful())
        exit;

  }

}
