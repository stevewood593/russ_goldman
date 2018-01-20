<?php

class ZMQ
{
    function __construct() {}
}

class ZMQContext
{
    function __construct(integer $io_threads = 1, boolean $is_persistent = true) {}
    function getOpt(string $key) {}
    function getSocket(integer $type, string $persistent_id = null, callback $on_new_socket = null) : ZMQSocket {}
    function isPersistent() : boolean {}
    function setOpt(integer $key, $value) : ZMQContext {}
}

class ZMQDevice
{
    function __construct(ZMQSocket $frontend, ZMQSocket $backend, ZMQSocket $listener = null) {}
    function getIdleTimeout() : ZMQDevice {}
    function getTimerTimeout() : ZMQDevice {}
    function run() {}
    function setIdleCallback(callable $cb_func, integer $timeout, $user_data = null) : ZMQDevice {}
    function setIdleTimeout(integer $timeout) : ZMQDevice {}
    function setTimerCallback(callable $cb_func, integer $timeout, $user_data = null) : ZMQDevice {}
    function setTimerTimeout(integer $timeout) : ZMQDevice {}
}

class ZMQPoll
{
    function add($entry, integer $type) : string {}
    function clear() : ZMQPoll {}
    function count() : integer {}
    function getLastErrors() : array {}
    function poll(array &$readable, array &$writable, integer $timeout = -1) : integer {}
    function remove($item) : boolean {}
}

class ZMQSocket
{
    function __construct(ZMQContext $context, int $type, string $persistent_id = null, callback $on_new_socket = null) {}
    function bind(string $dsn, boolean $force = false) : ZMQSocket {}
    function connect(string $dsn, boolean $force = false) : ZMQSocket {}
    function disconnect(string $dsn) : ZMQSocket {}
    function getEndpoints() : array {}
    function getPersistentId() : string {}
    function getSocketType() : integer {}
    function getSockOpt(string $key) {}
    function isPersistent() : boolean {}
    function recv(integer $mode = 0) : string {}
    function recvMulti(integer $mode = 0) : string {}
    function send(array $message, integer $mode = 0) : ZMQSocket {}
    function setSockOpt(integer $key, $value) : ZMQSocket {}
    function unbind(string $dsn) : ZMQSocket {}
}
