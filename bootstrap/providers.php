<?php

use App\Providers\AppServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Modules\Unipotence\Infrastructure\UnipotenceServiceProvider;

return [
    AppServiceProvider::class,
    TelescopeServiceProvider::class,
    UnipotenceServiceProvider::class,
];
