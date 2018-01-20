<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\inference;
use \phlint\NodeConcept;
use \phlint\phpLanguage;
use \PhpParser\Comment;
use \PhpParser\Node;

/**
 * @see /documentation/constraint/index.md
 */
class Constraint {

  function getIdentifier () {
    return 'constraint';
  }

  function getPass () {
    return 30;
  }

  function visitNode ($node) {

    if (NodeConcept::isExecutionContextNode($node)) {

      $constraints = [];

      foreach ($this->processConstraints($node) as $constraint)
        $constraints[] = $constraint;

      foreach ($node->params as $parameter)
        foreach ($this->processConstraints($parameter) as $constraint)
          $constraints[] = $constraint;

      if ($node->returnType instanceof Node)
        foreach ($this->processConstraints($node->returnType) as $constraint)
          $constraints[] = $constraint;

      $node->stmts = array_merge($constraints, $node->stmts === null ? [] : $node->stmts);

    }

  }

  function processConstraints ($node) {

    $constraints = [];

    foreach (inference\Attribute::get($node) as $attribute) {

      if ($attribute instanceof Node\Expr\New_ &&
          count($attribute->args) >= 2 &&
          inference\Value::get($attribute->args[0]) == [['type' => 't_string', 'value' => 'param']]) {

        if (count($attribute->args[1]->value->items) < 2)
          continue;

        $expressionConstraint = phpLanguage\PhpDocumentor::expressionConstraint(
          $attribute->args[1]->value->items[0]->value->value,
          $attribute->args[1]->value->items[1]->value->value
        );

        if (!$expressionConstraint)
          continue;

        try {
          $constraint = op\metaContext('code')->parse('<?php assert(' . $expressionConstraint . ');');
        } catch (\PhpParser\Error $exception) {

          // @todo: Handle and remove.
          continue;

        }

        assert(is_array($constraint) && count($constraint) == 1);
        $constraint = $constraint[0];

        $constraint->setAttribute('isGenerated', true);
        $constraint->setAttribute('isConstraint', true);

        $constraint->setAttribute('displayPrint', $attribute->getAttribute('displayPrint'));

        $constraint->setAttribute('constructTypeName', 'constraint');

        if ($attribute->getAttribute('path', ''))
          $constraint->setAttribute('path', $attribute->getAttribute('path', ''));

        $constraint->setAttribute('startLine', $attribute->getAttribute('startLine'));
        $constraint->setAttribute('endLine', $attribute->getAttribute('endLine'));

        $constraints[] = $constraint;

      }

    }

    $unwrap = function ($comment) {
      if (strpos(ltrim($comment), '//') === 0)
        return substr(ltrim($comment), 2);
      if (strpos(ltrim($comment), '/**') === 0)
        return preg_replace('/(?is)(\A\/\*\*|(?<=\n)[ \t]*\*(?!\/\z)|\*\/\z)/', '', $comment);
      return $comment;
    };

    $toString = function ($comment) {
      if ($comment instanceof Comment) {
        return $comment->getText();
      }
      return $comment;
    };

    foreach ($node->getAttribute('comments', []) as $commentNode) {

      $commentLine = is_object($commentNode) ? $commentNode->getLine() : 0;

      $comment = $unwrap($toString($commentNode));

      preg_match_all('/
        (?is)
        (?<![a-z0-9_\-])@([a-z0-9_\-]*)
        \s*
        ((
          \(
            [^\(\)\@]*(?-1)?
          \)?
          |
          \)
        )*)
      /x', $comment, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

      foreach ($matches as $match) {

        if ($match[1][0] != 'constraint')
          continue;

        try {
          $constraint = op\metaContext('code')->parse('<?php assert' . $match[2][0] . ';');
        } catch (\PhpParser\Error $exception) {
          op\metaContext('result')->addIssue(null, 'Parse error: ' . $exception->getMessage()
            . ($node->getAttribute('path', '') ? ' in *' . realpath($node->getAttribute('path', '')) . '*.' : '.')
          );
          continue;
        }

        assert(is_array($constraint) && count($constraint) == 1);
        $constraint = $constraint[0];

        $constraint->setAttribute('isGenerated', true);
        $constraint->setAttribute('isConstraint', true);

        $constraint->setAttribute('displayPrint', $match[0][0]);

        $constraint->setAttribute('constructTypeName', 'constraint');

        if ($node->getAttribute('path', ''))
          $constraint->setAttribute('path', $node->getAttribute('path', ''));

        $constraint->setAttribute(
          'startLine',
          $commentLine + count(explode("\n", substr($comment, 0, $match[2][1]))) - 1
        );
        $constraint->setAttribute(
          'endLine',
          $constraint->getAttribute('startLine') + $constraint->getAttribute('endLine') - 1
        );

        $constraints[] = $constraint;

      }

    }

    return $constraints;

  }

}
