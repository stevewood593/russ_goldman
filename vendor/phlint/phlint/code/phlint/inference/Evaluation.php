<?php

namespace phlint\inference;

use \luka8088\ExtensionInterface;
use \luka8088\phops as op;
use \phlint\IIData;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \PhpParser\Node;

class Evaluation {

  /**
   * Analyzes the code and infers the nodes that it would yield if it would
   * be evaluated.
   */
  static function evaluate ($node) {
    if (!isset(op\metaContext(IIData::class)['nodeEvaluationYield:' . spl_object_hash($node)]))
      op\metaContext(IIData::class)['nodeEvaluationYield:' . spl_object_hash($node)] = self::_evaluate($node);
    return op\metaContext(IIData::class)['nodeEvaluationYield:' . spl_object_hash($node)];
  }

  /** @internal */
  static function _evaluate ($node) {

    if ($node instanceof Node\Arg)
      return Evaluation::evaluate($node->value);

    if ($node instanceof Node\Expr\Ternary) {
      if (inference\Value::isTrue($node->cond))
        return [$node->if];
      if (inference\Value::isFalse($node->cond))
        return [$node->else];
    }

    /** @see http://www.php.net/manual/en/function.is-numeric.php */
    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_is_numeric'])
      return array_map(function ($value) {
        return new Node\Expr\ConstFetch(new Node\Name(is_numeric($value['value']) ? 'true' : 'false'));
      }, inference\Value::get(count($node->args) > 0 ? $node->args[0] : []));

    /** @see http://www.php.net/manual/en/function.is-object.php */
    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_is_object'])
      return array_map(function ($type) {
        return new Node\Expr\ConstFetch(new Node\Name(
          strpos(substr('.' . $type, strrpos('.' . $type, '.') + 1), 'c_') === 0
          ? 'true'
          : 'false'
        ));
      }, inference\Type::get(count($node->args) > 0 ? $node->args[0] : []));

    /** @see http://www.php.net/manual/en/function.rand.php */
    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_rand']) {
      return [new Node\Scalar\LNumber(0)];
    }

    /** @see http://www.php.net/manual/en/function.str-replace.php */
    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_str_replace']) {
      $resultNodes = [];
      foreach (inference\Value::get(count($node->args) > 0 ? $node->args[0] : []) as $searchValue)
        foreach (inference\Value::get(count($node->args) > 1 ? $node->args[1] : []) as $replaceValue)
          foreach (inference\Value::get(count($node->args) > 2 ? $node->args[2] : []) as $subjectValue)
            $resultNodes[] = new Node\Scalar\String_(
              str_replace($searchValue['value'], $replaceValue['value'], $subjectValue['value'])
            );
      return $resultNodes;
    }

    /** @see http://php.net/manual/en/function.substr.php */
    if (NodeConcept::isInvocationNode($node) && inference\SymbolLink::getUnmangled($node) == ['f_substr']) {
      $resultNodes = [];
      foreach (inference\Value::get(count($node->args) > 0 ? $node->args[0] : []) as $stringValue)
        foreach (inference\Value::get(count($node->args) > 1 ? $node->args[1] : []) as $startValue)
          foreach (inference\Value::get(count($node->args) > 2 ? $node->args[2] : []) as $lengthValue)
            $resultNodes[] = new Node\Scalar\String_(
              substr($stringValue['value'], $startValue['value'], $lengthValue['value'])
            );
      return $resultNodes;
    }

    // @todo: Call only if compatible.
    if (false)
    if (NodeConcept::isInvocationNode($node)) {
      $symbols = inference\SymbolLink::getUnmangled($node);
      if (count($symbols) == 1 && substr($symbols[0], 0, 2) == 'f_') {
        $functionName = substr($symbols[0], 2);
        if (in_array($functionName, phpLanguage\Fixture::$isolatedPhpFunctions)
            || in_array($functionName, phpLanguage\Fixture::$purePhpFunctions)) {
          $generateValueArguments = function ($node, $arguments = []) use (&$generateValueArguments) {
            if (count($arguments) < count($node->args)) {
              foreach (inference\Value::get($node->args[count($arguments)]) as $value)
                foreach ($generateValueArguments($node, array_merge($arguments, [$value['value']])) as $result)
                  yield $result;
              return;
            }
            yield $arguments;
          };
          foreach ($generateValueArguments($node) as $arguments) {
            $returnValue = call_user_func_array($functionName, $arguments);
            return [\phlint\Code::parse('<?php ' . var_export($returnValue, true) . ';')[0]];
          }
        }
      }
    }

    return [$node];

  }

}
