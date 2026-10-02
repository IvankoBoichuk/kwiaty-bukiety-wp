<?php

declare(strict_types=1);

namespace App\Console;

use App\Catalog\Faq;

/**
 * Imports the parsed production FAQ into the `kb_faq` term meta.
 *
 * Input is migration/faq/faq-parsed.json: 100 categories and 433 questions
 * extracted from the old ACF HTML field. Re-running it is safe -- each term is
 * overwritten with the file's contents.
 *
 * Terms are matched by slug, not by the term_id in the file. The ids belong to
 * the database the export came from, and they do not survive a reimport: on the
 * current development database every one of them resolves to a different
 * category, so matching by id would file all 100 FAQs under the wrong terms.
 */
final class FaqImporter
{
    /**
     * @return array{terms: int, questions: int, missing: array<int, string>, empty: array<int, string>}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $records = $this->read($path);

        $terms = 0;
        $questions = 0;
        $missing = [];
        $empty = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $slug = (string) ($record['slug'] ?? '');
            $items = Faq::normalize($record['items'] ?? []);

            if ($slug === '') {
                continue;
            }

            if ($items === []) {
                $empty[] = $slug;

                continue;
            }

            $term = get_term_by('slug', $slug, 'product_cat');

            if (! $term instanceof \WP_Term) {
                $missing[] = $slug;

                continue;
            }

            if (! $dryRun) {
                Faq::save($term->term_id, $items);
            }

            $terms++;
            $questions += count($items);
        }

        return [
            'terms' => $terms,
            'questions' => $questions,
            'missing' => $missing,
            'empty' => $empty,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    protected function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read {$path}.");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException("{$path} is not a JSON array.");
        }

        return $decoded;
    }
}
