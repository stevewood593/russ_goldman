<?php

/**
 * @param string $old_file
 * @param string $new_file
 * @param string $dest
 * @return bool
 */
function xdiff_file_bdiff(string $old_file, string $new_file, string $dest) : bool {}

/**
 * @param string $file
 * @return int
 */
function xdiff_file_bdiff_size(string $file) : int {}

/**
 * @param string $file
 * @param string $patch
 * @param string $dest
 * @return bool
 */
function xdiff_file_bpatch(string $file, string $patch, string $dest) : bool {}

/**
 * @param string $old_file
 * @param string $new_file
 * @param string $dest
 * @param int $context
 * @param bool $minimal
 * @return bool
 */
function xdiff_file_diff(string $old_file, string $new_file, string $dest, int $context = 3, bool $minimal = false) : bool {}

/**
 * @param string $old_file
 * @param string $new_file
 * @param string $dest
 * @return bool
 */
function xdiff_file_diff_binary(string $old_file, string $new_file, string $dest) : bool {}

/**
 * @param string $old_file
 * @param string $new_file1
 * @param string $new_file2
 * @param string $dest
 * @return mixed
 */
function xdiff_file_merge3(string $old_file, string $new_file1, string $new_file2, string $dest) {}

/**
 * @param string $file
 * @param string $patch
 * @param string $dest
 * @param int $flags
 * @return mixed
 */
function xdiff_file_patch(string $file, string $patch, string $dest, int $flags = DIFF_PATCH_NORMAL) {}

/**
 * @param string $file
 * @param string $patch
 * @param string $dest
 * @return bool
 */
function xdiff_file_patch_binary(string $file, string $patch, string $dest) : bool {}

/**
 * @param string $old_file
 * @param string $new_file
 * @param string $dest
 * @return bool
 */
function xdiff_file_rabdiff(string $old_file, string $new_file, string $dest) : bool {}

/**
 * @param string $old_data
 * @param string $new_data
 * @return string
 */
function xdiff_string_bdiff(string $old_data, string $new_data) : string {}

/**
 * @param string $patch
 * @return int
 */
function xdiff_string_bdiff_size(string $patch) : int {}

/**
 * @param string $str
 * @param string $patch
 * @return string
 */
function xdiff_string_bpatch(string $str, string $patch) : string {}

/**
 * @param string $old_data
 * @param string $new_data
 * @param int $context
 * @param bool $minimal
 * @return string
 */
function xdiff_string_diff(string $old_data, string $new_data, int $context = 3, bool $minimal = false) : string {}

/**
 * @param string $old_data
 * @param string $new_data1
 * @param string $new_data2
 * @param string $error
 * @return mixed
 */
function xdiff_string_merge3(string $old_data, string $new_data1, string $new_data2, string &$error = '') {}

/**
 * @param string $str
 * @param string $patch
 * @param int $flags
 * @param string $error
 * @return string
 */
function xdiff_string_patch(string $str, string $patch, int $flags = 0, string &$error = '') : string {}

/**
 * @param string $str
 * @param string $patch
 * @return string
 */
function xdiff_string_patch_binary(string $str, string $patch) : string {}
