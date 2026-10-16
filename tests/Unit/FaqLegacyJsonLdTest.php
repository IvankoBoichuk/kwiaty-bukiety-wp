<?php

declare(strict_types=1);

use App\Catalog\Faq;

/**
 * The shape the four production categories store in `category_faq_json`.
 */
function legacyFaqScript(array $questions): string
{
    return "<script type=\"application/ld+json\">\r\n" . json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(
            static fn(array $q): array => [
                '@type' => 'Question',
                'name' => $q[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q[1]],
            ],
            $questions,
        ),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\r\n</script>\r\n";
}

describe('Faq::parseLegacyJsonLd', function () {
    it('reads the questions that the category description shows', function () {
        $markup = legacyFaqScript([
            ['Czy mężczyźnie wypada dać kwiaty?', 'Tak, to elegancki gest.'],
            ['Który kwiat symbolizuje mężczyznę?', 'Najczęściej storczyk.'],
        ]);
        $description = '<h2>FAQ – pytania i odpowiedzi</h2>'
            . '<h3>Czy mężczyźnie wypada dać kwiaty?</h3><p>Tak, to elegancki gest.</p>'
            . "<h3>Który kwiat\n symbolizuje mężczyznę?</h3><p>Najczęściej storczyk.</p>";

        expect(Faq::parseLegacyJsonLd($markup, $description))->toBe([
            ['q' => 'Czy mężczyźnie wypada dać kwiaty?', 'a' => 'Tak, to elegancki gest.'],
            ['q' => 'Który kwiat symbolizuje mężczyznę?', 'a' => 'Najczęściej storczyk.'],
        ]);
    });

    it('drops a question that is not on the page, because Google requires it visible', function () {
        $markup = legacyFaqScript([
            ['Jakie kwiaty lubią dziewczyny?', 'Róże i piwonie.'],
            ['Pytanie tylko w znacznikach?', 'Nie ma go na stronie.'],
        ]);

        expect(Faq::parseLegacyJsonLd($markup, '<h3>Jakie kwiaty lubią dziewczyny?</h3>'))
            ->toBe([['q' => 'Jakie kwiaty lubią dziewczyny?', 'a' => 'Róże i piwonie.']]);
    });

    it('matches a question whose description spells it with entities', function () {
        $markup = legacyFaqScript([['Co powiedzieć kobiecie, dając "kwiaty"?', 'Coś od serca.']]);

        expect(Faq::parseLegacyJsonLd($markup, '<h3>Co powiedzieć kobiecie, dając &quot;kwiaty&quot;?</h3>'))
            ->toHaveCount(1);
    });

    it('finds a FAQPage nested in a @graph', function () {
        $markup = '<script type="application/ld+json">' . json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebPage'],
                ['@type' => 'FAQPage', 'mainEntity' => [
                    ['@type' => 'Question', 'name' => 'Q?', 'acceptedAnswer' => ['text' => 'A.']],
                ]],
            ],
        ]) . '</script>';

        expect(Faq::parseLegacyJsonLd($markup, '<p>Q?</p>'))->toBe([['q' => 'Q?', 'a' => 'A.']]);
    });

    it('returns nothing for the HTML the other categories kept in the field', function () {
        expect(Faq::parseLegacyJsonLd('<h3>Q?</h3><p>A.</p>', '<h3>Q?</h3>'))->toBe([]);
    });

    it('returns nothing when there is no description to check against', function () {
        expect(Faq::parseLegacyJsonLd(legacyFaqScript([['Q?', 'A.']]), ''))->toBe([]);
    });

    it('ignores a broken script', function () {
        expect(Faq::parseLegacyJsonLd('<script type="application/ld+json">{nope</script>', '<p>Q?</p>'))->toBe([]);
    });
});
