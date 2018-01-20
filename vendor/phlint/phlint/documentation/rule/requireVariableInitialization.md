Require Variable Initialization
===============================

Accessing an undefined variable (a variable that has not been initialized yet) causes different side effects in PHP.
In some cases it has no negative functional side effects (for example if a variables is being compared) and
in other cases it might cause a fatal (for example if a method is being invoked on an undefined variable).

This rule requires that variables are initialized (and thus implicitly defined) before they are used.

Example:

    $a = $b;
    // PHP Notice: Undefined variable: b
    // Phlint issue: Variable *$b* used before initialized.

    $a->foo();
    // PHP Notice: Undefined variable: a
    // PHP Fatal error: Uncaught Error: Call to a member function foo() on null
    // Phlint issue: Variable *$a* used before initialized.

Rule configuration:

    // To enable this rule:
    $phlint->addRule('requireVariableInitialization');

    // To disable this rule:
    $phlint->removeRule('requireVariableInitialization');

Rule source code: [/code/phlint/rule/RequireVariableInitialization.php](/code/phlint/rule/RequireVariableInitialization.php)
