<?php

declare(strict_types=1);

namespace KB\HostingerRedirects;

/**
 * The difference between what Redirection says and what Hostinger holds.
 *
 * Pure on purpose: every destructive decision the module makes is taken here,
 * from three plain arrays, so it can be tested without a token.
 *
 * The rule that keeps it safe to run unattended: a redirect is only deleted
 * when the ledger says this module created it. A redirect somebody added in
 * hPanel is left alone even when it collides with a Redirection row -- it is
 * reported as a conflict and the row stays in PHP, which still answers.
 */
final class Plan
{
    /**
     * @param  array<string, string>  $desired  from => to, from Map::build().
     * @param  array<string, string>  $remote  from => to, as Hostinger holds it.
     * @param  array<string, string>  $ledger  from => to, what this module created.
     * @return array{
     *     create: array<string, string>,
     *     delete: array<int, string>,
     *     conflicts: array<int, array{from: string, ours: string, theirs: string}>,
     *     unchanged: array<string, string>,
     * }
     */
    public static function build(array $desired, array $remote, array $ledger): array
    {
        $create = [];
        $delete = [];
        $conflicts = [];
        $unchanged = [];

        foreach ($desired as $from => $to) {
            if (! array_key_exists($from, $remote)) {
                $create[$from] = $to;

                continue;
            }

            if ($remote[$from] === $to) {
                $unchanged[$from] = $to;

                continue;
            }

            // The source is taken but points elsewhere. If it is ours, the
            // target changed in Redirection and the redirect is replaced --
            // the endpoint has no update, so the old one goes first.
            if (array_key_exists($from, $ledger)) {
                $delete[] = $from;
                $create[$from] = $to;

                continue;
            }

            $conflicts[] = ['from' => $from, 'ours' => $to, 'theirs' => $remote[$from]];
        }

        // Ours, no longer wanted: the row was deleted, disabled, or edited
        // into something the server cannot answer. Dropping it hands the URL
        // back to PHP, where Redirection decides again.
        foreach ($ledger as $from => $to) {
            if (array_key_exists($from, $desired)) {
                continue;
            }

            if (! array_key_exists($from, $remote)) {
                continue;
            }

            $delete[] = $from;
        }

        return [
            'create' => $create,
            'delete' => array_values(array_unique($delete)),
            'conflicts' => $conflicts,
            'unchanged' => $unchanged,
        ];
    }
}
