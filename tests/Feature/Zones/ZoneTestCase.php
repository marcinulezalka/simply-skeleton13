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

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\ViewErrorBag;
use Simplysmart\Admiro\Auth\SignIn\CompleteSignIn;
use Simplysmart\Admiro\Contracts\CodeAndPinAccount;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Database\ZoneTables;
use Simplysmart\Admiro\Models\ZoneAccount;
use Simplysmart\Admiro\Zones\ContextKeys;
use Simplysmart\Admiro\Zones\ZoneDefinition;
use Simplysmart\Admiro\Zones\ZoneSessionConfigurator;
use Simplysmart\Admiro\Zones\ZoneSessionManager;
use Tests\Fixtures\Zones\AlphaZoneServiceProvider;
use Tests\Fixtures\Zones\BetaZoneServiceProvider;
use Tests\Fixtures\Zones\OpenZoneServiceProvider;
use Tests\TestCase;

/**
 * Baza testów stref: rejestruje strefy testowe alpha i beta (z logowaniem) oraz open (publiczną)
 * i tworzy tabele stref z logowaniem schematem Core (SQLite w pamięci). Migracje Core zakładają tabele
 * prawdziwych stref (np. admiro), bo ich paczki też są zarejestrowane.
 *
 * Strefy istnieją wyłącznie w testach; skeleton i rdzeń admiro nie mają własnej strefy.
 *
 * Strefa z kontekstem w adresie (`context_in_url`, Zones\ContextKeys): adres testu bez klucza (`/<slug>/board`)
 * dostaje klucz bieżącego kontekstu zalogowanego konta (z sesji strefy), a po każdym żądaniu `route()` w teście
 * generuje adresy z kluczem bieżącego kontekstu — testy piszą adresy jak bez klucza; zoneUrl() daje taki adres
 * do porównań w treści i przekierowaniach.
 */
abstract class ZoneTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Konta zalogowane w strefach z kontekstem w adresie (nazwa strefy → konto).
     *
     * @var array<string, ZoneAccount>
     */
    private array $contextAccounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureZones();

        foreach (['alpha', 'beta'] as $zone) {
            $this->app->make(ZoneTables::class)->create($zone);
        }

        foreach ($this->zoneProviders() as $provider) {
            $this->registerZone($provider);
        }
    }

    /**
     * Providery stref testowych rejestrowane w setUp() (test może podmienić wariant strefy, np. alpha z kluczem w adresie).
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function zoneProviders(): array
    {
        return [AlphaZoneServiceProvider::class, BetaZoneServiceProvider::class, OpenZoneServiceProvider::class];
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

        $definition = $this->app->make(ZoneRegistry::class)->get($zone);

        if ($definition->contextInUrl && $account instanceof ZoneAccount) {
            $this->contextAccounts[$zone] = $account;
            $this->refreshContextDefaults();
        }

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

    /**
     * Pełny adres strony strefy z kluczem bieżącego kontekstu (gdy strona go wymaga), do porównań z treścią strony.
     */
    protected function zoneUrl(string $path): string
    {
        return url($this->withContextKey($path));
    }

    /**
     * @param string $uri
     */
    protected function prepareUrlForRequest($uri): string
    {
        return parent::prepareUrlForRequest($this->withContextKey($uri));
    }

    /**
     * @param array<array-key, mixed> $parameters
     * @param array<array-key, mixed> $cookies
     * @param array<array-key, mixed> $files
     * @param array<array-key, mixed> $server
     *
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        $this->refreshContextDefaults();

        return $response;
    }

    /**
     * Wstawia klucz bieżącego kontekstu po slugu strefy, gdy adres bez klucza nie ma trasy, a z kluczem ma.
     */
    private function withContextKey(string $uri): string
    {
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $segments = explode('/', ltrim($path, '/'), 2);

        foreach ($this->contextAccounts as $zone => $account) {
            $definition = $this->app->make(ZoneRegistry::class)->get($zone);

            if ($segments[0] !== $definition->slug || $this->routeExists($path)) {
                continue;
            }

            $keyed = '/' . $definition->slug . '/' . $this->currentKey($definition, $account) . (isset($segments[1]) && $segments[1] !== '' ? '/' . $segments[1] : '');

            if ($this->routeExists($keyed)) {
                return str_replace($path, $keyed, $uri);
            }
        }

        return $uri;
    }

    private function routeExists(string $path): bool
    {
        try {
            $this->app->make(Router::class)->getRoutes()->match(Request::create($path));
        } catch (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return false;
        }

        return true;
    }

    /**
     * Klucz kontekstu zapamiętanego w sesji strefy (jak po ostatnim żądaniu).
     */
    private function currentKey(ZoneDefinition $definition, ZoneAccount $account): string
    {
        $sessions = $this->app->make(ZoneSessionConfigurator::class);
        $sessions->apply($definition);
        $session = $this->app->make('session')->driver();
        $this->assertInstanceOf(Session::class, $session);
        $key = $this->app->make(ContextKeys::class)->current($definition, $account, $session);
        $sessions->restore();

        return $key;
    }

    /**
     * Domyślny parametr `context` generatora adresów — klucz bieżącego kontekstu ostatnio zalogowanego konta.
     */
    private function refreshContextDefaults(): void
    {
        foreach ($this->contextAccounts as $zone => $account) {
            $url = $this->app->make(UrlGenerator::class);
            $url->defaults([ContextKeys::PARAMETER => $this->currentKey($this->app->make(ZoneRegistry::class)->get($zone), $account)]);
        }
    }
}
