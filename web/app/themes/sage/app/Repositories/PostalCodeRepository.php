<?php

namespace App\Repositories;

use App\Models\PostalCode;
use Illuminate\Support\Collection;

class PostalCodeRepository
{
    public function findCitiesByPna(string $postal_code): Collection
    {
        return PostalCode::query()
            ->select('settlement', 'municipality', 'county', 'province')
            ->where('postal_code', trim($postal_code))
            ->distinct()
            ->orderBy('settlement')
            ->get();
    }

    public function findPnaByCity(string $city): Collection
    {
        return PostalCode::query()
            ->select('postal_code', 'settlement', 'municipality', 'county', 'province')
            ->where('settlement', trim($city))
            ->distinct()
            ->orderBy('postal_code')
            ->get();
    }
}
