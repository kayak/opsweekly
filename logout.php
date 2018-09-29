<?php
error_reporting(E_ALL ^ E_NOTICE);

if (!file_exists('phplib/config.php')) {
    die('Cannot find config.php! It must be in phplib and named config.php');
}

require_once 'phplib/auth.php';
require_once 'phplib/config.php';

$authenticated = check_authentication($auth);

if (!$authenticated) {
  header("Location: ".$ROOT_URL);
}

if (function_exists(logout)) {
  logout();
}