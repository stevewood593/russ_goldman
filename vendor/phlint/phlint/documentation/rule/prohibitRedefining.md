Prohibit Redefining
===================

This rule prohibits redefining of function, classes, traits, etc. Redefining is prohibited in PHP and causes a
runtime fatal error.

For example:

    function x () {}
    function x () {}
    // PHP Fatal error: Cannot redeclare x() (previously declared on line 1) on line 2
    // Phlint issue: Having multiple definitions for *x* is prohibited.

Rule configuration:

    // To enable this rule:
    $phlint->addRule('prohibitRedefining');

    // To disable this rule:
    $phlint->removeRule('prohibitRedefining');

Rule source code: [/code/phlint/rule/ProhibitRedefining.php](/code/phlint/rule/ProhibitRedefining.php)
