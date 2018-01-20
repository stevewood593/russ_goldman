<?php

namespace phif\patch;

use \phif\NodeFacade;
use \phif\Parser;
use \PhpParser\Node;

class Logic {

  static function patch ($blueprint) {

    $blueprint['f_date.s_default'] = Parser::parse('<?php
      return str_replace("Y", rand(0, 9) . rand(0, 9) . rand(0, 9) . rand(0, 9), $format);
    ')[0];

    // @todo: Implement and enable.
    #$blueprint['f_implode.s_default'] = Parser::parse('<?php
    #  assert((is_string($glue) && is_array($pieces)) || (is_array($glue) && is_string($pieces)));
    #')[0];

    static $usingSortLocaleConstant = [
      'f_array_multisort',
      'f_arsort',
      'f_asort',
      'f_krsort',
      'f_ksort',
      'f_rsort',
      'f_sort',
    ];

    foreach ($usingSortLocaleConstant as $symbol) {
      $blueprint[$symbol . '.s_sortLocaleIsolationBreach'] = Parser::parse('<?php
        if ($sort_flags === SORT_LOCALE_STRING) {
          /** @__isolationBreach(\'Depends on the current locale.\') */
        }
      ')[0];
    }

    $blueprint['f_array_diff.a_isolated'] = 'Invokes `__toString` on array values if they are objects.';

    $blueprint['f_setlocale.s_isolationBreach'] = Parser::parse('<?php
      /** @__isolationBreach(\'Modifies global state.\') */
    ')[0];

  }

}
