<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \PhpParser\Node;

/**
 * Duplicate array keys get overwritten by default which might cause
 * unexpected behavior - this rules prohibits that.
 *
 * @see http://php.net/manual/en/language.types.array.php#example-57
 */
class ProhibitDuplicateArrayDeclarationKeys {

  function getIdentifier () {
    return 'prohibitDuplicateArrayDeclarationKeys';
  }

  function getCategories () {
    return [
      'default',
      'unexpectedBehavior',
    ];
  }

  function visitNode ($node) {

    if ($node instanceof Node\Expr\Array_) {

      $keys = [];

      foreach ($node->items as $item) {

        if (!$item->key)
          continue;

        foreach ($keys as $existingKey)
          foreach (inference\Value::get($item->key) as $newKey)
            if ($existingKey === $newKey['value'])
              op\metaContext('result')->addIssue(
                $item,
                'Duplicate array key *' . $newKey['value'] . '*.'
              );

        foreach (inference\Value::get($item->key) as $newKey)
          $keys[] = $newKey['value'];

      }

    }

  }

}
