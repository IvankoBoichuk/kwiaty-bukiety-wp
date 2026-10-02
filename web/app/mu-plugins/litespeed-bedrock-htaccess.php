<?php

/**
 * Plugin Name: LiteSpeed Cache – Bedrock .htaccess path
 * Description: Points LiteSpeed Cache to web/.htaccess (Bedrock docroot) instead of web/wp/.htaccess.
 */

add_filter('litespeed_frontend_htaccess', fn() => dirname(ABSPATH) . '/.htaccess');
