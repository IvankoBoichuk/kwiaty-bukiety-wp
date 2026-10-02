<?php

declare(strict_types=1);

namespace App\Catalog;

/**
 * Per-category FAQ, stored as the `kb_faq` term meta.
 *
 * Replaces the production ACF field `category_faq_json` -- a WYSIWYG field that
 * held HTML despite the name, was echoed into the archive template unescaped
 * and produced no FAQPage markup at all. Here the questions are structured
 * data, so one component can render both the visible block and the JSON-LD.
 */
final class Faq
{
    public const META_KEY = 'kb_faq';

    /**
     * The legacy ACF field the importer reads from when no file is given.
     */
    public const LEGACY_META_KEY = 'category_faq_json';

    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function forTerm(int $termId): array
    {
        if ($termId <= 0) {
            return [];
        }

        return self::normalize(get_term_meta($termId, self::META_KEY, true));
    }

    /**
     * @return array<int, array{q: string, a: string}>
     */
    public static function forCurrentTerm(): array
    {
        $term = get_queried_object();

        return $term instanceof \WP_Term ? self::forTerm($term->term_id) : [];
    }

    /**
     * Drops anything without both a question and an answer, so the template
     * never has to guard against half-filled rows.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function normalize(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $question = trim(wp_strip_all_tags((string) ($item['q'] ?? '')));
            $answer = trim((string) ($item['a'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = ['q' => $question, 'a' => wp_kses_post($answer)];
        }

        return $items;
    }

    /**
     * @param  array<int, array{q: string, a: string}>  $items
     */
    public static function save(int $termId, array $items): bool
    {
        $items = self::normalize($items);

        if ($items === []) {
            return (bool) delete_term_meta($termId, self::META_KEY);
        }

        return (bool) update_term_meta($termId, self::META_KEY, $items);
    }

    /**
     * The JSON-LD FAQPage node for a set of questions.
     *
     * @param  array<int, array{q: string, a: string}>  $items
     * @return array<string, mixed>
     */
    public static function schema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_values(array_map(
                static fn(array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['q'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        // Google reads the answer as text; the visible block
                        // keeps the markup.
                        'text' => wp_strip_all_tags($item['a']),
                    ],
                ],
                $items,
            )),
        ];
    }
}
