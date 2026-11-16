<?php

namespace Database\Seeders;

use App\Services\Imports\CountryStateCityService;
use Illuminate\Database\Seeder;

class DefaultSettingBootstrapper extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CountryStateCityService $countryStateCityService): void
    {
        $countryStateCityService->countries();
        $countryStateCityService->states();
        $countryStateCityService->cities();
        // $countryStateCityService->institutions();
    }
}
