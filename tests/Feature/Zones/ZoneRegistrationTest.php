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

use Illuminate\Contracts\Foundation\Application;
use InvalidArgumentException;
use LogicException;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Simplysmart\Mainboard\Zones\ZoneServiceProvider;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Rejestracja strefy: guard i provider w config/auth, trasy z prefiksem slugu i stałym prefiksem nazw,
 * odrzucanie kolizji i błędnych konfiguracji.
 */
class ZoneRegistrationTest extends ZoneTestCase
{
    public function test_login_zone_registers_its_own_guard_and_provider(): void
    {
        $this->assertSame(['driver' => 'session', 'provider' => 'alpha_accounts'], config('auth.guards.alpha'));
        $this->assertSame(['driver' => 'eloquent', 'model' => AlphaAccount::class], config('auth.providers.alpha_accounts'));
    }

    public function test_public_zone_registers_no_guard(): void
    {
        $this->assertNull(config('auth.guards.open'));
        $this->assertFalse($this->app->make(ZoneRegistry::class)->get('open')->requiresLogin());
    }

    public function test_zone_routes_get_the_slug_prefix_and_the_fixed_name_prefix(): void
    {
        $this->assertSame(url('/alpha'), route('alpha.home'));
        $this->assertSame(url('/alpha/dashboard'), route('alpha.dashboard'));
        $this->assertSame(url('/open'), route('open.home'));
    }

    public function test_configured_slug_changes_the_url_but_not_the_route_name(): void
    {
        $this->registerZone($this->zone(['name' => 'renamed', 'slug' => 'panel/v2'], 'renamed', 'open'));

        $this->assertSame(url('/panel/v2'), route('renamed.home'));
    }

    public function test_a_zone_name_cannot_be_registered_twice(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registerZone($this->zone(['name' => 'alpha', 'slug' => 'alpha-copy'], 'alpha_copy', 'open'));
    }

    public function test_a_zone_slug_cannot_be_shared(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registerZone($this->zone(['name' => 'copy', 'slug' => 'alpha'], 'copy', 'open'));
    }

    public function test_a_zone_slug_cannot_be_a_locale_code(): void
    {
        config(['mainboard.locale.enabled' => ['pl', 'en']]);

        $this->expectException(InvalidArgumentException::class);

        $this->registerZone($this->zone(['name' => 'english', 'slug' => 'en'], 'english', 'open'));
    }

    public function test_a_zone_without_sign_in_cannot_have_routes_for_signed_in_accounts(): void
    {
        $this->expectException(LogicException::class);

        $this->registerZone($this->zone(['name' => 'broken'], 'broken', 'alpha'));
    }

    public function test_a_zone_model_must_be_authenticatable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registerZone($this->zone(['name' => 'nomodel', 'model' => self::class], 'nomodel', 'open'));
    }

    /**
     * Provider strefy zbudowany w locie z podanego configu i tras jednej ze stref testowych.
     *
     * @param array<string, mixed> $config
     */
    private function zone(array $config, string $routeNamePrefix, string $routesOf): ZoneServiceProvider
    {
        return new class ($this->app, $config, $routeNamePrefix, $routesOf) extends ZoneServiceProvider {
            /**
             * @param array<string, mixed> $config
             */
            public function __construct(Application $app, private array $config, private string $prefix, private string $routesOf)
            {
                parent::__construct($app);
            }

            protected function routeNamePrefix(): string
            {
                return $this->prefix;
            }

            protected function zoneConfig(): array
            {
                return $this->config;
            }

            protected function routesPath(): string
            {
                return base_path('tests/Fixtures/Zones/routes/' . $this->routesOf);
            }
        };
    }
}
