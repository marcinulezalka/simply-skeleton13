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

use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Język konta przy kilku włączonych językach: wybór w profilu wraca na profil w tym języku, a po logowaniu
 * aplikacja otwiera się w języku konta.
 */
class AccountLocaleTest extends SignInTestCase
{
    protected function configureZones(): void
    {
        config(['mainboard.locale.enabled' => ['pl', 'en']]);
    }

    public function test_chosen_language_opens_the_profile_and_the_next_sign_in_in_it(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $this->get('/pl/alpha/account/profile')->assertOk()->assertSee('name="locale"', false)->assertSee('<option value="en"', false);
        $this->put('/pl/alpha/account/profile', ['name' => 'Anna Nowak', 'avatar_color' => 'teal', 'locale' => 'en'])
            ->assertRedirect(url('/en/alpha/account/profile'));
        $this->assertSame('en', ($account->fresh()->json_attributes ?? [])['locale'] ?? null);

        $this->post('/pl/alpha/signin/out');
        $this->post('/pl/alpha/signin', ['email' => self::EMAIL]);
        $this->post('/pl/alpha/signin/code', ['code' => $this->sentCode()]);
        $this->post('/pl/alpha/signin/pin', ['pin' => self::PIN])->assertRedirect(url('/en/alpha/dashboard'));
    }
}
