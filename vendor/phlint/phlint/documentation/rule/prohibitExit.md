Prohibit Exit
=============

`exit` in PHP is a language construct described in
[http://php.net/manual/en/function.exit.php](http://php.net/manual/en/function.exit.php).
It immediately halts the script not rolling up the execution stack. It has some use cases in very specific edge cases
but in general using it might be regarded as a bad practice. This rule prohibits usage of `exit` or its alias `die`.

Example:

    function foo () {
      exit;
      // Phlint issue: Using *exit();* is prohibited.
    }

    try {
      doSomething();
    } finally {
      // This line will never be executed.
      // Using `throw` instead of `exit` might be a better idea.
    }

Rule configuration:

    // To enable this rule:
    $phlint->addRule('prohibitExit');

    // To disable this rule:
    $phlint->removeRule('prohibitExit');

Rule source code: [/code/phlint/rule/ProhibitExit.php](/code/phlint/rule/ProhibitExit.php)
