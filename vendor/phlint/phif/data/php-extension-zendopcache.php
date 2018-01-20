<?php

/**
 * @param string $file
 * @return boolean
 */
function opcache_compile_file(string $file) : boolean {}

/**
 * @return array
 */
function opcache_get_configuration() : array {}

/**
 * @param boolean $get_scripts
 * @return array
 */
function opcache_get_status(boolean $get_scripts = true) : array {}

/**
 * @param string $script
 * @param boolean $force
 * @return boolean
 */
function opcache_invalidate(string $script, boolean $force = false) : boolean {}

/**
 * @param string $file
 * @return boolean
 */
function opcache_is_script_cached(string $file) : boolean {}

/**
 * @return boolean
 */
function opcache_reset() : boolean {}
