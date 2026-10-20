<?php

declare(strict_types=1);

namespace Tests\Feature\Zones;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Tests\Fixtures\Zones\AlphaZoneServiceProvider;
use Tests\Fixtures\Zones\BetaZoneServiceProvider;
use Tests\Fixtures\Zones\OpenZoneServiceProvider;
use Tests\TestCase;

/**
 * Baza testów stref: rejestruje strefy testowe alpha i beta (z logowaniem) oraz open (publiczną).
 *
 * Strefy istnieją wyłącznie w testach; skeleton i mainboard nie mają własnej strefy.
 */
abstract class ZoneTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->configureZones();

        foreach ([AlphaZoneServiceProvider::class, BetaZoneServiceProvider::class, OpenZoneServiceProvider::class] as $provider) {
            $this->registerZone($provider);
        }
    }

    /**
     * Miejsce na config potrzebny przed rejestracją stref (np. włączone języki).
     */
    protected function configureZones(): void {}

    /**
     * Rejestruje provider strefy w uruchomionej już aplikacji i odświeża indeks nazw tras.
     *
     * @param class-string<ServiceProvider>|ServiceProvider $provider
     */
    protected function registerZone(string|ServiceProvider $provider): void
    {
        $this->app->register($provider);

        $routes = $this->app->make(Router::class)->getRoutes();
        $routes->refreshNameLookups();
        $routes->refreshActionLookups();
    }
}
