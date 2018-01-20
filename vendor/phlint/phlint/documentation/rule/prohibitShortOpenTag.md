Prohibit Exit
=============

`short_open_tag` in PHP is an ini directive described at
[http://www.php.net/manual/en/ini.core.php#ini.short-open-tag](http://www.php.net/manual/en/ini.core.php#ini.short-open-tag).
This rule prohibits usage of `short_open_tag`.

Example:

    <?

    echo 'Hello world';

Rule configuration:

    // To enable this rule:
    $phlint->enableRule('prohibitShortOpenTag');

    // To disable this rule:
    $phlint->disableRule('prohibitShortOpenTag');

Rule source code: [/code/phlint/rule/ProhibitShortOpenTag.php](/code/phlint/rule/ProhibitShortOpenTag.php)
