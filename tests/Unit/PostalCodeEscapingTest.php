<?php

declare(strict_types=1);

use App\Api\PostalCode;

/**
 * The settlement lookup is public and runs a prefix LIKE over ~118k rows on
 * every keystroke of the checkout autocomplete, so an unescaped wildcard turns
 * it into a full table scan.
 */
it('escapes the wildcard that would match everything', function () {
    expect(PostalCode::escapeLike('%'))->toBe('\\%');
});

it('escapes the single-character wildcard', function () {
    expect(PostalCode::escapeLike('_'))->toBe('\\_');
});

it('escapes a backslash so it cannot re-enable a wildcard', function () {
    expect(PostalCode::escapeLike('\\'))->toBe('\\\\');
});

it('leaves an ordinary settlement name untouched', function () {
    expect(PostalCode::escapeLike('Warszawa'))->toBe('Warszawa');
    expect(PostalCode::escapeLike('Bielsko-Biała'))->toBe('Bielsko-Biała');
    expect(PostalCode::escapeLike("Kłodzko Zdrój"))->toBe("Kłodzko Zdrój");
});

it('escapes wildcards embedded in a real query', function () {
    expect(PostalCode::escapeLike('War%sz_awa'))->toBe('War\\%sz\\_awa');
});
