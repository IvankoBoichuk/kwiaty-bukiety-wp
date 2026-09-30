<?php

/**
 * Configuration overrides for WP_ENV === 'production'
 */

use Roots\WPConfig\Config;

use function Env\env;

/**
 * Debug output must never reach a visitor. The base config already sets these,
 * but define them here too so an inherited constant cannot flip them.
 */
Config::define('WP_DEBUG', false);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('SAVEQUERIES', false);

/**
 * Keep the admin and login form on HTTPS. config/application.php already
 * honours X-Forwarded-Proto, so this works behind the host's proxy.
 */
Config::define('FORCE_SSL_ADMIN', true);

/**
 * WP-Cron on every front-end request both slows TTFB and makes WooCommerce's
 * scheduled actions unreliable under load. Disable it and drive wp-cron.php
 * from a real system cron on the server.
 */
Config::define('DISABLE_WP_CRON', env('DISABLE_WP_CRON') ?? true);

/**
 * Unbounded revisions bloat wp_posts; the homepage alone had accumulated more
 * than thirty before this was capped.
 */
Config::define('WP_POST_REVISIONS', env('WP_POST_REVISIONS') ?? 10);
