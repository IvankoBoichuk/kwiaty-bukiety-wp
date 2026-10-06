<?php

declare(strict_types=1);

use App\Modules\LocalLinking\LocalPageRepository;

/**
 * The city autocomplete behind /wp-json/sage/v1/cities.
 *
 * The production endpoint matched the raw substring of the raw query, so
 * "pila" missed Piła and "lodz" missed Łódź -- the two spellings a visitor
 * without a Polish keyboard actually types -- and returned whatever the first
 * twenty rows of the CSV happened to be. Both halves are covered here: the
 * fold that makes the comparison work, and the ranking that decides the order.
 *
 * The suite boots no WordPress, so these also pin down that normalize() folds
 * the Polish set on its own rather than leaning on remove_accents().
 */

/**
 * @param  array<int, array{0: string, 1: string, 2: int}>  $cities
 * @return array<int, array{normalized: string, name: string, voivodeship: string, term_id: int}>
 */
function kb_city_index(array $cities): array
{
    return array_map(
        static fn(array $city): array => [
            'normalized' => LocalPageRepository::normalize($city[0]),
            'name' => $city[0],
            'voivodeship' => $city[1],
            'term_id' => $city[2],
        ],
        $cities,
    );
}

/**
 * @param  array<int, array{name: string, voivodeship: string, term_id: int}>  $matches
 * @return array<int, string>
 */
function kb_city_names(array $matches): array
{
    return array_column($matches, 'name');
}

it('folds every Polish diacritic to its ASCII letter', function () {
    expect(LocalPageRepository::normalize('ĄĆĘŁŃÓŚŹŻ'))->toBe('acelnoszz');
});

it('folds the crossed L without needing WordPress to do it', function () {
    expect(LocalPageRepository::normalize('Łódź'))->toBe('lodz');
    expect(LocalPageRepository::normalize('Piła'))->toBe('pila');
    expect(LocalPageRepository::normalize('Świnoujście'))->toBe('swinoujscie');
});

it('lower-cases, trims and collapses whitespace', function () {
    expect(LocalPageRepository::normalize('  NOWY   SĄCZ '))->toBe('nowy sacz');
});

it('is idempotent, so a normalized query can be normalized again', function () {
    $once = LocalPageRepository::normalize('Bielsko-Biała');

    expect(LocalPageRepository::normalize($once))->toBe($once);
});

it('finds a city typed without its diacritics', function () {
    $index = kb_city_index([
        ['Piła', 'Wielkopolskie', 755],
        ['Pilawa', 'Mazowieckie', 1101],
        ['Łódź', 'Łódzkie', 849],
    ]);

    // Which of the two comes first is the next test's business: they are both
    // prefix matches differing only by ł/l, which is the one place the two
    // orderings disagree. What matters here is that a query with no diacritics
    // reaches a name that has them.
    expect(kb_city_names(LocalPageRepository::rankMatches($index, 'Pila')))
        ->toEqualCanonicalizing(['Piła', 'Pilawa']);

    expect(kb_city_names(LocalPageRepository::rankMatches($index, 'lodz')))
        ->toBe(['Łódź']);
});

/**
 * Within a band the order comes from Collator('pl_PL') when intl is loaded and
 * from the ASCII fold when it is not, and for ł/l the two disagree. That is the
 * documented fallback, not a bug -- but it is worth pinning, because the two
 * environments this runs in differ: the dev box has intl, the php:8.3-cli-alpine
 * image CI tests on does not.
 */
it('orders ł against l by whichever collation is available', function () {
    $index = kb_city_index([
        ['Piła', 'Wielkopolskie', 755],
        ['Pilawa', 'Mazowieckie', 1101],
    ]);

    expect(kb_city_names(LocalPageRepository::rankMatches($index, 'pila')))->toBe(
        class_exists(Collator::class)
            // Polish collation puts l before ł, so Pilawa sorts first.
            ? ['Pilawa', 'Piła']
            // The fold compares "pila" against "pilawa", so Piła sorts first.
            : ['Piła', 'Pilawa'],
    );
});

it('puts a match at the start of the name above one inside it', function () {
    $index = kb_city_index([
        ['Chełm Lubelski', 'Lubelskie', 10],
        ['Lublin', 'Lubelskie', 20],
        ['Bogatynia-Lubań', 'Dolnośląskie', 30],
    ]);

    expect(kb_city_names(LocalPageRepository::rankMatches($index, 'Lu')))
        ->toBe(['Lublin', 'Bogatynia-Lubań', 'Chełm Lubelski']);
});

it('orders a word-start match by name, hyphen and space alike', function () {
    $index = kb_city_index([
        ['Zielona Góra', 'Lubuskie', 1],
        ['Nowa Góra', 'Małopolskie', 2],
        ['Góra', 'Dolnośląskie', 3],
    ]);

    expect(kb_city_names(LocalPageRepository::rankMatches($index, 'gora')))
        ->toBe(['Góra', 'Nowa Góra', 'Zielona Góra']);
});

it('honours the limit', function () {
    $index = kb_city_index([
        ['Warszawa', 'Mazowieckie', 1],
        ['Warta', 'Łódzkie', 2],
        ['Warka', 'Mazowieckie', 3],
    ]);

    expect(LocalPageRepository::rankMatches($index, 'war', 2))->toHaveCount(2);
    expect(LocalPageRepository::rankMatches($index, 'war', 0))->toBe([]);
});

it('returns nothing for a query that normalizes to nothing', function () {
    $index = kb_city_index([['Warszawa', 'Mazowieckie', 1]]);

    expect(LocalPageRepository::rankMatches($index, '   '))->toBe([]);
});

it('carries the voivodeship and the term id of each match', function () {
    $index = kb_city_index([['Piła', 'Wielkopolskie', 755]]);

    expect(LocalPageRepository::rankMatches($index, 'pil'))->toBe([
        ['name' => 'Piła', 'voivodeship' => 'Wielkopolskie', 'term_id' => 755],
    ]);
});
