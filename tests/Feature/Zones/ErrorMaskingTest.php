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

use Simplysmart\Mainboard\Contracts\CurrentZone;
use Simplysmart\Mainboard\Security\ErrorMasker;
use Simplysmart\Mainboard\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Maskowanie błędów (domyślnie na produkcji): brak dostępu i inne maskowane statusy wyglądają jak 404,
 * strefa może to nadpisać, a wygasły formularz w strefie z logowaniem dalej wraca do logowania.
 * Agenci AI dostają 403 w strefach z logowaniem.
 */
class ErrorMaskingTest extends ZoneTestCase
{
    public function test_forbidden_looks_like_not_found_when_masking(): void
    {
        $account = $this->account(AlphaAccount::class);

        $this->signedIn($account, 'alpha')->get('/alpha/reports')->assertForbidden();

        config(['mainboard.security.mask_errors' => true]);

        $this->signedIn($account, 'alpha')->get('/alpha/reports')->assertNotFound();
    }

    public function test_masking_is_on_in_production_by_default(): void
    {
        $this->assertFalse($this->app->make(ErrorMasker::class)->masks(403));

        $this->app['env'] = 'production';

        $this->assertTrue($this->app->make(ErrorMasker::class)->masks(403));
        $this->assertFalse($this->app->make(ErrorMasker::class)->masks(503));
    }

    public function test_zone_overrides_the_global_setting(): void
    {
        config(['mainboard.security.mask_errors' => true]);
        $zone = $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop', 'security' => ['mask_errors' => false]], 'shop', __DIR__);

        $masks = $this->app->make(CurrentZone::class)->within($zone, fn(): bool => $this->app->make(ErrorMasker::class)->masks(403));

        $this->assertFalse($masks);
    }

    public function test_expired_form_still_returns_to_the_sign_in(): void
    {
        config(['mainboard.security.mask_errors' => true]);

        $this->post('/alpha/expired-form')->assertRedirect('/alpha/signin');
    }

    public function test_ai_agents_are_refused_in_zones_with_sign_in(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.2)')->get('/alpha/signin')->assertForbidden();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; GPTBot/1.2)')->get('/open')->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0')->get('/alpha/signin')->assertOk();
    }
}
