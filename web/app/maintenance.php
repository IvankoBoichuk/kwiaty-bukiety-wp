<?php

/**
 * Maintenance drop-in, served for the whole deploy window.
 *
 * wp_maintenance() loads this the moment ABSPATH/.maintenance exists -- which
 * .woodpecker/ci.yaml creates right before rsync and removes right after --
 * and die()s on return. Without it WP falls back to wp_die(), which means an
 * untranslated English wall of text; the shop is Polish.
 *
 * This runs from wp-settings.php before plugins, the theme or even
 * wp_load_translations_early(), so nothing here may touch a WP function or
 * load an external asset: it is plain PHP, inline CSS, no requests.
 *
 * Unlike wp_die() the drop-in owns its own response, so the status code and
 * every header below has to be set by hand.
 */

if (! headers_sent()) {
    header('Content-Type: text/html; charset=utf-8', true, 503);

    // Deploys finish in well under a minute; the window WP itself allows before
    // it ignores .maintenance is 10 minutes.
    header('Retry-After: 120');

    // LiteSpeed serves stored HTML without ever reaching PHP, so a cached 503
    // would outlive the deploy by the whole TTL. Its own header is the only one
    // it honours at this stage.
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-LiteSpeed-Cache-Control: no-cache');
    header('X-Robots-Tag: noindex, nofollow');
}

?>
<!DOCTYPE html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <!-- Visitors who land mid-deploy get the real site back without touching anything. -->
  <meta http-equiv="refresh" content="30">
  <title>Przerwa techniczna &mdash; Kwiaty Bukiety</title>
  <style>
    :root {
      --green-default: #0c4a2c;
      --green-easy: #6d9586;
      --background: #fcf9f6;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
      background: var(--background);
      color: var(--green-default);
      /* The theme self-hosts Cormorant Garamond and Raleway through
         vite-plugin-webfont-dl, under hashed filenames this page cannot resolve
         without the manifest -- so it stays on the system stack. */
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      font-size: 16px;
      line-height: 1.6;
      text-align: center;
    }

    main { max-width: 34rem; }

    .mark {
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(2.25rem, 8vw, 3.5rem);
      font-weight: 300;
      line-height: 1.1;
      margin: 0 0 1.5rem;
    }

    h1 {
      font-family: Georgia, "Times New Roman", serif;
      font-size: clamp(1.5rem, 5vw, 2rem);
      font-weight: 400;
      margin: 0 0 1rem;
    }

    p {
      margin: 0 0 0.75rem;
      color: var(--green-easy);
    }

    .rule {
      width: 3rem;
      height: 2px;
      margin: 2rem auto 0;
      border: 0;
      background: var(--green-easy);
      opacity: 0.5;
    }
  </style>
</head>
<body>
  <main>
    <p class="mark">Kwiaty&nbsp;Bukiety</p>

    <h1>Chwilowa przerwa techniczna</h1>

    <p>Aktualizujemy sklep. Wracamy w ciągu kilku minut.</p>
    <p>Ta strona odświeży się sama &mdash; nie trzeba nic robić.</p>

    <hr class="rule">
  </main>
</body>
</html>
