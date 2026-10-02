<?php

declare(strict_types=1);

namespace App\SEO;

/**
 * Lets the category descriptions run shortcodes (production snippet #46).
 *
 * The descriptions are ~6 KB of editor-managed SEO copy and several of them
 * embed [kb_kwiaciarnie_pl]. Snippet #47 was a second copy of the same filter
 * at a different priority; only one is migrated.
 */
final class TermShortcodes
{
    public static function boot(): void
    {
        add_filter('term_description', 'do_shortcode', 11);
        add_filter('woocommerce_archive_description', 'do_shortcode', 11);
    }
}
