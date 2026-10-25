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

use Carbon\CarbonInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Simplysmart\Admiro\Mail\AccountLockedMail;
use Simplysmart\Admiro\Zones\ZoneCacheKey;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Zabezpieczenia logowania: stopniowe blokady bez zerowania licznika, limity niezależne od IP,
 * aktualizacja hasha PIN-u, unieważnianie sesji epoką i dezaktywacją, czyszczenie kodów.
 */
class SignInProtectionTest extends SignInTestCase
{
    public function test_wrong_pins_lock_the_account_and_notify_the_owner(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->reachPinStep();

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->submitPin('000000')->assertSessionHasErrors(['pin' => text('sign_in.failure')]);
        }

        $this->submitPin('000000')->assertRedirect('/alpha/signin')->assertSessionHasErrors(['email' => text('sign_in.failure')]);

        $account->refresh();
        $this->assertTrue($account->isLocked());
        $this->assertSame(1, $account->lockLevel());
        Mail::assertQueued(AccountLockedMail::class, fn(AccountLockedMail $mail): bool => $mail->hasTo(self::EMAIL));
    }

    public function test_after_a_lock_each_wrong_pin_locks_for_longer(): void
    {
        $account = $this->account(AlphaAccount::class, ['pin_failed_attempts' => 5, 'lock_level' => 1], self::PIN);

        $this->reachPinStep();
        $this->submitPin('000000')->assertRedirect('/alpha/signin');

        $account->refresh();
        $this->assertSame(2, $account->lockLevel());
        $this->assertTrue($account->isLocked());
        $until = $account->getAttribute('locked_until');
        $this->assertInstanceOf(CarbonInterface::class, $until);
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($until), 1);
    }

    public function test_successful_sign_in_resets_failures_and_lock_level(): void
    {
        $account = $this->account(AlphaAccount::class, ['pin_failed_attempts' => 3, 'lock_level' => 2], self::PIN);

        $this->reachPinStep();
        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');

        $account->refresh();
        $this->assertSame(0, $account->failedPinAttempts());
        $this->assertSame(0, $account->lockLevel());
    }

    public function test_outdated_pin_hash_is_upgraded_on_sign_in(): void
    {
        $account = $this->account(AlphaAccount::class, ['pin_hash' => Hash::make(self::PIN, ['rounds' => 5])]);
        $oldHash = $account->pinHash();

        $this->reachPinStep();
        $this->submitPin(self::PIN)->assertRedirect('/alpha/dashboard');

        $newHash = $account->fresh()?->pinHash();
        $this->assertNotSame($oldHash, $newHash);
        $this->assertTrue(Hash::check(self::PIN, (string) $newHash));
    }

    public function test_code_requests_for_one_address_are_limited_across_ip_addresses(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);

        for ($i = 1; $i <= 10; $i++) {
            $this->requestCode(server: ['REMOTE_ADDR' => "198.51.100.{$i}"])->assertSessionHasNoErrors();
        }

        $this->requestCode(server: ['REMOTE_ADDR' => '198.51.100.99'])->assertSessionHasErrors('email');
    }

    public function test_limiter_keys_are_prefixed_with_the_zone(): void
    {
        $this->requestCode();

        $key = ZoneCacheKey::make('alpha', 'auth', 'account_code_requests', hash('sha256', self::EMAIL));
        $this->assertSame(1, $this->app->make(RateLimiter::class)->attempts($key));
    }

    public function test_bumping_the_epoch_signs_out_every_session(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $account->forceFill(['auth_epoch' => 1])->save();

        $this->get('/alpha/dashboard')->assertRedirect('/alpha/signin');
        $this->assertGuest('alpha');
    }

    public function test_deactivated_account_is_signed_out(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $this->signedIn($account, 'alpha');

        $account->forceFill(['is_active' => false])->save();

        $this->get('/alpha/dashboard')->assertRedirect('/alpha/signin');
        $this->assertGuest('alpha');
    }

    public function test_prune_removes_old_codes(): void
    {
        $this->account(AlphaAccount::class, pin: self::PIN);
        $this->requestCode();
        $this->travel(25)->hours();

        Artisan::call('admiro:auth:prune');

        $this->assertSame(0, DB::table('alpha_login_challenges')->count());
    }

    private function reachPinStep(): void
    {
        $this->requestCode();
        $this->submitCode($this->sentCode())->assertRedirect('/alpha/signin/pin');
    }
}
