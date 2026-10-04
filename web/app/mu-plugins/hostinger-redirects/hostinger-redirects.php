<?php

/**
 * Plugin Name: Hostinger Redirects
 * Description: Mirrors the plain 301s kept in Redirection into Hostinger's server-level redirects, so the common ones are answered before PHP boots. Redirection stays in charge of everything the server cannot express.
 * Author: Kwiaty Bukiety
 *
 * The two halves work together rather than competing:
 *
 * - Redirection remains the single place a redirect is written. Nothing here
 *   edits its tables, and switching this plugin off leaves the site exactly as
 *   it was -- every redirect still resolves, just in PHP.
 * - Hostinger answers the subset a web server can answer on its own: an
 *   enabled, non-regex, 301 redirect from a plain path to a plain URL. Those
 *   never reach WordPress again, which is the whole point -- a 404 hunt or a
 *   migration's worth of old URLs stops costing a full WordPress boot each.
 *
 * Anything else -- regex, query-string matching, 302/307, login/role/agent
 * conditions, pass-through and error actions -- is left to Redirection, and
 * still works because PHP runs for every request the server did not already
 * answer.
 *
 * @see https://docs.hostinger.com/api-reference/endpoints/hosting/redirects
 */

declare(strict_types=1);

namespace KB\HostingerRedirects;

if (! defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/Config.php';
require_once __DIR__ . '/src/Url.php';
require_once __DIR__ . '/src/Map.php';
require_once __DIR__ . '/src/Plan.php';
require_once __DIR__ . '/src/Client.php';
require_once __DIR__ . '/src/Source.php';
require_once __DIR__ . '/src/Sync.php';
require_once __DIR__ . '/src/AdminPage.php';
require_once __DIR__ . '/src/Cli.php';

Sync::boot();
AdminPage::boot();
Cli::boot();
