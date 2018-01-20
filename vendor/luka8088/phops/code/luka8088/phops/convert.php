<?php

namespace luka8088\phops;

use \RuntimeException;

/**
 * Converts a `$value` of any type to a value of `$type`.
 *
 * If passed in value cannot be converted to a `$type` then a `RuntimeException` is thrown.
 *
 * @param string $type
 * @param mixed $value
 * @return mixed
 */
function convertTo ($type, $value) {
  assert(count(func_get_args()) == 2);
  if ($type == 'bool')
    return toBool($value);
  if ($type == 'float')
    return toFloat($value);
  if ($type == 'int')
    return toInt($value);
  if ($type == 'string')
    return toString($value);
  if (is_a($value, $type))
    return $value;
  throw new RuntimeException('Unable to convert to *' . $type . '*.');
}

/**
 * Converts any value to a `bool`.
 *
 * If passed in value cannot be converted to a `bool` then a `RuntimeException` is thrown.
 *
 * @param mixed $value
 * @return bool
 */
function toBool ($value) {
  assert(count(func_get_args()) == 1);
  if (is_scalar($value)) {
    $convertedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($convertedValue !== null)
      return $convertedValue;
  }
  if (is_object($value) && hasMember($value, 'toBool'))
    return toBool($value->toBool());
  throw new RuntimeException('Unable to convert to bool.');
}

/**
 * Converts any value to a `float`.
 *
 * If passed in value cannot be converted to a `float` then a `RuntimeException` is thrown.
 *
 * @param mixed $value
 * @return float
 */
function toFloat ($value) {
  assert(count(func_get_args()) == 1);
  if (is_scalar($value)) {
    $convertedValue = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    if ($convertedValue !== null)
      return $convertedValue;
  }
  if (is_object($value) && hasMember($value, 'toFloat'))
    return toFloat($value->toFloat());
  throw new RuntimeException('Unable to convert to float.');
}

/**
 * Converts any value to an `int`.
 *
 * If passed in value cannot be converted to an `int` then a `RuntimeException` is thrown.
 *
 * @param mixed $value
 * @return int
 */
function toInt ($value) {
  assert(count(func_get_args()) == 1);
  if (is_scalar($value)) {
    /**
     * Conversion to float is being applied to the value to allow leading zeros.
     * @see https://bugs.php.net/bug.php?id=43372
     */
    $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $convertedValue = filter_var($floatValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
    if ($convertedValue !== null)
      return $convertedValue;
  }
  if (is_object($value) && hasMember($value, 'toInt'))
    return toInt($value->toInt());
  throw new RuntimeException('Unable to convert to int.');
}

/**
 * Converts any value to a `string`.
 *
 * If passed in value cannot be converted to a `string` then a `RuntimeException` is thrown.
 *
 * @param mixed $value
 * @return string
 */
function toString ($value) {
  assert(count(func_get_args()) == 1);
  if (is_string($value) || is_int($value) || is_float($value))
    return (string) $value;
  if (is_object($value) && hasMember($value, 'toString'))
    return toString($value->toString());
  throw new RuntimeException('Unable to convert to string.');
}
