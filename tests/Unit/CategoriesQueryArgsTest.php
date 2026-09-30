<?php

declare(strict_types=1);

use App\Api\Categories;

/**
 * The /categories endpoint is public and unauthenticated, so these two guards
 * are the only thing between an anonymous caller and an arbitrary, unbounded
 * get_terms().
 */
describe('taxonomy allowlist', function () {
    it('keeps a taxonomy the endpoint serves', function () {
        expect(Categories::resolveTaxonomy('product_cat'))->toBe('product_cat');
        expect(Categories::resolveTaxonomy('product_tag'))->toBe('product_tag');
    });

    it('falls back for a taxonomy it does not serve', function () {
        expect(Categories::resolveTaxonomy('nav_menu'))->toBe('product_cat');
        expect(Categories::resolveTaxonomy('wp_theme'))->toBe('product_cat');
    });

    it('falls back when the taxonomy is missing or not a string', function () {
        expect(Categories::resolveTaxonomy(null))->toBe('product_cat');
        expect(Categories::resolveTaxonomy(''))->toBe('product_cat');
        expect(Categories::resolveTaxonomy(123))->toBe('product_cat');
    });

    it('takes the first entry when a list is passed', function () {
        expect(Categories::resolveTaxonomy(['product_tag', 'nav_menu']))->toBe('product_tag');
        expect(Categories::resolveTaxonomy(['nav_menu']))->toBe('product_cat');
    });
});

describe('number clamping', function () {
    it('treats zero as unspecified rather than one', function () {
        // get_terms() reads 0 as unlimited, so this is the case that matters.
        expect(Categories::clampNumber(0))->toBe(20);
    });

    it('defaults when the parameter is absent', function () {
        expect(Categories::clampNumber(null))->toBe(20);
    });

    it('caps a large request', function () {
        expect(Categories::clampNumber(500))->toBe(100);
        expect(Categories::clampNumber(PHP_INT_MAX))->toBe(100);
    });

    it('passes a value through the allowed range', function () {
        expect(Categories::clampNumber(1))->toBe(1);
        expect(Categories::clampNumber(9))->toBe(9);
        expect(Categories::clampNumber(100))->toBe(100);
    });

    it('does not let a negative value through', function () {
        expect(Categories::clampNumber(-1))->toBe(20);
    });
});
