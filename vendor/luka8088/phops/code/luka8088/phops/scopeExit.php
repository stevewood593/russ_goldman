<?php

namespace luka8088\phops;

use \RuntimeException;

/**
 * Provides a way to tie a resource lifetime to a current execution scope.
 * The aim is to prove a way to achieve functionality described
 * on http://dlang.org/statement.html#ScopeGuardStatement
 *
 * Example usage:
 *
 *   function foo () {
 *     $_byeBye = scopeExit(function () { echo "Bye-bye.\n"; });
 *     echo "Hello world\n";
 *   }
 *
 * Returns an object which will execute the provided callable once destructed
 * and if assigned to a scope variable it will get garbage collected at
 * the end of scope. Assigning to a scoped variable is necessary as otherwise
 * the destruction happens immediately. Also on the other hand if there are
 * any other references created destruction will be deferred after the end
 * of scope.
 */
function scopeExit ($callable) {
  return new ScopeExit($callable);
}

/**
 * @internal
 */
class ScopeExit {

    /** @var callable */
    protected $callable;

    function __construct ($callable) {
      $this->callable = $callable;
    }

    function __destruct () {
      if (!$this->callable)
        throw new \RuntimeException('Callable garbage collected before executed.');
      call_user_func($this->callable);
    }

}
