<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \PhpParser\Node;

/**
 * Prohibit `use` imports that are not actually been used by the code.
 */
class ProhibitUnusedImports {

  function getIdentifier () {
    return 'prohibitUnusedImports';
  }

  function getCategories () {
    return [
      'tidy',
    ];
  }

  protected $imports = [];
  protected $usages = [];

  protected function resetState () {
    $this->imports = [];
    $this->usages = [];
  }

  function beforeTraverse(array $nodes) {
      $this->resetState();
  }

  function enterNode(Node $node) {

    if ($node instanceof Node\Stmt\Use_) {
      foreach ($node->uses as $import) {
        assert(!op\hasMember($this->imports, $import->alias));
        $this->imports[$import->alias] = $import;
      }
    }

    if ($node instanceof Node\Name) {
      $this->usages[implode('\\', $node->parts)] = $node;
    }

    if ($node instanceof Node\Expr\Variable)
      foreach (inference\Attribute::get($node) as $attribute)
        if ($attribute instanceof Node\Expr\New_ &&
            count($attribute->args) >= 1 &&
            inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'var']]) {
          $this->usages[$attribute->args[1]->value->items[0]->value->value] = new Node\Name(
            $attribute->args[1]->value->items[0]->value->value
          );
        }

  }

  function leaveNode ($node) {
    if ($node instanceof Node\Stmt\Namespace_)
      $this->resetState();
  }

  function afterTraverse (array $nodes) {

    foreach ($this->imports as $alias => $import) {

      $isImportUsed = false;

      $importAlias = substr(self::sourcePrint($import), strrpos(self::sourcePrint($import), '\\') + 1);

      if (op\hasMember($this->usages, $alias)) {
        $isImportUsed = true;
        continue;
      }

      foreach ($this->usages as $usage) {
        if ($usage->parts[0] == $import->alias) {
          $isImportUsed = true;
          break;
        }
        $useBaseAlias = substr(self::sourcePrint($usage), 0, strpos(self::sourcePrint($usage), '\\'));
        if ($importAlias == $useBaseAlias) {
          $isImportUsed = true;
          break;
        }
      }

      if (!$isImportUsed) {
        op\metaContext('result')->addIssue($import, 'Import *' . NodeConcept::sourcePrint($import) . '* is not used.');
      }

    }
  }

  static function sourcePrint ($node) {
    if (is_string($node))
      return $node;
    $prettyPrinter = new \PhpParser\PrettyPrinter\Standard();
    return $prettyPrinter->prettyPrint([$node]);
  }

}
