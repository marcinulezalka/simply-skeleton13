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
use Illuminate\Support\Facades\Mail;
use Simplysmart\Mainboard\Auth\AccountAdministration;
use Simplysmart\Mainboard\Mail\DeviceTokenReusedMail;
use Simplysmart\Mainboard\Mail\LoginCodeMail;
use Simplysmart\Mainboard\Mail\NewTrustedDeviceMail;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Zaufane urządzenia: zapamiętanie po kodzie z e-maila, logowanie bez kodu (zawsze z PIN-em), rotacja
 * validatora, wykrycie skopiowanego tokenu, granice zaufania (inne konto, wygaśnięcie, inna przeglądarka,
 * limit) i panel urządzeń konta.
 */
class TrustedDeviceTest extends SignInTestCase
{
    private const string COOKIE = 'alpha_device';

    public function test_remembered_device_skips_the_code_but_still_needs_the_pin(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();

        $this->assertDatabaseCount('alpha_trusted_devices', 1);
        Mail::assertQueued(NewTrustedDeviceMail::class, fn(NewTrustedDeviceMail $mail): bool => $mail->hasTo(self::EMAIL));

        $this->post('/alpha/signin/out');
        $this->withCookie(self::COOKIE, $cookie)->requestCode()->assertRedirect('/alpha/signin/pin');
        Mail::assertSent(LoginCodeMail::class, 1);

        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');
        $this->assertAuthenticatedAs($account, 'alpha');
    }

    public function test_remember_device_is_a_switch_on_the_code_screen(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->requestCode();

        $this->get('/alpha/signin/code')->assertOk()
            ->assertSee('<input type="checkbox" role="switch" id="remember_device" name="remember_device" value="1"', false);
    }

    public function test_device_is_remembered_only_on_request(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $this->submitCode($this->sentCode());
        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard')->assertCookieMissing(self::COOKIE);

        $this->assertDatabaseCount('alpha_trusted_devices', 0);
    }

    public function test_validator_is_stored_as_a_hash_and_rotated_on_each_use(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        [$selector, $validator] = explode('.', $cookie);

        $this->assertDatabaseMissing('alpha_trusted_devices', ['validator_hash' => $validator]);
        $this->post('/alpha/signin/out');
        $response = $this->withCookie(self::COOKIE, $cookie)->requestCode();
        $rotated = (string) $response->getCookie(self::COOKIE)?->getValue();

        $this->assertStringStartsWith($selector . '.', $rotated);
        $this->assertNotSame($cookie, $rotated);
    }

