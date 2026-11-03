<?php

namespace App\Providers;

use App\Services\ColetorClima;
use App\Services\Inpe\ClienteInpe;
use App\Services\Inpe\ColetorFocosRecentes;
use App\Services\Inpe\LeitorCsvFocos;
use App\Services\IntensidadeFoco;
use App\Services\LocalizadorMunicipio;
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

        $this->app->bind(IntensidadeFoco::class, fn () => new IntensidadeFoco(config('inpe.frp_referencia')));

        // singleton: a malha só é lida uma vez por execução
        $this->app->singleton(LocalizadorMunicipio::class, fn () => new LocalizadorMunicipio(
            database_path('data/mt-municipios.json'),
            database_path('data/municipios_sedes.json'),
        ));

        $this->app->bind(ColetorFocosRecentes::class, fn ($app) => new ColetorFocosRecentes(
            cliente: $app->make(ClienteInpe::class),
            localizador: $app->make(LocalizadorMunicipio::class),
            horasGuardadas: config('inpe.horas_recentes'),
        ));

        $this->app->bind(ColetorClima::class, fn ($app) => new ColetorClima(
            localizador: $app->make(LocalizadorMunicipio::class),
            url: config('inpe.url_clima'),
            timeout: config('inpe.timeout'),
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
