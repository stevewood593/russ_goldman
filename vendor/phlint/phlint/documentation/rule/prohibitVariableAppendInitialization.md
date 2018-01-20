Prohibit Variable Append Initialization
=======================================

Accessing an undefined variable (a variable that has not been initialized yet) is covered in
[Require Variable Initialization](/documentation/rule/requireVariableInitialization.md) rule.

Append initialization occurs when an append operator is used on against an uninitialized variable.
Strictly speaking append operators require something (already initialized) to append to, and for
that reason using append operator to initialize a variable might be viewed as trying to append to an
uninitialized variable. However this construct is quite common in PHP and the fact that the variable
is being initialized in this way has no negative side effects. For this reason this rule does not point
out broken code but rather a potentially undesired coding style.

Example:

    $a[] = 2;
    // Phlint issue: Variable *$a* initialized using append operator.

Rule configuration:

    // To enable this rule:
    $phlint->addRule('prohibitVariableAppendInitialization');

    // To disable this rule:
    $phlint->removeRule('prohibitVariableAppendInitialization');

Rule source code: [/code/phlint/rule/ProhibitVariableAppendInitialization.php](/code/phlint/rule/ProhibitVariableAppendInitialization.php)
