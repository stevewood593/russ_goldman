<?php

namespace phlint\rule;

use \luka8088\phops as op;

/**
 * @see /documentation/rule/prohibitShortOpenTag.md
 */
class ProhibitShortOpenTag {

  function getIdentifier () {
    return 'prohibitShortOpenTag';
  }

  function getCategories () {
    return [
      'default',
      'legacy',
    ];
  }

  function visitNode ($node) {

    if ($node->getAttribute('hasShortOpenTag', false))
      op\metaContext('result')->addIssue($node, 'Using short open tag is prohibited.');

  }

}
