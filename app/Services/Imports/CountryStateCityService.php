<?php

namespace App\Services\Imports;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonMachine\Exception\InvalidArgumentException;
use JsonMachine\Items;

class CountryStateCityService
{
    protected Filesystem $files;

    public function __construct()
    {
        $this->files = new Filesystem;
        // avoid memory blow-up when importing huge batches
        DB::disableQueryLog();
    }

    // --------------------------------------------------
    // Shared helpers
    // --------------------------------------------------

    protected function seederPath(string $filename): string
    {
        return storage_path("app/private/seeders/{$filename}");
    }

    /**
     * Stream a JSON array file: [ {...}, {...}, ... ]
     * Uses JsonMachine under the hood.
     *
     * @throws InvalidArgumentException
     */
    protected function streamJsonArray(string $filename): Items|\ArrayIterator
    {
        $path = $this->seederPath($filename);

        if (! $this->files->exists($path)) {
            return new \ArrayIterator([]);
        }

        return Items::fromFile($path);
    }

    // --------------------------------------------------
    // Institutions (tenant-scoped)
    // --------------------------------------------------

    /**
     * Import institutions using batch upsert.
     * Dedupe is handled by upsert on (tenant_id, code).
     */
    public function institutions(string $filename = 'institutions.json'): void
    {
        $items = $this->streamJsonArray($filename);
        $batch = [];
        $batchSize = 1000;
        $now = now();

        foreach ($items as $row) {

            $batch[] = [
                // keep upstream id if you already reference it elsewhere
                'id' => $row->id ?? null,
                'uuid' => $row->uuid ?? (string) Str::uuid(),
                'tenant_id' => $this->tenant->id,
                'name' => $row->name ?? null,
                'code' => $row->code ?? null,
                'category' => $row->category ?? null,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertInstitutionsBatch($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->upsertInstitutionsBatch($batch);
        }
    }

    protected function upsertInstitutionsBatch(array $batch): void
    {
        // Let upsert handle duplicates by tenant + code
        DB::table('institutions')->upsert(
            $batch,
            ['tenant_id', 'code'], // conflict key
            [
                'name',
                'category',
                'uuid',
                'updated_at',
            ]
        );
    }

    // --------------------------------------------------
    // Countries
    // --------------------------------------------------

    /**
     * Countries from a canonical dataset.
     * We keep their numeric id because states/cities reference it.
     * Dedupe by id via upsert.
     */
    public function countries(string $filename = 'countries.json'): void
    {
        $items = $this->streamJsonArray($filename);

        $batch = [];
        $batchSize = 1000;
        $now = now();

        foreach ($items as $row) {
            //            dd($row);
            //            if (! is_array($row)) {
            //                continue;
            //            }

            $batch[] = [
                'id' => $row->id,
                'uuid' => Str::uuid(),
                'name' => $row->name,
                'iso2' => $row->iso2,
                'iso3' => $row->iso3,
                'numeric_code' => $row->numeric_code,
                'phone_code' => $row->phonecode,
                'capital' => $row->capital,
                'currency_name' => $row->currency_name,
                'currency_symbol' => $row->currency_symbol,
                'currency' => $row->currency,
                'native' => $row->native,
                'region' => $row->region,
                'subregion' => $row->subregion,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'emoji' => $row->emoji,
                'emojiU' => $row->emojiU,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) >= $batchSize) {
                $this->upsertCountriesBatch($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->upsertCountriesBatch($batch);
        }
    }

    protected function upsertCountriesBatch(array $batch): void
    {
        DB::table('countries')->upsert(
            $batch,
            ['id'],  // PK from the dataset; duplicates update instead of error
            [
                'name',
                'iso2',
                'iso3',
                'numeric_code',
                'phone_code',
                'capital',
                'currency_name',
                'currency_symbol',
                'currency',
                'native',
                'region',
                'subregion',
                'latitude',
                'longitude',
                'emoji',
                'emojiU',
                'updated_at',
            ]
        );
    }

    // --------------------------------------------------
    // States
    // --------------------------------------------------

    public function states(string $filename = 'states.json'): void
    {
        $items = $this->streamJsonArray($filename);
        $batch = [];
        $batchSize = 1000;
        $now = now();

        foreach ($items as $row) {
            //            if (! is_array($row)) {
            //                continue;
            //            }

            $batch[] = [
                'id' => $row->id,
                'uuid' => Str::uuid(),
                'name' => $row->name,
                'country_id' => $row->country_id,
                'country_code' => $row->country_code,
                'country_name' => $row->country_name,
                'state_code' => $row->state_code,
                'type' => $row->type,
                'latitude' => $row->latitude,
                'longitude' => $row->longitude,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertStatesBatch($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->upsertStatesBatch($batch);
        }
    }

    protected function upsertStatesBatch(array $batch): void
    {
        DB::table('states')->upsert(
            $batch,
            ['id'], // same logic: dedupe by dataset id
            [
                'uuid',
                'name',
                'country_id',
                'country_code',
                'country_name',
                'state_code',
                'type',
                'latitude',
                'longitude',
                'updated_at',
            ]
        );
    }

    // --------------------------------------------------
    // Cities (very large)
    // --------------------------------------------------

    public function cities(string $filename = 'cities.json'): void
    {
        $this->importCities($filename);
    }

    /**
     * Streams cities.json and upserts in batches.
     *
     * @return int number of records processed
     */
    public function importCities(string $filename = 'cities.json'): int
    {
        $items = $this->streamJsonArray($filename);
        $batch = [];
        $batchSize = 1000;
        $count = 0;
        $now = now();

        foreach ($items as $city) {
            //            if (! is_array($city)) {
            //                continue;
            //            }

            $batch[] = [
                'id' => $city->id,
                'uuid' => Str::uuid(),
                'name' => $city->name,
                'state_id' => $city->state_id,
                'state_code' => $city->state_code,
                'state_name' => $city->state_name,
                'country_id' => $city->country_id,
                'country_code' => $city->country_code,
                'country_name' => $city->country_name,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'wikiDataId' => $city->wikiDataId ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $batchSize) {
                $this->upsertCitiesBatch($batch);
                $count += count($batch);
                $batch = [];
            }
        }

        if ($batch) {
            $this->upsertCitiesBatch($batch);
            $count += count($batch);
        }

        return $count;
    }

    protected function upsertCitiesBatch(array $batch): void
    {
        DB::table('cities')->upsert(
            $batch,
            ['id'], // dataset PK; repeated id → update
            [
                'uuid',
                'name',
                'state_id',
                'state_code',
                'state_name',
                'country_id',
                'country_code',
                'country_name',
                'latitude',
                'longitude',
                'wikiDataId',
                'updated_at',
            ]
        );
    }
}
