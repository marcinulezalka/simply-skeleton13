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

use Illuminate\Foundation\Testing\RefreshDatabase;
use Simplysmart\Admiro\Models\AdmiroAccount;
use Illuminate\Testing\PendingCommand;
use Simplysmart\Mainboard\Contracts\CurrentZone;
use Tests\TestCase;

/**
 * Komenda admiro:account:create: aktywne konto bez PIN-u, odrzucenie duplikatu (także konta usuniętego),
 * pominięcie duplikatu z --skip-existing i odrzucenie błędnych danych.
 */
class CreateAdmiroAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_account_without_a_pin(): void
    {
        $this->createAccount(' Ewa@Example.com ', 'Ewa Admin')
            ->assertSuccessful();

        $account = AdmiroAccount::query()->sole();
        $this->assertSame('ewa@example.com', $account->emailAddress());
        $this->assertSame('Ewa Admin', $account->displayName());
        $this->assertTrue($account->canSignIn());
        $this->assertFalse($account->hasPin());
        $this->assertFalse($this->app->make(CurrentZone::class)->has());
    }

    public function test_it_rejects_an_address_that_already_has_an_account(): void
    {
        $this->createAccount('ewa@example.com', 'Ewa Admin')->assertSuccessful();

        $this->createAccount('EWA@example.com', 'Inna Ewa')
            ->expectsOutput('Konto z tym adresem e-mail już istnieje.')
            ->assertFailed();

        $this->assertSame(1, AdmiroAccount::query()->count());
        $this->assertFalse($this->app->make(CurrentZone::class)->has());
    }

    public function test_skip_existing_leaves_the_account_and_succeeds(): void
    {
        $this->createAccount('ewa@example.com', 'Ewa Admin')->assertSuccessful();

        $this->createAccount('EWA@example.com', 'Inna Ewa', ['--skip-existing' => true])
            ->expectsOutput('Konto z tym adresem e-mail już istnieje — pominięte.')
            ->assertSuccessful();

        $this->assertSame('Ewa Admin', AdmiroAccount::query()->sole()->displayName());
        $this->assertFalse($this->app->make(CurrentZone::class)->has());
    }

    public function test_a_deleted_account_keeps_its_address_taken(): void
    {
        $this->createAccount('ewa@example.com', 'Ewa Admin')->assertSuccessful();
        AdmiroAccount::query()->sole()->delete();

        $this->createAccount('ewa@example.com', 'Nowa Ewa')
            ->expectsOutput('Konto z tym adresem e-mail już istnieje.')
            ->assertFailed();

        $this->assertSame(0, AdmiroAccount::query()->count());
        $this->assertSame(1, AdmiroAccount::withTrashed()->count());
    }

    public function test_it_rejects_an_invalid_address(): void
    {
        $this->createAccount('not-an-email', 'Ewa Admin')->assertFailed();

        $this->assertSame(0, AdmiroAccount::query()->count());
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function createAccount(string $email, string $name, array $options = []): PendingCommand
    {
        $command = $this->artisan('admiro:account:create', ['email' => $email, 'name' => $name, ...$options]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
