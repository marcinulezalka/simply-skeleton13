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

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Simplysmart\Mainboard\Auth\AccessRevocationReason;
use Simplysmart\Mainboard\Auth\AccountActor;
use Simplysmart\Mainboard\Auth\AccountAdministration;
use Simplysmart\Mainboard\Auth\Events\AccountAccessRevoked;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Simplysmart\Mainboard\Zones\ZoneDefinition;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Akcje administratora na kontach strefy: reset PIN-u, odblokowanie i dezaktywacja unieważniają sesje
 * (epoka, zapisane sesje) w strefie konta i są zapisywane z aktorem, także gdy administrator działa z innej strefy.
 */
class AccountAdministrationTest extends SignInTestCase
{
    public function test_pin_reset_removes_the_pin_and_signs_out_every_session(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        DB::table('alpha_sessions')->insert(['id' => 'stored', 'user_id' => $account->getKey(), 'payload' => '', 'last_activity' => 0]);

        $this->administration()->resetPin($account);
        $account->refresh();

        $this->assertFalse($account->hasPin());
        $this->assertSame(1, $account->authEpoch());
        $this->assertDatabaseMissing('alpha_sessions', ['id' => 'stored']);
    }

    public function test_unlock_clears_the_lock_and_the_failures(): void
    {
        $account = $this->account(AlphaAccount::class, ['locked_until' => Carbon::now()->addHour(), 'lock_level' => 2, 'pin_failed_attempts' => 5], self::PIN);

        $this->administration()->unlock($account);

        $fresh = $account->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->canSignIn());
        $this->assertSame(0, $fresh->lockLevel());
        $this->assertSame(0, $fresh->failedPinAttempts());
    }

    public function test_deactivated_account_cannot_sign_in(): void
    {
        $account = $this->account(AlphaAccount::class, pin: self::PIN);

        $this->administration()->deactivate($account);

        $this->assertFalse($account->fresh()?->canSignIn());
    }

    public function test_actions_record_the_reason_and_the_actor_from_another_zone(): void
    {
        Event::fake([AccountAccessRevoked::class]);
        $account = $this->account(AlphaAccount::class, pin: self::PIN);
        $admin = $this->account(BetaAccount::class, ['email' => 'admin@example.com'], self::PIN);

        $this->administration()->deactivate($account, AccountActor::of($this->zone('beta'), $admin));

        Event::assertDispatched(AccountAccessRevoked::class, fn(AccountAccessRevoked $event): bool => $event->zone === 'alpha'
            && $event->reason === AccessRevocationReason::Deactivated
            && $event->actor?->zone === 'beta'
            && $event->actor->accountId === $admin->getKey());
    }

    public function test_model_outside_every_zone_is_rejected(): void
    {
        $this->expectException(LogicException::class);

        $this->administration()->resetPin(new class extends AlphaAccount {});
    }

    private function administration(): AccountAdministration
    {
        return $this->app->make(AccountAdministration::class);
    }

    private function zone(string $name): ZoneDefinition
    {
        return $this->app->make(ZoneRegistry::class)->get($name);
    }
}
