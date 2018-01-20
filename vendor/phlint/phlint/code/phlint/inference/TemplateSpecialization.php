<?php

namespace phlint\inference;

use \luka8088\ExtensionCall;
use \luka8088\ExtensionCallOverwrite;
use \luka8088\phops as op;
use \phlint\Code;
use \phlint\IIData;
use \phlint\inference;
use \phlint\inference\Symbol;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \PhpParser\Node;

class TemplateSpecialization {

  function getIdentifier () {
    return 'templateSpecialization';
  }

  function getDependencies () {
    return [
      'constraint',
      'isolation',
      'purity',
      'symbol',
      'type',
      'value',
    ];
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  /**
   * @ExtensionCall("phlint.analysis.inference.symbolLookup/templateSpecialization")
   * @ExtensionCallOverwrite("phlint.analysis.inference.symbolLookup/default")
   */
  function specializeSymbols (&$specializationSymbols, $node, $symbolIdentifierGroup = '') {

    $specializationSymbols = [];

    $this->extensionInterface['phlint.analysis.inference.symbolLookup/default'](
      $symbols,
      $node,
      $symbolIdentifierGroup
    );

    if (!NodeConcept::isInvocationNode($node)) {
      $specializationSymbols = $symbols;
      return;
    }

    if (!inference\Execution::isReachable($node)) {
      $specializationSymbols = $symbols;
      return;
    }

    if (!$node->getAttribute('inAnalysisScope', true)) {
      $specializationSymbols = $symbols;
      return;
    }

    foreach ($symbols as $symbol) {

      // @todo: Refactor.
      if (!isset(op\metaContext('code')->data['symbols'][$symbol]))
        continue;

      $specializationMangle = $this->specializationMangle($node);
      $specializationSymbol = $symbol . $specializationMangle;

      $specializationSymbols[] = $specializationSymbol;

      assert(isset(op\metaContext('code')->data['symbols'][$symbol]));

      if (!isset(op\metaContext('code')->data['symbols'][$specializationSymbol]))
        op\metaContext('code')->data['symbols'][$specializationSymbol] = [
          'id' => $specializationSymbol,
          'phpId' => op\metaContext('code')->data['symbols'][$symbol]['phpId'],
          'aliasOf' => '',
          'declarationNodes' => [],
          'linkedNodes' => [],
        ];

      $isLinkedNodeAttached = false;
      foreach (op\metaContext('code')->data['symbols'][$specializationSymbol]['linkedNodes'] as $existingNode)
        if ($existingNode === $node)
          $isLinkedNodeAttached = true;
      if (!$isLinkedNodeAttached)
        op\metaContext('code')->data['symbols'][$specializationSymbol]['linkedNodes'][] = $node;





      $specializedDeclarationNodes = [];

      if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
      foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $declarationNode) {

        $specializedDeclarationNode = null;
        $specializationKey = $specializationSymbol . '/' . spl_object_hash($declarationNode);
        $requiresInference = false;

        if (!$specializedDeclarationNode && !$this->requiresSpecialization($node, $declarationNode))
          $specializedDeclarationNode = $declarationNode;

        if (!$specializedDeclarationNode && isset(op\metaContext('code')->data['specializationMap'][$specializationKey]))
          $specializedDeclarationNode = op\metaContext('code')->data['specializationMap'][$specializationKey];

        if (!$specializedDeclarationNode) {

          $specializedDeclarationNode = $this->generateSpecialization($node, $declarationNode);

          $specializedDeclarationNode->setAttribute('symbols', [$specializationSymbol]);
          $specializedDeclarationNode->setAttribute(
            'scope',
            $declarationNode->getAttribute('scope', '')
          );
          $specializedDeclarationNode->setAttribute(
            'context',
            $declarationNode->getAttribute('context', '')
          );
          $specializedDeclarationNode->setAttribute(
            'scopeDeclare',
            $declarationNode->getAttribute('scopeDeclare', '') . $specializationMangle
          );

          op\metaContext(IIData::class)['nodeIsIsolated:' . spl_object_hash($specializedDeclarationNode)]
            = inference\Isolation::isIsolated($node) || inference\Purity::isPure($node);

          $specializedDeclarationNode->setAttribute('isSpecializationTemp', true);

          $requiresInference = true;

        }

        if (!isset(op\metaContext('code')->data['specializationMap'][$specializationKey]))
          op\metaContext('code')->data['specializationMap'][$specializationKey] = $specializedDeclarationNode;

        $isDeclarationNodeAttached = false;
        foreach (op\metaContext('code')->data['symbols'][$specializationSymbol]['declarationNodes'] as $existingNode)
          if ($existingNode === $specializedDeclarationNode)
            $isDeclarationNodeAttached = true;
        if (!$isDeclarationNodeAttached)
          op\metaContext('code')->data['symbols'][$specializationSymbol]['declarationNodes'][] = $specializedDeclarationNode;

        if ($requiresInference) {

          Code::infer([$specializedDeclarationNode]);

          $constraints = [];
          foreach ($specializedDeclarationNode->stmts as $statement)
            if ($statement->getAttribute('isConstraint', false))
              $constraints[] = $statement;

          $satisfiesConstraints = true;
          foreach ($constraints as $statement)
            foreach (inference\Value::get($statement->args[0]) as $value)
              if ($value['type'] == 't_bool' && !$value['value'])
                $satisfiesConstraints = false;

          if (!$satisfiesConstraints)
            $specializedDeclarationNode->stmts = $constraints;

        }

      }

    }

  }

