<?php

namespace phlint\rule;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\NodeTraverser;
use \PhpParser\Node;

/**
 * @see /documentation/rule/prohibitIncompatibleInvocationArguments.md
 */
class ProhibitIncompatibleInvocationArguments {

  function getIdentifier () {
    return 'prohibitIncompatibleInvocationArguments';
  }

  function getCategories () {
    return [
      'default',
    ];
  }

  function getInferences () {
    return [
      'symbol',
      'templateSpecialization',
      'trait',
      'type',
      'value',
    ];
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function visitNode ($node) {

    if (NodeConcept::isInvocationNode($node)) {

      foreach (inference\SymbolLink::get($node) as $symbol)
        if (isset(op\metaContext('code')->data['symbols'][$symbol]['declarationNodes']))
          foreach (op\metaContext('code')->data['symbols'][$symbol]['declarationNodes'] as $definitionNode) {

            $variadicIndex = 0;
            foreach ($node->args as $argumentIndex => $argument) {
              $useStrictTypeConversion = inference\Trait_::useStrictTypeConversion($argument);
              $index = $variadicIndex ? $variadicIndex : $argumentIndex;
              #var_dump($index);
              #var_dump($variadicIndex);
              if (isset($definitionNode->params[$index])) {
                if ($definitionNode->params[$index]->variadic)
                  $variadicIndex = $index;
                #var_dump($definitionNode->params[$index]->variadic, $index);
                foreach (array_unique(array_merge(inference\Type::get($argument), array_map(function ($value) { return $value['type']; }, inference\Value::get($argument)))) as $argumentType) {

                  if ($argumentType == 't_string') {
                    if (array_unique(array_map(function ($value) { return $value['type']; }, inference\Value::get($argument))) == ['t_autoInteger'])
                      continue;
                    if (array_unique(array_map(function ($value) { return $value['type']; }, inference\Value::get($argument))) == ['t_stringInt'])
                      continue;
                  }

                  // @todo: Rewrite
                  if (substr('.' . $argumentType, -9) == '.c_static')
                    continue;

                  $isCompatible = count(inference\Type::get($definitionNode->params[$index])) == 0;

                  foreach (inference\Type::get($definitionNode->params[$index]) as $parameterType) {
                    if ($definitionNode->params[$index]->variadic)
                      $parameterType = substr($parameterType, -2) == '[]' ? substr($parameterType, 0, -2) : '';

                    #if (!inference\Type::isImplicitlyConvertible($argumentType, $parameterType))
                    #  var_dump($argumentType . ' to ' .  $parameterType);

                    // @todo: Rewrite
                    #if (strpos($argumentType, '[]') !== false || strpos($parameterType, '[]') !== false)
                    #  continue;

                    if (substr($argumentType, -2) == '[]' && in_array($parameterType, ['t_array', 'array']))
                      $isCompatible = true;

                    if (!$useStrictTypeConversion) {
                      if (in_array($argumentType, ['t_int', 't_float']) && $parameterType == 't_string')
                        $argumentType = 't_string';
                      #if ($argumentType == 't_int')
                      #  $argumentType = 't_autoInteger';
                      #if ($argumentType == 't_float')
                      #  $argumentType = 't_autoFloat';
                      #$argumentType = inference\Type::toImplicitType($argumentType);

                      if ($argumentType == 't_autoInteger' && $parameterType == 't_int')
                        $isCompatible = true;

                      if ($argumentType == 't_stringInt' && $parameterType == 't_int')
                        $isCompatible = true;

                      if ($argumentType == 't_autoInteger' && $parameterType == 't_float')
                        $isCompatible = true;

                      if ($argumentType == 't_stringInt' && $parameterType == 't_float')
                        $isCompatible = true;

                      if ($argumentType == 't_autoFloat' && $parameterType == 't_float')
                        $isCompatible = true;

                      if ($argumentType == 't_stringFloat' && $parameterType == 't_float')
                        $isCompatible = true;

                      if ($argumentType == 't_autoBool' && $parameterType == 't_bool')
                        $isCompatible = true;

                      if ($argumentType == 't_stringBool' && $parameterType == 't_bool')
                        $isCompatible = true;

                      if ($argumentType == 't_stringBool' && $parameterType == 't_int')
                        $isCompatible = true;

                      if ($argumentType == 't_stringBool' && $parameterType == 't_float')
                        $isCompatible = true;

                    }

                    #var_dump($useStrictTypeConversion, $argumentType, $parameterType, '---------------------');

                    if ($argumentType == 't_int' && $parameterType == 't_float')
                      $isCompatible = true;

                    if ($argumentType == 't_autoInteger' && $parameterType == 't_string')
                      $isCompatible = true;

                    if ($argumentType == 't_stringInt' && $parameterType == 't_string')
                      $isCompatible = true;

                    if ($argumentType == 't_autoFloat' && $parameterType == 't_string')
                      $isCompatible = true;

                    if ($argumentType == 't_stringFloat' && $parameterType == 't_string')
                      $isCompatible = true;

                    if ($argumentType == 't_stringBool' && $parameterType == 't_string')
                      $isCompatible = true;

                    if ($parameterType == '')
                      $isCompatible = true;

                    if (inference\Type::isImplicitlyConvertible($argumentType, $parameterType)) {
                      $isCompatible = true;
                      break;
                    }

                  }

                  $toGeneralType = function ($type) {
                    if ($type == 'stringFloat')
                      return 'string';
                    if ($type == 'stringInt')
                      return 'string';
                    if ($type == 'stringBool')
                      return 'string';
                    return $type;
                  };

                  if (!$isCompatible)
                    // @todo: Rewrite.
                    if (isset(op\metaContext('code')->data['symbols'][$argumentType]) ? ltrim(op\metaContext('code')->data['symbols'][$argumentType]['phpId'], '\\') : substr($argumentType, 2))
                    if (!in_array((isset(op\metaContext('code')->data['symbols'][$argumentType]) ? ltrim(op\metaContext('code')->data['symbols'][$argumentType]['phpId'], '\\') : substr($argumentType, 2)), ['mixed', 'undefined']))
                    if (substr(isset(op\metaContext('code')->data['symbols'][$argumentType]) ? ltrim(op\metaContext('code')->data['symbols'][$argumentType]['phpId'], '\\') : substr($argumentType, 2), -1) != '\\')
                    op\metaContext('result')->addIssue($node,
                      'Provided ' . NodeConcept::referencePrint($argument) . ' ' .
                      'of type *' . $toGeneralType(isset(op\metaContext('code')->data['symbols'][$argumentType]) ? ltrim(op\metaContext('code')->data['symbols'][$argumentType]['phpId'], '\\') : substr($argumentType, 2)) . '* ' .
                      'is not implicitly convertable to type *' . $toGeneralType(isset(op\metaContext('code')->data['symbols'][$parameterType]) ? ltrim(op\metaContext('code')->data['symbols'][$parameterType]['phpId'], '\\') : substr($parameterType, 2)) . '* '
                      . 'in the ' . NodeConcept::referencePrint($node) . '.'
                    );

                }

              }

              #var_dump(inference\Type::get($argument));
            }
          }



    }

    // prohibitIncompatibleInvocationArguments

  }

}
