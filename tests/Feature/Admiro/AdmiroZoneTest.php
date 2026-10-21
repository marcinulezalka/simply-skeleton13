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

namespace Tests\Feature\Admiro;

use Simplysmart\Admiro\Models\AdmiroAccount;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Tests\Feature\Zones\ZoneTestCase;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Strefa administratorów admiro z paczki simplysmart/admiro: rejestracja, dostęp tylko po zalogowaniu
 * i tabele z migracji Core.
 */
class AdmiroZoneTest extends ZoneTestCase
{
    public function test_zone_is_registered_with_its_own_guard_and_fixed_route_names(): void
    {
        $zone = $this->app->make(ZoneRegistry::class)->get('admiro');

        $this->assertSame(AdmiroAccount::class, $zone->model);
        $this->assertSame(['driver' => 'session', 'provider' => 'admiro_accounts'], config('auth.guards.admiro'));
        $this->assertSame(url('/admiro'), route('admiro.dashboard'));
        $this->assertSame(url('/admiro/signin'), route('admiro.auth.login'));
    }

    public function test_guest_is_sent_to_the_admiro_sign_in(): void
    {
        $this->get('/admiro')->assertRedirect(route('admiro.auth.login'));
        $this->get('/admiro/signin')->assertOk()->assertSee('name="email"', false);
    }

    public function test_signed_in_administrator_sees_the_dashboard(): void
    {
        $account = $this->account(AdmiroAccount::class, ['name' => 'Ewa Admin'], '159357');

        $this->signedIn($account, 'admiro')
            ->get('/admiro')
            ->assertOk()
            ->assertSee('Panel administratora')
            ->assertSee('Zalogowano jako Ewa Admin.')
            ->assertSee(route('admiro.auth.logout'), false);
    }

    public function test_account_of_another_zone_does_not_open_admiro(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha')
            ->get('/admiro')
            ->assertRedirect(route('admiro.auth.login'));
    }
}
