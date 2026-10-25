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

use Simplysmart\Admiro\Contracts\CurrentZone;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Izolacja stref: własne ciasteczko i tabela sesji, osobny guard, brak współdzielonego tokenu XSRF
 * i brak stanu strefy po zakończeniu żądania.
 */
class ZoneIsolationTest extends ZoneTestCase
{
    public function test_login_zone_uses_its_own_strict_secure_session_cookie(): void
    {
        $cookie = $this->get('/alpha')->assertOk()->getCookie('alpha_session', false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', $cookie->getSameSite());
    }

    public function test_public_zone_uses_its_own_lax_session_cookie(): void
    {
        $cookie = $this->get('/open')->assertOk()->getCookie('open_session', false);

        $this->assertNotNull($cookie);
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_zone_session_settings_apply_only_during_the_request(): void
    {
        $base = config('session');

        $this->get('/alpha/session')->assertOk()->assertJson([
            'cookie' => 'alpha_session',
            'table' => 'alpha_sessions',
            'lifetime' => 30,
            'expire_on_close' => true,
            'encrypt' => true,
        ]);

        $this->assertSame($base, config('session'));
    }

    public function test_zones_do_not_set_the_shared_xsrf_cookie(): void
    {
        $this->get('/alpha')->assertCookieMissing('XSRF-TOKEN');
    }

    public function test_zone_account_reaches_its_zone_routes(): void
    {
        $account = $this->account(AlphaAccount::class);
        $id = $account->getKey();
        $this->assertIsInt($id);

        $this->signedIn($account, 'alpha')
            ->get('/alpha/dashboard')
            ->assertOk()
            ->assertSee('alpha dashboard:' . $id);
    }

    public function test_zone_account_is_a_guest_in_another_zone(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha')
            ->get('/beta/dashboard')
            ->assertRedirect('/beta/signin');
    }

    public function test_guest_is_sent_to_the_sign_in_of_the_zone(): void
    {
        $this->get('/alpha/dashboard')->assertRedirect('/alpha/signin');
    }

    public function test_the_current_zone_does_not_outlive_the_request(): void
    {
        $this->get('/alpha')->assertOk();

        $this->assertFalse($this->app->make(CurrentZone::class)->has());
        $this->assertSame('web', $this->app->make('auth')->getDefaultDriver());
    }
}
