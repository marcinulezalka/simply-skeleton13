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

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Simplysmart\Mainboard\Auth\SignInLimit;
use Simplysmart\Mainboard\Mail\PinChangedMail;
use Simplysmart\Mainboard\Zones\ZoneCacheKey;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Zmiana PIN-u zalogowanego konta: obecny PIN i kod z e-maila, nowy PIN inny niż obecny, potem nowa epoka
 * (pozostałe sesje wylogowane), bieżąca sesja zostaje i właściciel dostaje potwierdzenie.
 */
class PinChangeTest extends SignInTestCase
{
    private const string NEW_PIN = '284619';

    public function test_account_changes_its_pin_with_the_current_pin_and_an_e_mailed_code(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $this->post('/alpha/account/pin/code')->assertRedirect('/alpha/account/pin');
        $this->changePin(self::PIN, $this->sentCode(), self::NEW_PIN)
            ->assertRedirect('/alpha/account/pin')
            ->assertSessionHas('status', text('account.pin_changed'));

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue(Hash::check(self::NEW_PIN, (string) $fresh->pinHash()));
        $this->assertSame(1, $fresh->authEpoch());
        $this->get('/alpha/dashboard')->assertOk();
        Mail::assertQueued(PinChangedMail::class, fn(PinChangedMail $mail): bool => $mail->hasTo(self::EMAIL));
    }

    public function test_wrong_current_pin_is_rejected_and_counted(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $this->post('/alpha/account/pin/code');
        $this->changePin('951357', $this->sentCode(), self::NEW_PIN);
        $account->refresh();

        $this->assertSessionError('current_pin', text('account.pin_invalid'));
        $this->assertSame(1, $account->failedPinAttempts());
        $this->assertTrue(Hash::check(self::PIN, (string) $account->pinHash()));
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha');

        $this->post('/alpha/account/pin/code');
        $this->changePin(self::PIN, $this->wrongCode($this->sentCode()), self::NEW_PIN);

        $this->assertSessionError('code', text('account.code_invalid'));
    }

    public function test_new_pin_must_differ_from_the_current_one(): void
    {
        $this->signedIn($this->account(AlphaAccount::class, pin: self::PIN), 'alpha');

        $this->post('/alpha/account/pin/code');
        $this->changePin(self::PIN, $this->sentCode(), self::PIN);

        $this->assertSessionError('pin', text('account.pin_same'));

        Mail::assertNotQueued(PinChangedMail::class);
    }

    public function test_pin_change_attempts_are_limited_per_account(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');
        $id = $account->getKey();
        $this->assertIsInt($id);
        $key = ZoneCacheKey::make('alpha', 'auth', SignInLimit::PinChanges->value, hash('sha256', (string) $id));

        foreach (range(1, 5) as $attempt) {
            RateLimiter::hit($key);
        }

        $this->post('/alpha/account/pin/code');
        $this->changePin(self::PIN, $this->sentCode(), self::NEW_PIN);

        $this->assertMatchesRegularExpression('/^' . preg_quote(explode(':seconds', text('sign_in.too_many_attempts'))[0], '/') . '\d+/', $this->sessionError('current_pin'));
        $this->assertTrue(Hash::check(self::PIN, (string) $account->fresh()?->pinHash()));
    }

    public function test_guest_cannot_open_the_pin_change(): void
    {
        $this->get('/alpha/account/pin')->assertRedirect('/alpha/signin');
    }

    /**
     * @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    private function changePin(string $currentPin, string $code, string $newPin): \Illuminate\Testing\TestResponse
    {
        return $this->put('/alpha/account/pin', ['current_pin' => $currentPin, 'code' => $code, 'pin' => $newPin, 'pin_confirmation' => $newPin]);
    }
}
