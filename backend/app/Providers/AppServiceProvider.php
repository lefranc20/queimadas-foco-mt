<?php

namespace App\Providers;

use App\Services\Inpe\ClienteInpe;
use App\Services\Inpe\LeitorCsvFocos;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClienteInpe::class, fn () => new ClienteInpe(
            urlBase: rtrim(config('inpe.url_base'), '/'),
            timeout: config('inpe.timeout'),
        ));

        $this->app->bind(LeitorCsvFocos::class, fn () => new LeitorCsvFocos(
            estado: config('inpe.estado'),
            sateliteReferencia: config('inpe.satelite_referencia'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
