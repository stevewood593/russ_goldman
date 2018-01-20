<?php

class Collectable
{
    function isGarbage() : bool {}
    function setGarbage() {}
}

class Cond
{
    function broadcast(long $condition) : boolean {}
    function create() : long {}
    function destroy(long $condition) : boolean {}
    function signal(long $condition) : boolean {}
    function wait(long $condition, long $mutex, long $timeout = null) : boolean {}
}

class Mutex
{
    function create(boolean $lock = false) : long {}
    function destroy(long $mutex) : boolean {}
    function lock(long $mutex) : boolean {}
    function trylock(long $mutex) : boolean {}
    function unlock(long $mutex, boolean $destroy = false) : boolean {}
}

class Pool
{
    function __construct(integer $size, string $class = '', array $ctor = []) : Pool {}
    function collect(Callable $collector = null) : int {}
    function resize(integer $size) {}
    function shutdown() {}
    function submit(Threaded $task) : int {}
    function submitTo(int $worker, Threaded $task) : int {}
}

class Thread extends Threaded implements Countable, Traversable, ArrayAccess
{
    function detach() {}
    function getCreatorId() : integer {}
    function getCurrentThread() : Thread {}
    function getCurrentThreadId() : integer {}
    function getThreadId() : integer {}
    function globally() {}
    function isJoined() : boolean {}
    function isStarted() : boolean {}
    function join() : boolean {}
    function kill() {}
    function start(integer $options = 0) : boolean {}
}

class Threaded implements Collectable, Traversable, Countable, ArrayAccess
{
    function chunk(integer $size, boolean $preserve) : array {}
    function count() : integer {}
    function extend(string $class) : bool {}
    function from(Closure $run, Closure $construct = null, array $args = []) : Threaded {}
    function getTerminationInfo() : array {}
    function isRunning() : boolean {}
    function isTerminated() : boolean {}
    function isWaiting() : boolean {}
    function lock() : boolean {}
    function merge($from, bool $overwrite = false) : boolean {}
    function notify() : boolean {}
    function notifyOne() : boolean {}
    function pop() : boolean {}
    function run() {}
    function shift() {}
    function synchronized(Closure $block, ...$__variadic) {}
    function unlock() : boolean {}
    function wait(integer $timeout = 0) : boolean {}
}

class Volatile extends Threaded implements Collectable, Traversable {}

class Worker extends Thread implements Traversable, Countable, ArrayAccess
{
    function collect(Callable $collector = null) : int {}
    function getStacked() : int {}
    function isShutdown() : bool {}
    function isWorking() : boolean {}
    function shutdown() : bool {}
    function stack(Threaded &$work) : int {}
    function unstack() : int {}
}
