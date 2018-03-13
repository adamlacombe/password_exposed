<?php

use DivineOmega\PasswordExposed\PasswordExposedChecker;

/**
 * Just a quick hack
 * Silence error on Windows - rapidwebltd/rw-file-cache-psr-6 RWFileCache Line 124
 */
if( !function_exists('sys_getloadavg')) {
	function sys_getloadavg() {}
}

if (!function_exists('password_exposed')) {
    function password_exposed($password, $cacheConfig=[], $clientConfig=[])
    {
        return (new PasswordExposedChecker($cacheConfig, $clientConfig))->passwordExposed($password);
    }
}
