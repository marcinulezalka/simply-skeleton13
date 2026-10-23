<?php

declare(strict_types=1);

/**
 * This file is part of simplysmart/skeleton13.
 *
 * Copyright (c) 2026 Marcin Ulezalka <marcin.ulezalka@simplysmart.pl>
 *
 * Licensed under the Simplysmart Restricted Source License 1.0.
 * See the LICENSE file distributed with this source code.
 */

namespace Tests\Feature\Zones;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\PendingCommand;
use Simplysmart\Mainboard\Auth\SignIn\CompleteSignIn;
use Simplysmart\Mainboard\Contracts\CodeAndPinAccount;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Simplysmart\Mainboard\Database\ZoneTables;
use Simplysmart\Mainboard\Zones\ZoneSessionConfigurator;
use Simplysmart\Mainboard\Zones\ZoneSessionManager;
use Tests\Fixtures\Zones\AlphaZoneServiceProvider;
use Tests\Fixtures\Zones\BetaZoneServiceProvider;
use Tests\Fixtures\Zones\OpenZoneServiceProvider;
use Tests\TestCase;

/**
 * Baza testów stref: rejestruje strefy testowe alpha i beta (z logowaniem) oraz open (publiczną)
 * i tworzy tabele stref z logowaniem schematem Core (SQLite w pamięci). Migracje Core zakładają tabele
 * prawdziwych stref (np. admiro), bo ich paczki też są zarejestrowane.
 *
 * Strefy istnieją wyłącznie w testach; skeleton i mainboard nie mają własnej strefy.
 */
abstract class ZoneTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureZones();

        foreach (['alpha', 'beta'] as $zone) {
            $this->app->make(ZoneTables::class)->create($zone);
        }

        foreach ([AlphaZoneServiceProvider::class, BetaZoneServiceProvider::class, OpenZoneServiceProvider::class] as $provider) {
            $this->registerZone($provider);
        }
    }

    /**
     * Miejsce na config potrzebny przed rejestracją stref (np. włączone języki).
     */
    protected function configureZones(): void {}

    /**
     * Konto strefy zapisane w bazie; PIN podany jawnie trafia do bazy jako hash.
     *
     * @template TAccount of Model&CodeAndPinAccount
     *
     * @param class-string<TAccount> $model
     * @param array<string, mixed> $attributes
     *
     * @return TAccount
     */
    protected function account(string $model, array $attributes = [], ?string $pin = null): Model
    {
        $account = new $model();
        $account->forceFill([
            'name' => 'Anna Nowak',
            'email' => 'anna@example.com',
            'pin_hash' => $pin === null ? null : Hash::make($pin),
            'is_active' => true,
            ...$attributes,
        ])->save();

        return $account;
    }

    /**
     * Zalogowane konto strefy (guard o nazwie strefy) z epoką w sesji strefy, jak po CompleteSignIn.
     */
    protected function signedIn(Model&CodeAndPinAccount $account, string $zone): static
    {
        $sessions = $this->app->make(ZoneSessionConfigurator::class);

        $sessions->apply($this->app->make(ZoneRegistry::class)->get($zone));
        $manager = $this->app->make('session');
        $this->assertInstanceOf(ZoneSessionManager::class, $manager);
        $manager->driver()->put(CompleteSignIn::EPOCH_SESSION_KEY, $account->authEpoch());
        $sessions->restore();

        return $this->actingAs($account, $zone);
    }

    /**
     * Pierwszy błąd pola w sesji strefy po żądaniu zalogowanego konta. TestResponse::assertSessionHasErrors()
     * startuje magazyn sesji strefy od nowa i nie widzi wtedy błędów, więc testy kont zalogowanych czytają je tu.
     */
    protected function sessionError(string $field): string
    {
        $errors = session('errors');

        return $errors instanceof ViewErrorBag ? (string) $errors->first($field) : '';
    }

    protected function assertSessionError(string $field, string $message): void
    {
        $this->assertSame($message, $this->sessionError($field));
    }

    /**
     * Klucz trasy (token) konta.
     */
    protected function routeKey(Model $model): string
    {
        $key = $model->getRouteKey();
        $this->assertIsString($key);

        return $key;
    }

    /**
     * Komenda Artisana z asercjami (PendingCommand).
     *
     * @param array<string, mixed> $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        return $pending instanceof PendingCommand ? $pending : throw new \LogicException('Artisan commands must be mocked in tests.');
    }

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
