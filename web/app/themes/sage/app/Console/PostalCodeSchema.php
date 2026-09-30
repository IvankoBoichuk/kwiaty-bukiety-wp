<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the postal_codes table.
 *
 * database/migrations/ holds the same definition, but Acorn 6 ships no
 * `migrate` command, so nothing ever ran it and a fresh install had no table
 * at all -- which takes down the checkout's city and postcode autocomplete.
 * This runs from WP-CLI instead and is safe to re-run.
 */
final class PostalCodeSchema
{
    public static function exists(): bool
    {
        return Schema::hasTable('postal_codes');
    }

    public static function install(): bool
    {
        if (self::exists()) {
            return false;
        }

        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();

            $table->string('postal_code', 10);
            $table->string('settlement');

            $table->text('street')->nullable();
            $table->text('house_numbers')->nullable();
            $table->string('municipality')->nullable();
            $table->string('county')->nullable();
            $table->string('province')->nullable();

            $table->timestamps();

            $table->index('postal_code');
            $table->index('settlement');
            $table->index(['postal_code', 'settlement']);
            $table->index(['settlement', 'postal_code']);
        });

        return true;
    }
}