  function requiresSpecialization ($invocationNode, $node) {

    // @todo: Implement.
    if ($node instanceof Node\Stmt\Class_)
      return false;

    assert(NodeConcept::isExecutionContextNode($node));

    // @todo: Remove.
    if (in_array(inference\Symbol::get($node), ['f_is_object', 'f_rand', 'f_str_pad', 'f_array_keys', 'f_array_values', 'f_array_rand', 'f_ini_get', 'f_str_replace', 'f_timezone_abbreviations_list', 'f_array_change_key_case', 'f_timezone_identifiers_list', 'f_assert', 'f_count']))
      return false;

    $requiresSpecialization = false;

    foreach (inference\Attribute::get($node) as $attribute)
      if ($attribute instanceof Node\Expr\New_ && count($attribute->args) >= 1
          && inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'template']])
        return true;

    if (inference\Isolation::isIsolated($invocationNode) && !inference\Isolation::isIsolated($node))
      $requiresSpecialization = true;

    if (inference\Purity::isPure($invocationNode) && !inference\Isolation::isIsolated($node) && !inference\Purity::isPure($node))
      $requiresSpecialization = true;

    foreach ($node->params as $parameter)
      if (count(inference\Type::get($parameter)) == 0 && count(inference\Value::get($parameter)) <= ($parameter->default ? 1 : 0))
        $requiresSpecialization = true;

    if (count(inference\Type::getReturn($node)) == 0 && count($node->getAttribute('returnValues', [])) == 0)
      $requiresSpecialization = true;

    return $requiresSpecialization;

  }

  function specializationMangle ($node) {
    static $replacements = [
      '.' => '__',
      '{' => '_(_',
      '}' => '_)_',
    ];
    return '{' . str_replace(
      array_keys($replacements),
      array_values($replacements),
      $this->specializationSignature($node)
    ) . '}';
  }

  function specializationSignature ($node) {

    if (NodeConcept::isInvocationNode($node))
      return '(' . implode(', ', array_map(function ($argument) {
        return implode('|', array_merge(inference\Type::get($argument), array_map(function ($value) {
          return $value['type'] . ':' . (!is_scalar($value['value']) ? serialize($value['value']) : $value['value']);
        }, inference\Value::get($argument))));
      }, $node->args)) . ')' .
      (inference\Isolation::isIsolated($node) ? '!i' : '') .
      (inference\Purity::isPure($node) ? '!p' : '');

    return '';

  }

  function generateSpecialization ($invocationNode, $definitionNode) {

    $specializationNode = NodeConcept::deepClone($definitionNode);

    Code::clean([$specializationNode]);

    NodeTraverser::traverse($specializationNode, [function ($specializationNode) {
      $specializationNode->setAttribute('isSpecialization', true);
      $specializationNode->setAttribute('inAnalysisScope', true);
    }]);

    if (NodeConcept::isExecutionContextNode($specializationNode)) {

        foreach ($invocationNode->args as $offset => $argument) {
          if (isset($specializationNode->params[$offset])) {
            op\metaContext(IIData::class)['nodeTypes:' . spl_object_hash($specializationNode->params[$offset])]
              = count(inference\Type::get($definitionNode->params[$offset])) > 0
              ? inference\Type::get($definitionNode->params[$offset])
              : inference\Type::get($argument);
            if (count(inference\Value::get($argument)) > 0)
              $specializationNode->params[$offset]->setAttribute('values', inference\Value::get($argument));
            else
              $specializationNode->params[$offset]->setAttribute('values', [['type' => 't_mixed', 'value' => '']]);
          }

        }

    }

    return $specializationNode;

  }

}
