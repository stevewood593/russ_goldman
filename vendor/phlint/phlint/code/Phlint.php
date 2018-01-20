<?php

use \luka8088\ExtensionCall;
use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \phlint\Code;
use \phlint\IIData;
use \phlint\Internal;
use \phlint\Output;
use \phlint\Result;

class Phlint implements ArrayAccess {

  /** @internal */
  protected $extensionInterface = null;

  /** @internal */
  public $parser = null;

  /** @internal */
  public $inferences = [];

  /** @internal */
  public $rules = [];

  /** @internal */
  public $enabledRules = [];

  /** @internal */
  public $code = [];

  /** @internal */
  public $importStandardDefinitions = true;

  /**
   * Intermediately inferred data.
   *
   * @see /documentation/glossary/intermediatelyInferredData.md
   *
   * @internal
   */
  public $iiData = null;

  static function create () {
    return new static();
  }

  function __construct () {

    $this->iiData = new ArrayObject();

    $this->extensionInterface = new ExtensionInterface();

    $this->extensionInterface[] = [
      /** @ExtensionCall("phlint.analysis.begin") */ function () {},
      /** @ExtensionCall("phlint.analysis.end") */ function () {},
      /** @ExtensionCall("phlint.analysis.issueFound") */ function ($issue) {},
      /** @ExtensionCall("phlint.phpAutoloadClass") */ function ($className, $code) {},
      /** @ExtensionCall("phlint.phpAutoloadInitialize") */ function () {},
    ];

    $this->output = new Output(null);

    $this->registerInference(new \phlint\inference\Attribute());
    $this->registerInference(new \phlint\inference\Constraint());
    $this->registerInference(new \phlint\inference\Execution());
    $this->registerInference(new \phlint\inference\ExecutionBarrier());
    $this->registerInference(new \phlint\inference\Include_());
    $this->registerInference(new \phlint\inference\Isolation());
    $this->registerInference(new \phlint\inference\NodeRelation());
    $this->registerInference(new \phlint\inference\Purity());
    $this->registerInference(new \phlint\inference\Scope());
    $this->registerInference(new \phlint\inference\Symbol());
    $this->registerInference(new \phlint\inference\SymbolInitialization());
    $this->registerInference(new \phlint\inference\SymbolLink());
    $this->registerInference(new \phlint\inference\TemplateSpecialization());
    $this->registerInference(new \phlint\inference\Trait_());
    $this->registerInference(new \phlint\inference\Trusted());
    $this->registerInference(new \phlint\inference\Type());
    $this->registerInference(new \phlint\inference\Value());

    $this->registerRule(new \phlint\rule\EnforceIsolatedAttribute());
    $this->registerRule(new \phlint\rule\EnforcePureAttribute());
    $this->registerRule(new \phlint\rule\EvaluateAsserts());
    $this->registerRule(new \phlint\rule\EvaluateConstraints());
    $this->registerRule(new \phlint\rule\ProhibitCaseInsensitiveNaming());
    $this->registerRule(new \phlint\rule\ProhibitDuplicateArrayDeclarationKeys());
    $this->registerRule(new \phlint\rule\ProhibitExit());
    $this->registerRule(new \phlint\rule\ProhibitIncompatibleInvocationArguments());
    $this->registerRule(new \phlint\rule\ProhibitInvalidTypeDeclaration());
    $this->registerRule(new \phlint\rule\ProhibitNonExistingSymbolInvocation());
    $this->registerRule(new \phlint\rule\ProhibitRedefining());
    $this->registerRule(new \phlint\rule\ProhibitShortOpenTag());
    $this->registerRule(new \phlint\rule\ProhibitUnusedImports());
    $this->registerRule(new \phlint\rule\ProhibitVariableAppendInitialization());
    $this->registerRule(new \phlint\rule\ProhibitVariableVariables());
    $this->registerRule(new \phlint\rule\RequireCompatibleConcept());
    $this->registerRule(new \phlint\rule\RequireVariableInitialization());

    $this->enableRule('default');

  }

