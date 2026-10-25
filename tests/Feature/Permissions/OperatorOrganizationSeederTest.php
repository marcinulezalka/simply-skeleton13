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

namespace Tests\Feature\Permissions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Simplysmart\Admiro\Models\Organization;
use Tests\TestCase;

/**
 * Organizacja operatora aplikacji (`admiro:organizations:seed`): stałe id, nazwa z app.name, idempotentnie.
 */
class OperatorOrganizationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_operator_organization_once(): void
    {
        config(['app.name' => 'Simplysmart']);

        $this->seedOperator();
        $this->seedOperator();

        $operator = Organization::query()->sole();
        $this->assertSame(Organization::OPERATOR_ID, $operator->id);
        $this->assertSame('Simplysmart', $operator->name);
        $this->assertTrue($operator->is_active);
        $this->assertTrue($operator->is_default);
    }

    public function test_keeps_a_renamed_operator_and_restores_a_deleted_one(): void
    {
        $this->seedOperator();
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $operator->forceFill(['name' => 'Operator'])->save();
        $operator->delete();

        $this->seedOperator();

        $this->assertSame('Operator', Organization::query()->findOrFail(Organization::OPERATOR_ID)->name);
    }

    private function seedOperator(): void
    {
        $command = $this->artisan('admiro:organizations:seed');
        $this->assertInstanceOf(PendingCommand::class, $command);
        $command->assertSuccessful()->run();
    }
}
