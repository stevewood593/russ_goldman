<?php

class Yar_Client
{
    function __call(string $method, array $parameters) {}
    function __construct(string $url) {}
    function setOpt($name, $value) : boolean {}
}

class Yar_Client_Exception extends Exception
{
    function getType() {}
}

class Yar_Concurrent_Client
{
    function call(string $uri, string $method, array $parameters, callable $callback = null) : int {}
    function loop(callable $callback = null, callable $error_callback = null) : boolean {}
    function reset() : boolean {}
}

class Yar_Server
{
    function __construct(Object $obj) {}
    function handle() : boolean {}
}

class Yar_Server_Exception extends Exception
{
    function getType() : string {}
}