  /**
   * @param $path Path to a file or folder with files to analyze.
   */
  function addPath ($path, $isLibrary = false) {

    $this->code[] = [
      'path' => $path,
      'source' => '',
      #'ast' => null,
      'isLibrary' => $isLibrary,
    ];

    return $this;

  }

  /**
   * @param $source PHP source code to analyze.
   */
  function addSource ($source, $isLibrary = false) {

    $this->code[] = [
      'path' => '',
      'source' => $source,
      #'ast' => null,
      'isLibrary' => $isLibrary,
    ];

    return $this;

  }

  /** @internal */
  public $autoloaders = [];

  /** @internal */
  public $output = null;

  /** @internal */
  function addOutput ($output) {
    $this->output = new Output($output);
    return $this;
  }

  /**
   * @param $path Path to a file or folder with files to analyze.
   */
  function analyzePath ($path = '') {

    if ($path) {
      $this->addPath($path);
    }

    $result = $this->_analyze();

    if ($path)
      array_pop($this->code);

    return $result;
  }

  /**
   * @param $source PHP source code to analyze.
   */
  function analyze ($source = '') {

    if ($source) {
      $this->addSource($source);
    }

    $result = $this->_analyze();

    if ($source)
      array_pop($this->code);

    return $result;
  }

  protected function _analyze () {

    $self = $this;

    $this->extensionInterface['phlint.analysis.begin']();

    $code = new Code();
    $result = new Result();

    $code->extensionInterface = $this->extensionInterface;
    $result->extensionInterface = $this->extensionInterface;

    foreach ($this->inferences as $inference) {
      $this->extensionInterface[] = $inference;
      if (method_exists($inference, 'setExtensionInterface'))
        $inference->setExtensionInterface($this->extensionInterface);
    }

    foreach ($this->rules as $rule) {
      $this->extensionInterface[] = $rule;
      if (method_exists($rule, 'setExtensionInterface'))
        $rule->setExtensionInterface($this->extensionInterface);
    }

    $code->acode = $this->code;

    $this->iiData->exchangeArray([]);

    $iiData = $this->iiData;

    // Todo: Rewrite
    static $phpStandardCode = [];

    $extensionInterfaceMetaContext = op\metaContextCreateScoped(ExtensionInterface::class, $self->extensionInterface);
    $iiDataMetaContext = op\metaContextCreateScoped(IIData::class, $self->iiData);
    $outputMetaContext = op\metaContextCreateScoped('output', $self->output);
    $resultMetaContext = op\metaContextCreateScoped('result', $result);
    $codeMetaContext = op\metaContextCreateScoped('code', $code);

    #op\metaContext(ExtensionInterface::class, $self->extensionInterface, function () use ($self, $code, $result, $iiData, &$phpStandardCode) {
    #op\metaContext(IIData::class, $self->iiData, function () use ($self, $code, $result, $iiData, &$phpStandardCode) {

    #op\metaContext('output', $self->output, function () use ($self, $code, $result, $iiData, &$phpStandardCode) {

      #op\metaContext('result', $result, function () use ($self, $code, $result, $iiData, &$phpStandardCode) {

        #op\metaContext('code', $code, function () use ($self, $code, $result, $iiData, &$phpStandardCode) {

          $beginTimestamp = microtime(true);

          $rules = [];
          $inferences = [];

          $collectInferences = function ($requiredInferences) use (&$collectInferences, &$inferences, $self) {
            foreach ($self->inferences as $specificInference)
              if (in_array($specificInference->getIdentifier(), $requiredInferences)) {
                if (method_exists($specificInference, 'getDependencies'))
                  $collectInferences($specificInference->getDependencies());
                $hasInference = false;
                foreach ($inferences as $existingInference)
                  if ($existingInference === $specificInference)
                    $hasInference = true;
                if (!$hasInference)
                  $inferences[] = $specificInference;
              }
          };

          foreach ($self->rules as $specificRule)
            if (in_array($specificRule->getIdentifier(), $this->enabledRules)) {
              $hasRule = false;
              foreach ($rules as $existingRule)
                if ($existingRule === $specificRule)
                  $hasRule = true;
              if (!$hasRule)
                $rules[] = $specificRule;
              if (method_exists($specificRule, 'getInferences'))
                $collectInferences($specificRule->getInferences());
            }

          $code->inferences = $inferences;
          $code->autoloaders = $self->autoloaders;

          if ($this->importStandardDefinitions) {
          op\metaContext('output')->__invoke("Importing php standard definitions ...\n");

          // @todo: Rewrite
          static $phpStandardDefinitionsAst = [];

          if (count($phpStandardDefinitionsAst) == 0)
            $phpStandardDefinitionsAst = array_merge(
              Internal::importDefinitions('core'),
              Internal::importDefinitions('extension-date'),
              Internal::importDefinitions('extension-filter'),
              Internal::importDefinitions('extension-hash'),
              Internal::importDefinitions('extension-libxml'),
              Internal::importDefinitions('extension-pcre'),
              Internal::importDefinitions('extension-readline'),
              Internal::importDefinitions('extension-reflection'),
              Internal::importDefinitions('extension-session'),
              Internal::importDefinitions('extension-spl'),
              Internal::importDefinitions('standard')
            );

          // @todo: Rewrite
          if (count($phpStandardCode) == 0) {
            call_user_func(function () use ($inferences, &$phpStandardDefinitionsAst, $iiData, &$phpStandardCode) {
            #op\metaContext('code', new Code(), function () use ($inferences, &$phpStandardDefinitionsAst, $iiData, &$phpStandardCode) {
              $codeMetaContext = op\metaContextCreateScoped('code', new Code());
              op\metaContext('code')->extensionInterface = new ExtensionInterface();
              op\metaContext('code')->extensionInterface[] = [
                /** @ExtensionCall("phlint.analysis.begin") */ function () {},
                /** @ExtensionCall("phlint.analysis.end") */ function () {},
                /** @ExtensionCall("phlint.analysis.issueFound") */ function ($issue) {},
                /** @ExtensionCall("phlint.phpAutoloadClass") */ function ($className, $code) {},
                /** @ExtensionCall("phlint.phpAutoloadInitialize") */ function () {},
              ];
              op\metaContext('code')->inferences = $inferences;
              op\metaContext('code')->autoloaders = [];
              Code::infer($phpStandardDefinitionsAst);
              $phpStandardCode = [
                'iiData' => $iiData->getArrayCopy(),
                'data' => op\metaContext('code')->data,
                'scopes' => op\metaContext('code')->scopes,
                'interfaceSymbolMap' => op\metaContext('code')->interfaceSymbolMap,
              ];
            });
          }
          $iiData->exchangeArray(array_merge($phpStandardCode['iiData'], $iiData->getArrayCopy()));
          op\metaContext('code')->data = array_merge($phpStandardCode['data'], op\metaContext('code')->data);
          op\metaContext('code')->scopes = array_merge($phpStandardCode['scopes'], op\metaContext('code')->scopes);
          op\metaContext('code')->interfaceSymbolMap = array_merge($phpStandardCode['interfaceSymbolMap'], op\metaContext('code')->interfaceSymbolMap);

          $code->addAst($phpStandardDefinitionsAst, true);
          }

          op\metaContext('output')->__invoke("Loading code ...\n");

          foreach ($code->autoloaders as $autoloader)
            if (method_exists($autoloader['autoloader'], 'initialize'))
              $autoloader['autoloader']->initialize();
          op\metaContext('code')->extensionInterface['phlint.phpAutoloadInitialize']();

          op\metaContext('output')->indent();
          $code->load($self->code);
          op\metaContext('output')->unindent();

          op\metaContext('output')->__invoke("Analyzing code ...\n");

          #$rules = new \PhpParser\NodeTraverser();

          #foreach ($self->rules as $rule) {
          #  $rules->addVisitor($rule);
          #}

          #foreach ($code->asts as $index => $ast) {
          #  if ($code->astsIsLibrary[$index])
          #    continue;
          #  $rules->traverse($ast);
          #}

          foreach ($code->asts as $index => $ast) {
            if ($code->astsIsLibrary[$index])
              continue;
            Code::analyze($ast, $rules);
          }

          $libraryResult = new Result();

          $libraryResult->extensionInterface = new ExtensionInterface();

          $libraryResult->extensionInterface[] = [
            /** @ExtensionCall("phlint.analysis.begin") */ function () {},
            /** @ExtensionCall("phlint.analysis.end") */ function () {},
            /** @ExtensionCall("phlint.analysis.issueFound") */ function ($issue) {},
            /** @ExtensionCall("phlint.phpAutoloadClass") */ function ($className, $code) {},
            /** @ExtensionCall("phlint.phpAutoloadInitialize") */ function () {},
          ];

          call_user_func(function () use ($self, $libraryResult, $code, $result, $rules, &$phpStandardCode) {

            $resultMetaContext = op\metaContextCreateScoped('result', $libraryResult);
            $outputMetaContext = op\metaContextCreateScoped('output', function () {});

          #op\metaContext('result', $libraryResult, function () use ($self, $code, $result, $rules, &$phpStandardCode) {
            #op\metaContext('output', function () {}, function () use ($self, $code, $result, $rules, &$phpStandardCode) {
              foreach (op\metaContext('code')->data['symbols'] as $symbol => $_)
                if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
                foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode) {
                  if (!$definitionNode->getAttribute('isLibrary', false))
                    continue;
                  if ($definitionNode->getAttribute('isSpecialization', false))
                    continue;
                  Code::analyze([$definitionNode], $rules);
                }
            #});
          #});
          });

