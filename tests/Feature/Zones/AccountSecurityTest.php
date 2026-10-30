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

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Strona bezpieczeństwa konta: zasady logowania strefy, PIN, zaufane urządzenia i ostatnie zdarzenia konta (bez adresów IP i bez zdarzeń
 * innych kont), wylogowanie ze wszystkich urządzeń oraz dawne adresy sekcji przekierowujące do kotwic.
 */
class AccountSecurityTest extends SignInTestCase
{
    public function test_page_has_the_pin_form_devices_history_and_sign_out_everywhere(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->account(AlphaAccount::class, ['email' => 'other@example.com', 'name' => 'Ola'], self::PIN);
        $this->signIn();

        $html = (string) $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/alpha/account/security')->assertOk()->getContent();

        $this->assertStringContainsString('id="pin"', $html);
        $this->assertStringContainsString('action="' . url('/alpha/account/pin') . '"', $html);
        $this->assertStringContainsString('id="devices"', $html);
        $this->assertStringContainsString('<section class="ss-card ss-security-lock" id="devices"', $html);
        $this->assertStringContainsString('<section class="ss-card ss-card--inverse ss-security-lock" id="lock"', $html);
        $this->assertStringContainsString('action="' . url('/alpha/account/security/sessions') . '"', $html);
        $this->assertStringContainsString(text('audit.events.sign_in'), $html);
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertSame(1, substr_count($html, text('audit.events.sign_in')));
    }

    public function test_page_explains_the_zone_sign_in_rules(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signIn();
        $pin = $this->app->make(\Simplysmart\Admiro\Contracts\ZoneRegistry::class)->get('alpha')->auth()->pinLength;

        $this->get('/alpha/account/security')
            ->assertOk()
            ->assertSee(text('account.sign_in_policy_title'))
            ->assertSee(text('account.sign_in_policy_devices', ['min' => $pin->min, 'max' => $pin->max]));
    }

    public function test_sign_out_everywhere_ends_every_session_and_returns_to_sign_in(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signIn();
        $epoch = $account->fresh()?->authEpoch();

        $this->delete('/alpha/account/security/sessions')
            ->assertRedirect('/alpha/signin')
            ->assertSessionHas('status', text('account.signed_out_everywhere'));

        $this->assertGuest('alpha');
        $this->assertNotSame($epoch, $account->fresh()?->authEpoch());
        $context = DB::table('audit_events')->where('event', 'access revoked')->value('context');
        $this->assertSame(['reason' => 'signed_out_everywhere'], is_string($context) ? json_decode($context, true) : null);
        $this->get('/alpha/account/security')->assertRedirect('/alpha/signin');
    }

    public function test_old_section_addresses_lead_to_the_security_page(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->get('/alpha/account/pin')->assertRedirect(url('/alpha/account/security') . '#pin');
        $this->get('/alpha/account/devices')->assertRedirect(url('/alpha/account/security') . '#devices');
    }

    public function test_account_menu_links_profile_and_security(): void
    {
        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/account/security')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~href="' . preg_quote(url('/alpha/account/security'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringContainsString(url('/alpha/account/profile'), $html);
    }

    private function signIn(): void
    {
        $this->requestCode();
        $this->submitCode($this->sentCode());
        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');
    }
}
