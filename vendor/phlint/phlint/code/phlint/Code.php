<?php

namespace phlint;

use \ArrayObject;
use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeTraverser;
use \phlint\SourceNode;
use \ReflectionMethod;

class Code {

  public $data = [];

  /** @internal */
  public $acode = [];

  /** @internal */
  public $inferences = [];

  /** @internal */
  public $autoloaders = [];

  /** @internal */
  public $autoloadLookups = [];

  /** @internal */
  public $globalVariables = [];

  /** @internal */
  public $scopes = [];

  public $asts = [];
  public $astsIsLibrary = [];

  public $loadedFileMap = [];

  public $imports = [];

  public $interfaceSymbolMap = [];

  function __construct () {
    $this->globalVariables = new ArrayObject();
  }

  function addAst ($ast, $isLibrary = false) {

    if (count($ast) == 0 || !$ast[0]->getAttribute('inferred'))
      Code::infer($ast, $this->inferences);

    $this->asts[] = $ast;
    $this->astsIsLibrary[] = $isLibrary;
  }

  function load ($code) {

    $expandedCode = [];

    #op\metaContext('output')->__invoke("Collecting code files ...\n");

    foreach ($code as $codeEntry) {

      if ($codeEntry['source']) {
        $codeEntry['source'] = preg_replace('/(?is)\A\r?\n/', '', $codeEntry['source']);
        $codeEntry['source'] = (substr($codeEntry['source'], 0, 2) != '<?' ? '<?php ' : '') . $codeEntry['source'];
        $expandedCode[] = $codeEntry;
        continue;
      }

      if (is_file($codeEntry['path'])) {
        $iterator = [$codeEntry['path']];
      } else {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($codeEntry['path']));
        $iterator = new \RegexIterator($iterator, '/(?i)\.php$/');
      }

      foreach ($iterator as $file) {
        $filePath = is_object($file) ? $file->getPathName() : $file;
        if (isset(op\metaContext('code')->loadedFileMap[$filePath]) || isset(op\metaContext('code')->loadedFileMap[realpath($filePath)]))
          continue;
        op\metaContext('code')->loadedFileMap[$filePath] = true;
        op\metaContext('code')->loadedFileMap[realpath($filePath)] = true;
        $expandedCode[] = [
          'path' => $file,
          'source' => file_get_contents($file),
          'isLibrary' => $codeEntry['isLibrary'],
        ];
      }

    }

    #op\metaContext('output')->__invoke("Parsing code ...\n");

    $aggregatedAst = [];

    foreach ($expandedCode as &$codeEntry) {
      try {
        if (false)
        if ($codeEntry['path'])
          op\metaContext('output')->__invoke(($codeEntry['isLibrary'] ? '  Library: ' : '') . $codeEntry['path'] . "\n");
        $codeEntry['ast'] = [new SourceNode(Code::parse($codeEntry['source']))];
        if ($codeEntry['path'])
          self::populatePath($codeEntry['ast'], $codeEntry['path']);
        if ($codeEntry['isLibrary'])
          NodeTraverser::traverse($codeEntry['ast'], [function ($node) {
            $node->setAttribute('isLibrary', true);
            $node->setAttribute('inAnalysisScope', false);
          }]);
        $aggregatedAst = array_merge($aggregatedAst, $codeEntry['ast']);
      } catch (\PhpParser\Error $exception) {
        op\metaContext('result')->addIssue(null, 'Parse error: ' . $exception->getMessage()
          . ($codeEntry['path'] ? ' in *' . realpath($codeEntry['path']) . '*.' : '.')
        );
      }
    }

    self::infer($aggregatedAst, $this->inferences);

    foreach ($expandedCode as &$codeEntry) {
      if (!isset($codeEntry['ast']))
        continue;
      $this->addAst($codeEntry['ast'], $codeEntry['isLibrary']);
    }

  }

  static function clean ($ast, $inferrers = []) {

    if (count($inferrers) == 0)
      $inferrers = op\metaContext('code')->inferences;

    NodeTraverser::traverse($ast, [function ($node) use ($inferrers) {
      foreach ($inferrers as $inferrer)
        if (method_exists($inferrer, 'cleanNode'))
          $inferrer->cleanNode($node);
    }]);

  }

  static function infer ($ast, $inferrers = []) {

    if (count($inferrers) == 0)
      $inferrers = op\metaContext('code')->inferences;

    foreach ($ast as $astEntry)
      $astEntry->setAttribute('inferred', true);

    #op\metaContext('output')->__invoke("Inferring about code ...\n");

    #op\metaContext('output')->indent();

    $inferrers = array_filter($inferrers, function ($inferrer) {
      return false
        || method_exists($inferrer, 'enterNode')
        || method_exists($inferrer, 'visitNode')
        || method_exists($inferrer, 'leaveNode')
      ;
    });

    $inferrerPasses = [];

    foreach ($inferrers as $inferrer) {

      $pass = 10;

      if (is_callable($inferrer) && (!is_object($inferrer) || ($inferrer instanceof \Closure))) {
        $reflection = new ReflectionMethod($inferrer);
        if (preg_match('/(?is)\@pass[ \t\r\n]*\([ \t\r\n]*([^\)]+)\)/', $reflection->getDocComment(), $match))
          $pass = $match[1];
      }

      if (method_exists($inferrer, 'getPass'))
        $pass = $inferrer->getPass();

      // Allow running the same inference in different passes.
      // @todo: Rethink.
      foreach ((array) $pass as $p) {
        if (!isset($inferrerPasses[$p]))
          $inferrerPasses[$p] = [];
        $inferrerPasses[$p][] = $inferrer;
      }

    }

    ksort($inferrerPasses);

    foreach ($inferrerPasses as $pass => $inferrers) {
      #op\metaContext('output')->__invoke("Running inference pass " . $pass . " ...\n");
      $inferrersx = [];
      foreach ($inferrers as $inferrer)
        $inferrersx[] = clone $inferrer;
      NodeTraverser::traverse($ast, $inferrersx);
    }

    #op\metaContext('output')->unindent();

    #op\metaContext('output')->__invoke("Code inference complete.\n");

    return $ast;

  }

  static function analyze ($ast, $rules) {
    NodeTraverser::traverse($ast, $rules);
  }

  static function parse ($source, $path = '') {

    static $parser = null;

    if ($parser === null) {
      $parserFactory = new \PhpParser\ParserFactory();
      $parser = $parserFactory->create(\PhpParser\ParserFactory::PREFER_PHP7);
    }

    $ast = $parser->parse(preg_replace('/(?is)\A\<\?(?=[ \t\r\n])/', '<?php', $source));

    if (preg_match('/(?is)\A\<\?(?=[ \t\r\n])/', $source) > 0 && count($ast) > 0)
      $ast[0]->setAttribute('hasShortOpenTag', true);

    #NodeTraverser::traverse($ast, [function ($node) {
    #  $node->setAttribute('isSourceAvailable', true);
    #}]);

    return $ast;

  }

  protected static function populatePath ($ast, $path) {
    NodeTraverser::traverse($ast, [function ($node) use ($path) {
      $node->setAttribute('path', $path);
    }]);
  }

}
