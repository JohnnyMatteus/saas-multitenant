<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Datas imutáveis evitam mutação acidental em cálculos de agenda.
        Date::use(CarbonImmutable::class);

        // Falha cedo em lazy loading, atributos inexistentes e mass assignment silencioso.
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
