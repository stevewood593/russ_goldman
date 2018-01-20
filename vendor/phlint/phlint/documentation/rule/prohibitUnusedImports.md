Prohibit Unused Imports
=======================

Importing is PHP language feature described in [http://php.net/manual/en/language.namespaces.importing.php](http://php.net/manual/en/language.namespaces.importing.php).
This rule prohibits import (use) statements that are not being used. Having import statements that are not being used
has no negative functional side effects - the only negative side effect is having a piece of code that is not being
used which might be misleading for a programmer who might think that it is used.

Example:

    use \B;
    // Phlint issue: Import *B* is not used.

    class A {}

    $x = new A();

Rule configuration:

    // To enable this rule:
    $phlint->addRule('prohibitUnusedImports');

    // To disable this rule:
    $phlint->removeRule('prohibitUnusedImports');

Rule source code: [/code/phlint/rule/ProhibitUnusedImports.php](/code/phlint/rule/ProhibitUnusedImports.php)