          op\metaContext('result')->libraryIssues = $libraryResult->getIssues();

          foreach (op\metaContext('code')->data['symbols'] as $symbol => $_)
            if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
            foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode) {
              if (!$definitionNode->getAttribute('isSpecialization', false))
                continue;
              Code::analyze([$definitionNode], $rules);
            }

          #op\metaContext('output')->__invoke("Result:\n" . $result->toString() . "\n");

          $this->_recordMemoryUsage();

          op\metaContext('output')->__invoke(
            'Done in ' . number_format((microtime(true) - $beginTimestamp) * 1000, 2) . "ms\n" .
            'Maximum memory usage is ' . number_format($this->maximumMemoryUsage / (1024 * 1024), 2) . "MB\n"
          );

        #});

      #});

    #});
    #});
    #});

    $this->extensionInterface['phlint.analysis.end']();

    return $result;

  }

  public $maximumMemoryUsage = 0;

  protected function _recordMemoryUsage () {
    $this->maximumMemoryUsage = max($this->maximumMemoryUsage, memory_get_usage());
  }

  function registerExtension ($extension) {
    $this->extensionInterface[] = $extension;
  }

  /** @internal */
  function offsetExists ($offset) {
    assert(false);
  }

  /** @internal */
  function offsetGet ($offset) {
    assert(false);
  }

  /** @internal */
  function offsetSet ($offset, $value) {
    assert($offset === null);
    $this->registerExtension($value);
  }

  /** @internal */
  function offsetUnset ($offset) {
    assert(false);
  }

  function registerInference ($inference) {
    if (method_exists($inference, 'setIIData'))
      $inference->setIIData($this->iiData);
    $this->inferences[] = $inference;
  }

  function registerRule ($rule) {
    if (method_exists($rule, 'setIIData'))
      $rule->setIIData($this->iiData);
    $this->rules[] = $rule;
  }

  function enableRule ($rule) {
    $class = '\\phlint\\rule\\' . ucfirst($rule);
    if (class_exists($class))
      $this->registerRule(new $class());
    foreach ($this->rules as $specificRule)
      if (self::doesRuleMatch($specificRule, $rule))
        $this->enabledRules[] = $specificRule->getIdentifier();
  }

  function disableRule ($rule) {
    foreach ($this->rules as $specificRule)
      if (self::doesRuleMatch($specificRule, $rule))
        $this->enabledRules = array_filter($this->enabledRules, function ($enabledRule) use ($specificRule) {
          return !($enabledRule == $specificRule->getIdentifier());
        });
  }

  static function doesRuleMatch ($specificRule, $rule) {
    return
      $specificRule->getIdentifier() == $rule ||
      (method_exists($specificRule, 'getCategories') && in_array($rule, $specificRule->getCategories())) ||
      $rule == 'all' ||
      false
    ;
  }

}
