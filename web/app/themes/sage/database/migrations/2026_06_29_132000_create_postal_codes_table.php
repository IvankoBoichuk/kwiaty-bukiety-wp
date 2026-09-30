<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
