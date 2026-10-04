<?php

declare(strict_types=1);

use App\Support\Markup;

/**
 * The scan behind Markup::cardImage()'s fallback.
 *
 * Only the pure half is covered here -- the suite boots no WordPress, so the
 * library lookup that turns these ids into an <img> is not exercised. What
 * matters at this level is that the editor markup of the imported posts yields
 * the id of the image that opens the body, and that nothing else in a page full
 * of blocks is mistaken for one.
 */
it('reads the id of a core image block', function () {
    $content = <<<'HTML'
    <!-- wp:paragraph --><p>Lead.</p><!-- /wp:paragraph -->
    <!-- wp:image {"id":8287,"sizeSlug":"full","linkDestination":"none"} -->
    <figure class="wp-block-image size-full"><img src="/x.webp" alt="" class="wp-image-8287"/></figure>
    <!-- /wp:image -->
    HTML;

    expect(Markup::contentImageIds($content))->toBe([8287]);
});

it('keeps the order of the images and drops the repeats', function () {
    $content = <<<'HTML'
    <!-- wp:image {"id":11} --><figure><img class="wp-image-11"/></figure><!-- /wp:image -->
    <!-- wp:image {"id":22} --><figure><img class="wp-image-22"/></figure><!-- /wp:image -->
    HTML;

    expect(Markup::contentImageIds($content))->toBe([11, 22]);
});

it('reads a cover block, whose id only appears in the block comment', function () {
    $content = '<!-- wp:cover {"url":"/x.webp","id":99,"dimRatio":50} --><div class="wp-block-cover"></div><!-- /wp:cover -->';

    expect(Markup::contentImageIds($content))->toBe([99]);
});

it('ignores the ids of blocks that are not images', function () {
    $content = '<!-- wp:query {"queryId":5,"query":{"id":77}} --><!-- /wp:query -->'
        . '<!-- wp:image {"id":42} --><figure><img class="wp-image-42"/></figure><!-- /wp:image -->';

    expect(Markup::contentImageIds($content))->toBe([42]);
});

it('returns nothing for a body with no image in it', function () {
    expect(Markup::contentImageIds('<!-- wp:paragraph --><p>Just words.</p><!-- /wp:paragraph -->'))
        ->toBe([]);
    expect(Markup::contentImageIds(''))->toBe([]);
});

it('skips an image hotlinked from outside the library', function () {
    // It carries no attachment id, so there is nothing to render a srcset from
    // and the card falls back to its placeholder.
    expect(Markup::contentImageIds('<p><img src="https://example.com/x.jpg" /></p>'))->toBe([]);
});
