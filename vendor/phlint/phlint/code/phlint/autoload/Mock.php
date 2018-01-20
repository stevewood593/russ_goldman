<?php

namespace phlint\autoload;

use \luka8088\ExtensionCall;
use \luka8088\phops as op;

class Mock {

  function __construct ($data) {
    $this->data = $data;
  }

  /** @ExtensionCall("phlint.phpAutoloadClass") */
  function __invoke ($class) {
    if (isset($this->data[$class]))
      op\metaContext('code')->load([[
        'path' => 'mock:' . $class,
        'source' => $this->data[$class],
        'isLibrary' => true,
      ]]);
  }

}
