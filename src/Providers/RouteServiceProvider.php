<?php

namespace Innoboxrr\LocaleGenerator\Providers;

use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Hereda de Illuminate\Support\ServiceProvider y no del RouteServiceProvider de
 * Foundation: ese guarda en una propiedad estatica el callback con el que la
 * aplicacion carga sus rutas, y cualquier subclase lo vuelve a ejecutar.
 */
class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Cuando las rutas estan cacheadas no hay que volver a registrarlas.
        if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
            return;
        }

        $this->mapApiRoutes();
    }

    protected function mapApiRoutes(): void
    {
        foreach (glob(__DIR__ . '/../../routes/api/models/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            Route::middleware('api')
                ->prefix('api/innoboxrr/localegenerator/' . $name)
                ->as('api.innoboxrr.localegenerator.' . $name . '.')
                ->namespace('Innoboxrr\LocaleGenerator\Http\Controllers')
                ->group($file);
        }
    }
}
