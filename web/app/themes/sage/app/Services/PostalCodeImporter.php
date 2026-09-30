<?php

namespace App\Services;

use App\Models\PostalCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostalCodeImporter
{
    public function import(string $path): int
    {
        Log::info('Postal codes import started', [
            'path' => $path,
        ]);

        if (! file_exists($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new \RuntimeException("Cannot open file: {$path}");
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            throw new \RuntimeException('CSV file is empty.');
        }

        $delimiter = $this->detectDelimiter($firstLine);

        $headers = str_getcsv(trim($firstLine), $delimiter);
        $headers = array_map([$this, 'normalizeHeader'], $headers);

        Log::info('CSV detected', [
            'delimiter' => $delimiter === "\t" ? 'tab' : $delimiter,
            'headers' => $headers,
        ]);

        DB::table((new PostalCode())->getTable())->truncate();

        $count = 0;
        $rowNumber = 1;
        $skipped = 0;
        $batch = [];
        $now = now();

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if (count($headers) !== count($row)) {
                Log::warning('Postal code row skipped: column count mismatch', [
                    'row_number' => $rowNumber,
                    'headers_count' => count($headers),
                    'row_count' => count($row),
                    'row' => $row,
                ]);

                $skipped++;
                continue;
            }

            $data = array_combine($headers, $row);

            if (! $data || empty($data['postal_code']) || empty($data['settlement'])) {
                Log::warning('Postal code row skipped: missing postal_code or settlement', [
                    'row_number' => $rowNumber,
                    'data' => $data,
                ]);

                $skipped++;
                continue;
            }

            $batch[] = [
                'postal_code' => trim($data['postal_code'] ?? ''),
                'settlement' => trim($data['settlement'] ?? ''),
                'street' => trim($data['street'] ?? ''),
                'house_numbers' => trim($data['house_numbers'] ?? ''),
                'municipality' => trim($data['municipality'] ?? ''),
                'county' => trim($data['county'] ?? ''),
                'province' => trim($data['province'] ?? ''),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ];

            if (count($batch) >= 1000) {
                $this->insertBatch($batch);

                $count += count($batch);

                Log::info('Postal codes batch inserted', [
                    'inserted_total' => $count,
                    'batch_size' => count($batch),
                ]);

                $batch = [];
            }
        }

        if ($batch) {
            $this->insertBatch($batch);

            $count += count($batch);

            Log::info('Postal codes last batch inserted', [
                'inserted_total' => $count,
                'batch_size' => count($batch),
            ]);
        }

        fclose($handle);

        Log::info('Postal codes import finished', [
            'imported' => $count,
            'skipped' => $skipped,
            'rows_read' => $rowNumber,
        ]);

        return $count;
    }
    private function insertBatch(array $batch): void
    {
        if (! $batch) {
            return;
        }

        global $wpdb;

        $table = $wpdb->prefix . 'postal_codes';

        $sql = "
            INSERT INTO {$table}
            (postal_code, settlement, street, house_numbers, municipality, county, province, created_at, updated_at)
            VALUES
        ";

        $placeholders = [];
        $bindings = [];

        foreach ($batch as $item) {
            $placeholders[] = "(?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $bindings[] = $item['postal_code'];
            $bindings[] = $item['settlement'];
            $bindings[] = $item['street'];
            $bindings[] = $item['house_numbers'];
            $bindings[] = $item['municipality'];
            $bindings[] = $item['county'];
            $bindings[] = $item['province'];
            $bindings[] = $item['created_at'];
            $bindings[] = $item['updated_at'];
        }

        DB::insert($sql . implode(', ', $placeholders), $bindings);
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = ["\t", ';', ','];

        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($line, $delimiter);
        }

        arsort($counts);

        return array_key_first($counts);
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);

        // Remove UTF-8 BOM
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);

        return $header;
    }
}
