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
     * The legacy ACF field. Most categories held HTML in it, which the importer
     * turned into `kb_faq`; four (podziekowania, dla-dziewczyny, dla-zony,
     * dla-mezczyzny) held only a JSON-LD script, with the questions written
     * into the category description. See legacyForCurrentTerm().
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
     * FAQPage questions for a category whose legacy field holds a JSON-LD
     * script instead of HTML. The questions themselves are visible in the
     * category description, so the markup is legitimate -- production
     * published it, and dropping it lost the FAQ rich result.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function legacyForCurrentTerm(string $visibleHtml): array
    {
        $term = get_queried_object();

        if (! $term instanceof \WP_Term) {
            return [];
        }

        return self::parseLegacyJsonLd(
            (string) get_term_meta($term->term_id, self::LEGACY_META_KEY, true),
            $visibleHtml,
        );
    }

    /**
     * Reads the FAQPage questions out of the JSON-LD scripts in $markup and
     * keeps only those whose question is visible in $visibleHtml: Google
     * ignores, and may penalise, FAQ markup for questions not on the page.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function parseLegacyJsonLd(string $markup, string $visibleHtml): array
    {
        if (! preg_match_all('#<script[^>]*application/ld\+json[^>]*>(.*?)</script>#is', $markup, $scripts)) {
            return [];
        }

        $visible = self::plainText($visibleHtml);

        if ($visible === '') {
            return [];
        }

        $items = [];

        foreach ($scripts[1] as $json) {
            $data = json_decode(trim($json), true);

            if (! is_array($data)) {
                continue;
            }

            $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : [$data];

            foreach ($nodes as $node) {
                if (! is_array($node) || ($node['@type'] ?? '') !== 'FAQPage' || ! is_array($node['mainEntity'] ?? null)) {
                    continue;
                }

                foreach ($node['mainEntity'] as $entity) {
                    if (! is_array($entity)) {
                        continue;
                    }

                    $question = self::plainText((string) ($entity['name'] ?? ''));
                    $answer = self::plainText((string) ($entity['acceptedAnswer']['text'] ?? ''));

                    if ($question === '' || $answer === '' || ! str_contains($visible, $question)) {
                        continue;
                    }

                    $items[] = ['q' => $question, 'a' => $answer];
                }
            }
        }

        return $items;
    }

    /**
     * Questions written into the legacy field as plain HTML: each <h3> is a
     * question, and everything after it up to the next heading is its answer.
     * That is the shape the "Kwiaciarnie w Polsce" category used on production
     * (a `.faq-section` of h3/p pairs), which the FAQ import did not pick up.
     *
     * @return array<int, array{q: string, a: string}>
     */
    public static function parseLegacyHtml(string $html): array
    {
        if (trim($html) === '' || ! str_contains(strtolower($html), '<h3')) {
            return [];
        }

        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $items = [];

        foreach ($dom->getElementsByTagName('h3') as $heading) {
            $answer = '';

            for ($node = $heading->nextSibling; $node !== null; $node = $node->nextSibling) {
                if ($node instanceof \DOMElement && preg_match('/^h[1-6]$/i', $node->nodeName)) {
                    break;
                }

                $answer .= $dom->saveHTML($node);
            }

            $items[] = ['q' => self::plainText($heading->textContent), 'a' => trim($answer)];
        }

        return self::normalize($items);
    }

    private static function plainText(string $html): string
    {
        $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
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
