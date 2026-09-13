<?php

namespace Innoboxrr\LocaleGenerator\Tests\Feature;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as FoundationRouteServiceProvider;
use Innoboxrr\LocaleGenerator\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Una aplicacion Laravel 11 o posterior registra sus rutas con
 * RouteServiceProvider::loadRoutesUsing(), que guarda el callback en una
 * propiedad estatica del RouteServiceProvider de Foundation. Cualquier subclase
 * la comparte: si el proveedor del paquete hereda de el, vuelve a ejecutar el
 * callback y las rutas de la aplicacion se cargan otra vez.
 */
final class RouteServiceProviderTest extends TestCase
{
    private static int $appRouteLoads = 0;

    protected function getEnvironmentSetUp($app): void
    {
        self::$appRouteLoads = 0;

        FoundationRouteServiceProvider::loadRoutesUsing(static function (): void {
            self::$appRouteLoads++;
        });
    }

    protected function tearDown(): void
    {
        FoundationRouteServiceProvider::loadRoutesUsing(null);

        parent::tearDown();
    }

    #[Test]
    public function no_vuelve_a_cargar_las_rutas_de_la_aplicacion(): void
    {
        $this->assertSame(0, self::$appRouteLoads);
    }
}
