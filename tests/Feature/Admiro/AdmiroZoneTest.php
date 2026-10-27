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
use Simplysmart\Admiro\Auth\PinLength;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Enums\LegalDocumentType;
use Simplysmart\Admiro\Legal\LegalDocuments;
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

    public function test_administrators_need_a_longer_pin_than_other_zones(): void
    {
        $zones = $this->app->make(ZoneRegistry::class);

        $this->assertEquals(new PinLength(8, PinLength::MAX), $zones->get('admiro')->auth()->pinLength);
        $this->assertEquals(PinLength::accepted(), $zones->get('alpha')->auth()->pinLength);
    }

    public function test_guest_is_sent_to_the_admiro_sign_in(): void
    {
        $this->get('/admiro')->assertRedirect(route('admiro.auth.login'));
        $this->get('/admiro/signin')->assertOk()->assertSee('name="email"', false);
    }

    public function test_sign_in_shows_the_form_left_and_the_admiro_logo_on_the_brand_card_right(): void
    {
        $this->get('/admiro/signin')
            ->assertOk()
            ->assertSee('class="ss-auth ss-auth--split ss-auth--reversed"', false)
            ->assertSee('<span class="ss-logo ss-auth__brand-logo"><svg role="img" aria-label="admiro"', false);
    }

    public function test_sign_in_title_highlights_precision_in_the_admiro_fonts(): void
    {
        $this->get('/admiro/signin')
            ->assertOk()
            ->assertSee('Kontrola, automatyzacja i <em class="ss-accent-text">precyzja</em> w każdym wdrożeniu', false)
            ->assertSee('font-family: "Space Grotesk"', false)
            ->assertSee('font-family: "Instrument Serif"', false);
    }

    public function test_sign_in_card_links_only_the_published_core_legal_documents(): void
    {
        $this->get('/admiro/signin')->assertOk()->assertDontSee('href="' . route('admiro.legal.privacy') . '"', false);

        $documents = $this->app->make(LegalDocuments::class);
        $documents->saveDraft(LegalDocumentType::Privacy, ['pl' => ['title' => 'Polityka prywatności', 'body' => 'Treść']]);
        $documents->publish(LegalDocumentType::Privacy, 'pl');

        $this->get('/admiro/signin')
            ->assertOk()
            ->assertSeeInOrder(['<nav class="ss-auth__legal"', 'href="' . route('admiro.legal.privacy') . '"'], false)
            ->assertDontSee('href="' . route('admiro.legal.gdpr') . '"', false);
    }

    public function test_sign_in_page_title_is_the_zone_name(): void
    {
        $this->get('/admiro/signin')
            ->assertOk()
            ->assertSee('<title>admiro</title>', false);
    }

    public function test_signed_in_administrator_sees_the_dashboard(): void
    {
        $account = $this->account(AdmiroAccount::class, ['name' => 'Ewa Admin'], '159357');

        $this->signedIn($account, 'admiro')
            ->get('/admiro')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.title'))
            ->assertSee(text('admiro::dashboard.lead'))
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
