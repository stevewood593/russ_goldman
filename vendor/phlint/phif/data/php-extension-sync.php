<?php

class SyncEvent
{
    function __construct(string $name = '', bool $manual = false, bool $prefire = false) {}
    function fire() : bool {}
    function reset() : bool {}
    function wait(integer $wait = -1) : bool {}
}

class SyncMutex
{
    function __construct(string $name = '') {}
    function lock(integer $wait = -1) : bool {}
    function unlock(bool $all = false) : bool {}
}

class SyncReaderWriter
{
    function __construct(string $name = '', bool $autounlock = true) {}
    function readlock(integer $wait = -1) : bool {}
    function readunlock() : bool {}
    function writelock(integer $wait = -1) : bool {}
    function writeunlock() : bool {}
}

class SyncSemaphore
{
    function __construct(string $name = '', integer $initialval = 1, bool $autounlock = true) {}
    function lock(integer $wait = -1) : bool {}
    function unlock(integer &$prevcount = 0) : bool {}
}

class SyncSharedMemory
{
    function __construct(string $name, integer $size) {}
    function first() : bool {}
    function read(integer $start = 0, integer $length = 0) {}
    function size() : bool {}
    function write(string $string = '', integer $start = 0) {}
}
