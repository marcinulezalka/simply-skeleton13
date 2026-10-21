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
 * Komenda admiro:account:create: aktywne konto bez PIN-u, odrzucenie duplikatu i błędnych danych.
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

    public function test_it_rejects_an_invalid_address(): void
    {
        $this->createAccount('not-an-email', 'Ewa Admin')->assertFailed();

        $this->assertSame(0, AdmiroAccount::query()->count());
    }

    private function createAccount(string $email, string $name): PendingCommand
    {
        $command = $this->artisan('admiro:account:create', ['email' => $email, 'name' => $name]);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }
}