    public function test_reused_old_token_revokes_access_and_warns_the_owner(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        $this->post('/alpha/signin/out');
        $this->withCookie(self::COOKIE, $cookie)->requestCode();

        $this->withCookie(self::COOKIE, $cookie)->requestCode()->assertRedirect('/alpha/signin/code');

        $this->assertSame(0, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
        $this->assertSame(1, $account->fresh()?->authEpoch());
        Mail::assertQueued(DeviceTokenReusedMail::class, fn(DeviceTokenReusedMail $mail): bool => $mail->hasTo(self::EMAIL));
    }

    public function test_device_of_another_account_is_ignored(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->account(AlphaAccount::class, ['email' => 'other@example.com'], self::PIN);
        $cookie = $this->signInRemembering();
        $this->post('/alpha/signin/out');

        $this->withCookie(self::COOKIE, $cookie)->requestCode('other@example.com')->assertRedirect('/alpha/signin/code');

        $this->assertSame(1, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
    }

    public function test_trust_expires_without_being_extended(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        $this->post('/alpha/signin/out');

        $this->travel(31)->days();

        $this->withCookie(self::COOKIE, $cookie)->requestCode()->assertRedirect('/alpha/signin/code');
    }

    public function test_another_browser_ends_the_trust(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131.0')->signInRemembering();
        $this->post('/alpha/signin/out');

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36')
            ->withCookie(self::COOKIE, $cookie)
            ->requestCode()
            ->assertRedirect('/alpha/signin/code');

        $this->assertNotNull(DB::table('alpha_trusted_devices')->value('revoked_at'));
    }

    public function test_trusted_path_cannot_remember_another_device(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        $this->post('/alpha/signin/out');

        $this->withCookie(self::COOKIE, $cookie)->requestCode();
        $this->post('/alpha/signin/code', ['code' => '000000', 'remember_device' => '1'])->assertRedirect('/alpha/signin');
        $this->assertDatabaseCount('alpha_trusted_devices', 1);
    }

    public function test_oldest_device_is_revoked_over_the_limit(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        foreach (range(1, 11) as $sign) {
            $this->travel(7)->minutes();
            $this->signInRemembering();
            $this->post('/alpha/signin/out');
        }

        $this->assertSame(10, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
    }

    public function test_pin_reset_and_lock_revoke_every_device(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signInRemembering();

        $this->app->make(AccountAdministration::class)->resetPin($account);

        $this->assertSame(0, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
    }

    public function test_wrong_pins_on_a_trusted_device_lock_the_account_and_end_the_trust(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        $this->post('/alpha/signin/out');

        $this->withCookie(self::COOKIE, $cookie)->requestCode();

        foreach (range(1, 5) as $attempt) {
            $this->submitPin('000000');
        }

        $this->assertSame(0, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
    }

    public function test_device_cookie_flags(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        $this->requestCode();
        $this->post('/alpha/signin/code', ['code' => $this->sentCode(), 'remember_device' => '1']);
        $cookie = $this->submitPin(self::PIN)->getCookie(self::COOKIE, decrypt: false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', $cookie->getSameSite());
        $this->assertIsString($cookie->getValue());
        $this->assertStringNotContainsString('.', $cookie->getValue());
    }

    public function test_account_lists_and_revokes_its_devices(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $cookie = $this->signInRemembering();
        $token = $this->deviceToken();

        $this->withCookie(self::COOKIE, $cookie)->get('/alpha/account/devices')->assertOk()->assertSee(text('account.this_device'));
        $this->delete('/alpha/account/devices/' . $token)->assertRedirect('/alpha/account/devices')->assertCookieExpired(self::COOKIE);

        $this->assertNotNull(DB::table('alpha_trusted_devices')->value('revoked_at'));
        $this->assertAuthenticatedAs($account, 'alpha');
    }

    public function test_account_cannot_revoke_a_device_of_another_account(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signInRemembering();
        $token = $this->deviceToken();
        $this->post('/alpha/signin/out');

        $other = $this->account(AlphaAccount::class, ['email' => 'other@example.com'], self::PIN);
        $this->signedIn($other, 'alpha')->delete('/alpha/account/devices/' . $token)->assertNotFound();

        $this->assertNull(DB::table('alpha_trusted_devices')->value('revoked_at'));
    }

    public function test_account_revokes_all_its_devices(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signInRemembering();
        $this->post('/alpha/signin/out');
        $this->signInRemembering();

        $this->delete('/alpha/account/devices')->assertRedirect('/alpha/account/devices');

        $this->assertSame(0, DB::table('alpha_trusted_devices')->whereNull('revoked_at')->count());
    }

    public function test_prune_removes_inactive_devices(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signInRemembering();
        $this->travel(31)->days();

        $this->command('mainboard:auth:prune')->assertSuccessful();

        $this->assertDatabaseCount('alpha_trusted_devices', 0);
    }

    public function test_zone_can_require_the_code_every_time(): void
    {
        $account = $this->account(BetaAccount::class, pin: self::PIN);

        $this->post('/beta/signin', ['email' => self::EMAIL]);
        $this->get('/beta/signin/code')->assertOk()->assertDontSee('remember_device');
        $this->post('/beta/signin/code', ['code' => $this->sentCode(), 'remember_device' => '1']);
        $this->post('/beta/signin/pin', ['pin' => self::PIN])->assertRedirect('/beta/dashboard')->assertCookieMissing('beta_device');

        $this->assertDatabaseCount('beta_trusted_devices', 0);
        $this->signedIn($account, 'beta')->get('/beta/account/devices')->assertNotFound();
    }

    /**
     * Token (klucz w URL panelu) jedynego zapamiętanego urządzenia.
     */
    private function deviceToken(): string
    {
        $token = DB::table('alpha_trusted_devices')->value('token');
        $this->assertIsString($token);

        return $token;
    }

    /**
     * Pełne logowanie z zapamiętaniem urządzenia; zwraca wartość ciasteczka urządzenia (selector.validator).
     */
    private function signInRemembering(): string
    {
        $this->requestCode();
        $this->post('/alpha/signin/code', ['code' => $this->sentCode(), 'remember_device' => '1'])->assertRedirect('/alpha/signin/pin');
        $response = $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');

        return (string) $response->getCookie(self::COOKIE)?->getValue();
    }
}
