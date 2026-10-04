<?php

declare(strict_types=1);

use KB\HostingerRedirects\Map;
use KB\HostingerRedirects\Plan;
use KB\HostingerRedirects\Url;

/**
 * The mu-plugin's two decisions worth testing: which of Redirection's rows a
 * web server may answer, and which server-level redirects a run deletes.
 *
 * The first one, got wrong, sends visitors to the wrong page before PHP can
 * have an opinion. The second one deletes redirects, including -- if the
 * ledger rule were wrong -- ones somebody made by hand in hPanel. Both sides
 * are pure functions over arrays, so they are tested without WordPress; the
 * classes are required directly because mu-plugins are not autoloaded.
 */
require_once __DIR__ . '/../../web/app/mu-plugins/hostinger-redirects/src/Url.php';
require_once __DIR__ . '/../../web/app/mu-plugins/hostinger-redirects/src/Map.php';
require_once __DIR__ . '/../../web/app/mu-plugins/hostinger-redirects/src/Plan.php';

const KB_HOME = 'https://kwiaty-bukiety.com.pl/';

/**
 * One `redirection_items` row, with the columns Source::rows() selects.
 *
 * @return array<string, mixed>
 */
function kbRedirectRow(string $url, string $target, int $code = 301, int $id = 1): array
{
    return [
        'id' => $id,
        'url' => $url,
        'action_data' => $target,
        'action_code' => $code,
        'position' => $id,
    ];
}

it('normalises a source to the one form both sides compare in', function () {
    $host = 'kwiaty-bukiety.com.pl';

    expect(Url::source('/old-page/', $host))->toBe('/old-page');
    expect(Url::source('old-page', $host))->toBe('/old-page');
    expect(Url::source('https://kwiaty-bukiety.com.pl/old-page', $host))->toBe('/old-page');
    expect(Url::source('https://www.kwiaty-bukiety.com.pl/old-page/', $host))->toBe('/old-page');
});

it('refuses a source a web server cannot match on its own', function () {
    $host = 'kwiaty-bukiety.com.pl';

    // A query string: Redirection decides whether it matters, the server cannot.
    expect(Url::source('/old-page?ref=fb', $host))->toBe('');
    // Redirection's wildcard and bare regex metacharacters.
    expect(Url::source('/kwiaty/*', $host))->toBe('');
    expect(Url::source('^/kwiaty/(.*)$', $host))->toBe('');
    // Another site's URL, which this website has no business redirecting.
    expect(Url::source('https://example.com/old-page', $host))->toBe('');
    // The home page: a server-level redirect here would take the site down.
    expect(Url::source('/', $host))->toBe('');
    expect(Url::source('', $host))->toBe('');
});

it('resolves a target to an absolute url', function () {
    expect(Url::target('/bukiety', KB_HOME))->toBe('https://kwiaty-bukiety.com.pl/bukiety');
    expect(Url::target('https://example.com/x', KB_HOME))->toBe('https://example.com/x');
    expect(Url::target('//example.com/x', KB_HOME))->toBe('https://example.com/x');
    // Neither a path nor an http(s) URL.
    expect(Url::target('mailto:shop@example.com', KB_HOME))->toBe('');
    expect(Url::target('bukiety', KB_HOME))->toBe('');
});

it('mirrors a plain 301 and leaves everything else to php', function () {
    $built = Map::build([
        kbRedirectRow('/old-page', '/bukiety', 301, 1),
        kbRedirectRow('/temporary', '/bukiety', 302, 2),
        kbRedirectRow('/kwiaty/*', '/bukiety', 301, 3),
        kbRedirectRow('/gone', 'mailto:shop@example.com', 301, 4),
    ], KB_HOME, [301]);

    expect($built['map'])->toBe(['/old-page' => 'https://kwiaty-bukiety.com.pl/bukiety']);
    expect($built['skipped'])->toHaveCount(3);
});

it('refuses a redirect that would loop with no php left to break it', function () {
    $built = Map::build([
        kbRedirectRow('/bukiety', '/bukiety/', 301, 1),
    ], KB_HOME, [301]);

    expect($built['map'])->toBe([]);
    expect($built['skipped'][0]['reason'])->toBe('the target is the source');
});

it('keeps the row redirection itself would answer with when two share a source', function () {
    // Source::rows() reads in `position` order, which is the order Redirection
    // matches in, so the first row through here is the one that wins.
    $built = Map::build([
        kbRedirectRow('/old-page', '/first', 301, 1),
        kbRedirectRow('/old-page/', '/second', 301, 2),
    ], KB_HOME, [301]);

    expect($built['map'])->toBe(['/old-page' => 'https://kwiaty-bukiety.com.pl/first']);
});

it('creates what hostinger is missing', function () {
    $plan = Plan::build(
        ['/a' => 'https://site/1', '/b' => 'https://site/2'],
        ['/a' => 'https://site/1'],
        ['/a' => 'https://site/1'],
    );

    expect($plan['create'])->toBe(['/b' => 'https://site/2']);
    expect($plan['delete'])->toBe([]);
    expect($plan['unchanged'])->toBe(['/a' => 'https://site/1']);
});

it('replaces one of ours whose target changed', function () {
    // The endpoint has no update, so a changed target is a delete and a create.
    $plan = Plan::build(
        ['/a' => 'https://site/new'],
        ['/a' => 'https://site/old'],
        ['/a' => 'https://site/old'],
    );

    expect($plan['delete'])->toBe(['/a']);
    expect($plan['create'])->toBe(['/a' => 'https://site/new']);
});

it('deletes one of ours that is no longer wanted', function () {
    $plan = Plan::build([], ['/a' => 'https://site/1'], ['/a' => 'https://site/1']);

    expect($plan['delete'])->toBe(['/a']);
    expect($plan['create'])->toBe([]);
});

it('never touches a redirect it did not create', function () {
    // Made by hand in hPanel: not in the ledger, so it is reported and left
    // alone even though Redirection wants the same source elsewhere.
    $plan = Plan::build(
        ['/a' => 'https://site/ours'],
        ['/a' => 'https://site/theirs', '/unrelated' => 'https://site/x'],
        [],
    );

    expect($plan['delete'])->toBe([]);
    expect($plan['create'])->toBe([]);
    expect($plan['conflicts'])->toBe([
        ['from' => '/a', 'ours' => 'https://site/ours', 'theirs' => 'https://site/theirs'],
    ]);
});

it('does not ask to delete what is already gone', function () {
    // Ours by the ledger, unwanted now, but somebody deleted it in hPanel
    // first: there is nothing to send.
    $plan = Plan::build([], [], ['/a' => 'https://site/1']);

    expect($plan['delete'])->toBe([]);
});
