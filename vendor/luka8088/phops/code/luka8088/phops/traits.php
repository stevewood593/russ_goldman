<?php

namespace luka8088\phops;

use \Traversable;

function allMembers ($symbol) {
  if (is_array($entity))
    return array_keys($entity);
  if (is_object($entity))
    if (method_exists($entity, 'allMembers'))
      return $entity->allMembers();
    else
      return opConcat(array_keys(get_object_vars($entity)), get_class_methods($entity));
  assert(false);
}

function hasMember ($entity, $member) {
  assert(is_string($member));
  if ($entity === null || is_scalar($entity))
    return false;
  if (is_array($entity))
    return array_key_exists($member, $entity);
  if (is_object($entity) && method_exists($entity, 'hasMember'))
    return $entity->hasMember($member);
  if (is_string($entity) || is_object($entity))
    return property_exists($entity, $member) || method_exists($entity, $member);
  assert(false);
}

function isString ($value) {
  if (is_string($value))
    return true;
  if (is_scalar($value))
    return false;
  if (hasMember($value, 'toString'))
    return true;
}

function isTraversable ($value) {
  return is_array($value) || $value instanceof Traversable;
}
