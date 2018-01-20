<?php

namespace phlint\inference;

use \luka8088\Attribute as AttributeReader;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \PhpParser\Node;

/**
 * @see /documentation/attribute/index.md
 */
class Attribute {

  function getIdentifier () {
    return 'attribute';
  }

  function getDependencies () {
    return [
      'nodeRelation',
    ];
  }

  /**
   * Lookup the node attributes.
   * Note that this call can be significantly expensive.
   * For general purpose it is better to call `::get` which will
   * call lookup implicitly if needed.
   *
   * @internal
   */
  static function lookup ($node) {

    $attributes = [];

    if ($node instanceof Node\Param) {
      $contextNode = op\metaContext(IIData::class)['contextNode:' . spl_object_hash($node)];
      assert($contextNode, 'Parameter without a context node found!');
      $phpDocAttribute = null;
      $parameterIndex = -1;
      foreach ($contextNode->params as $index => $parameter)
        if ($parameter === $node) {
          $parameterIndex = $index;
          break;
        }
      assert($parameterIndex >= 0);
      $attributeIndex = 0;
      foreach (inference\Attribute::get($contextNode) as $attribute) {
        if (!($attribute instanceof Node\Expr\New_ &&
            count($attribute->args) >= 1 &&
            inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'param']]))
          continue;
        if ($attributeIndex < $parameterIndex) {
          $attributeIndex += 1;
          continue;
        }
        $attributes[] = $attribute;
        break;
      }
    }

    $commentToString = function ($comment) {
      if (method_exists($comment, 'getText'))
        return $comment->getText();
      return $comment;
    };

    foreach (array_map($commentToString, $node->getAttribute('comments', [])) as $comment)
      foreach (AttributeReader::extractAttributes($comment) as $attribute) {

        $attributeAst = op\metaContext('code')->parse('<?php ' . $attribute['phpCode'] . ';');

        assert(count($attributeAst) == 1);
        $attributeNode = $attributeAst[0];

        // @todo: Rethink and remove.
        if ($attributeNode instanceof Node\Expr\Ternary)
          $attributeNode = $attributeNode->else;

        $attributeNode->setAttribute('displayPrint', $attribute['source']);
        $attributeNode->setAttribute('startLine', 0);

        $attributes[] = $attributeNode;

      }

    return $attributes;

  }

  /**
   * Get node analysis-time known attributes.
   *
   * @param object $node Node whose attributes to get.
   * @return object[]
   */
  static function get ($node) {

    if (!isset(op\metaContext(IIData::class)['nodeAttributes:' . spl_object_hash($node)]))
      op\metaContext(IIData::class)['nodeAttributes:' . spl_object_hash($node)] = inference\Attribute::lookup($node);

    return op\metaContext(IIData::class)['nodeAttributes:' . spl_object_hash($node)];

  }

}
