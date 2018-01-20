<?php

namespace phlint\rule;

/**
 * Makes sure that @pure code is indeed pure.
 *
 * @see /documentation/attribute/pure.md
 */
class EnforcePureAttribute {

  function getIdentifier () {
    return 'enforcePureAttribute';
  }

  function getCategories () {
    return [
      'attribute',
      'default',
    ];
  }

  function getInferences () {
    return [
      'attribute',
      'purity',
    ];
  }

  function visitNode ($node) {
    // @todo: Implement
  }

}
