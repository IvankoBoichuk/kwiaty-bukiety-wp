<?php

/**
 * Configuration overrides for WP_ENV === 'local'
 *
 * Without this file a local checkout using WP_ENV=local silently fell through
 * to the production defaults: no debugging, and DISALLOW_INDEXING never set.
 */

use Roots\WPConfig\Config;

use function Env\env;

Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', true);
Config::define('WP_DEBUG_LOG', env('WP_DEBUG_LOG') ?? true);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('DISALLOW_INDEXING', true);
Config::define('WP_MEMORY_LIMIT', -1);

ini_set('display_errors', '1');

// Allow installing plugins and themes from the admin while developing.
Config::define('DISALLOW_FILE_MODS', false);
