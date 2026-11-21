<?php

use App\Modules\Branding\BrandingServiceProvider;
use App\Modules\Identity\IdentityServiceProvider;
use App\Modules\Observability\ObservabilityServiceProvider;
use App\Modules\Scheduling\SchedulingServiceProvider;
use App\Modules\Tenancy\TenancyServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    IdentityServiceProvider::class,
    SchedulingServiceProvider::class,
    BrandingServiceProvider::class,
    ObservabilityServiceProvider::class,
];
