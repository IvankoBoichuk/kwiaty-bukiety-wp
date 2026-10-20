<?php

declare(strict_types=1);

use App\Catalog\Faq;

describe('Faq::parseLegacyHtml', function () {
    it('reads the h3/p pairs the "Kwiaciarnie w Polsce" category kept in the field', function () {
        $html = '<div class="faq-section"><h2>FAQ – Najczęściej zadawane pytania</h2>'
            . '<h3>Czy mogę zamówić kwiaty z dostawą do dowolnego miasta w Polsce?</h3>'
            . '<p>Tak, nasz serwis współpracuje z kwiaciarniami w całej Polsce.</p>'
            . '<h3>Jakie są dostępne metody płatności?</h3>'
            . '<p>Kartą, BLIK</p><p>i szybkie przelewy.</p></div>';

        expect(Faq::parseLegacyHtml($html))->toBe([
            [
                'q' => 'Czy mogę zamówić kwiaty z dostawą do dowolnego miasta w Polsce?',
                'a' => '<p>Tak, nasz serwis współpracuje z kwiaciarniami w całej Polsce.</p>',
            ],
            ['q' => 'Jakie są dostępne metody płatności?', 'a' => '<p>Kartą, BLIK</p><p>i szybkie przelewy.</p>'],
        ]);
    });

    it('stops an answer at the next heading of any level', function () {
        expect(Faq::parseLegacyHtml('<h3>Q?</h3><p>A.</p><h2>Inne</h2><p>Nie odpowiedź.</p>'))
            ->toBe([['q' => 'Q?', 'a' => '<p>A.</p>']]);
    });

    it('drops a question without an answer', function () {
        expect(Faq::parseLegacyHtml('<h3>Q?</h3><h3>R?</h3><p>B.</p>'))->toBe([['q' => 'R?', 'a' => '<p>B.</p>']]);
    });

    it('returns nothing for the JSON-LD-only field or an empty one', function () {
        expect(Faq::parseLegacyHtml('<script type="application/ld+json">{}</script>'))->toBe([])
            ->and(Faq::parseLegacyHtml(''))->toBe([]);
    });
});
