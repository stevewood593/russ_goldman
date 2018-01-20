<?php

namespace phlint\inference;

use \luka8088\phops as op;
use \phlint\inference;
use \PhpParser\Node;

class Include_ {

  function getIdentifier () {
    return 'include';
  }

  function getPass () {
    return 30;
  }

  protected $extensionInterface = null;

  function setExtensionInterface ($extensionInterface) {
    $this->extensionInterface = $extensionInterface;
  }

  function visitNode ($node) {

    if ($node instanceof Node\Expr\Include_)
      foreach (inference\Value::get($node->expr) as $value) {

        $path = $value['value'];

        if (!is_file($path))
          continue;

        $isLibrary = true;

        foreach (op\metaContext('code')->acode as $acode) {
          if (strpos(str_replace('\\', '/', $path . '/'), str_replace('\\', '/', $acode['path'] . '/')) === 0)
            $isLibrary = false;
          if (strpos(str_replace('\\', '/', realpath($path) . '/'), str_replace('\\', '/', realpath($acode['path']) . '/')) === 0)
            $isLibrary = false;
        }

        op\metaContext('code')->load([[
          'path' => $path,
          'source' => '',
          'isLibrary' => $isLibrary,
        ]]);

      }

  }

}
