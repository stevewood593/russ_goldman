<?php

namespace luka8088\phops;

use \RuntimeException;

function metaContext ($type) {
  if (!isset(MetaContextData::$contexts[$type]))
    throw new RuntimeException('Metacontext for type *' . $type . '* not initialized.');
  return MetaContextData::$contexts[$type];
}

function metaContextCreateScoped ($type, $value) {
  if (!isset(MetaContextData::$stacks[$type]))
    MetaContextData::$stacks[$type] = [];
  array_push(MetaContextData::$stacks[$type], $value);
  MetaContextData::$contexts[$type] = $value;
  return scopeExit(function () use ($type) {
    array_pop(MetaContextData::$stacks[$type]);
    unset(MetaContextData::$contexts[$type]);
    if (count(MetaContextData::$stacks[$type]) > 0)
      MetaContextData::$contexts[$type] = end(MetaContextData::$stacks[$type]);
    if (count(MetaContextData::$stacks[$type]) == 0)
      unset(MetaContextData::$stacks[$type]);
  });
}

/**
 * @internal
 */
class MetaContextData {
  static $contexts = [];
  static $stacks = [];
}
