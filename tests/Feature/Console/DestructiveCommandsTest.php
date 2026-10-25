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

namespace Tests\Feature\Console;

use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\WipeCommand;
use ReflectionProperty;
use Simplysmart\Admiro\Providers\SecurityServiceProvider;
use Tests\TestCase;

/**
 * Blokada destrukcyjnych komend bazy z Core: na produkcji `migrate:fresh` i `db:wipe` są zablokowane, a zdejmuje je
 * tylko `admiro.security.allow_destructive_commands` (ustawiane przez `deploy:fresh` dla jednego procesu).
 * Komendy nie są uruchamiane — test czyta tylko flagę blokady Laravela po starcie providera.
 */
class DestructiveCommandsTest extends TestCase
{
    protected function tearDown(): void
    {
        FreshCommand::prohibit(false);
        WipeCommand::prohibit(false);

        parent::tearDown();
    }

    public function test_production_prohibits_destructive_commands(): void
    {
        $this->bootSecurityIn('production', allowDestructiveCommands: false);

        $this->assertTrue($this->prohibited(FreshCommand::class));
        $this->assertTrue($this->prohibited(WipeCommand::class));
    }

    public function test_allow_flag_lifts_the_block_in_production(): void
    {
        $this->bootSecurityIn('production', allowDestructiveCommands: true);

        $this->assertFalse($this->prohibited(FreshCommand::class));
        $this->assertFalse($this->prohibited(WipeCommand::class));
    }

    public function test_other_environments_are_not_blocked(): void
    {
        $this->bootSecurityIn('local', allowDestructiveCommands: false);

        $this->assertFalse($this->prohibited(FreshCommand::class));
    }

    public function test_flag_is_off_by_default(): void
    {
        $this->assertFalse(config('admiro.security.allow_destructive_commands'));
    }

    private function bootSecurityIn(string $environment, bool $allowDestructiveCommands): void
    {
        $this->app['env'] = $environment;
        config(['admiro.security.allow_destructive_commands' => $allowDestructiveCommands]);

        $this->app->call([new SecurityServiceProvider($this->app), 'boot']);
    }

    /**
     * @param  class-string  $command
     */
    private function prohibited(string $command): bool
    {
        return (bool) (new ReflectionProperty($command, 'prohibitedFromRunning'))->getValue();
    }
}
