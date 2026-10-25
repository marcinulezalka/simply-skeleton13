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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Database\Columns;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Organizations\OrganizationChangeRejected;
use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Permissions\PermissionTables;
use Simplysmart\Admiro\Queries\Organizations\OrganizationUnits;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\AlphaNote;

/**
 * Departamenty organizacji (wewnętrzna agregacja): leżą tylko bezpośrednio pod jednostką i nie mają nic pod sobą,
 * są osobnym kontekstem w przełączniku („Jednostka · Departament”), a dane czytają z jednostki — wiązanie rekordu
 * w kontekście departamentu działa w jego jednostce, nie w innej.
 */
class OrganizationDepartmentsTest extends ZoneRolesTestCase
{
    public function test_department_lies_directly_under_a_unit_and_has_nothing_under_it(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $unit = $management->create(new OrganizationData('Sklep'));
        $department = $management->createDepartment(new OrganizationData('Magazyn'), $unit);

        $this->assertTrue($department->isDepartment());
        $this->assertFalse($unit->isDepartment());
        $this->assertSame($unit->id, $department->parent_id);
        $this->assertRejected(static fn() => $management->createDepartment(new OrganizationData('Pod działem'), $department));
        $this->assertRejected(static fn() => $management->create(new OrganizationData('Oddział'), $department));
        $this->assertRejected(static fn() => $management->update($department, new OrganizationData('Magazyn'), null));
        $this->assertRejected(static fn() => $management->update($management->create(new OrganizationData('Inna')), new OrganizationData('Inna'), $department));
    }

    public function test_units_give_the_unit_of_a_department_and_its_active_departments(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $unit = $management->create(new OrganizationData('Sklep'));
        $branch = $management->create(new OrganizationData('Oddział'), $unit);
        $first = $management->createDepartment(new OrganizationData('Kadry'), $unit);
        $second = $management->createDepartment(new OrganizationData('Magazyn'), $unit);
        $management->setActive($first, false);
        $units = $this->app->make(OrganizationUnits::class);

        $this->assertSame($unit->id, $units->unitIdOf($second->id));
        $this->assertSame($unit->id, $units->unitIdOf($unit->id));
        $this->assertSame($branch->id, $units->unitIdOf($branch->id), 'A branch is a unit of its own.');
        $this->assertNull($units->unitIdOf(null));
        $this->assertSame([$second->id], $units->departments($unit)->modelKeys());
        $this->assertSame($first->id, $units->department($unit, $first->token)?->id);
        $this->assertNull($units->department($branch, $second->token));
        $this->assertSame($unit->id, $this->app->make(CurrentOrganization::class)->within($second->id, static fn(): ?int => $units->currentUnitId()));
    }

    public function test_department_context_reads_the_records_of_its_unit_and_shows_the_unit_in_the_switch(): void
    {
        Schema::create(AlphaNote::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->foreignId(PermissionTables::TEAM_FOREIGN_KEY)->constrained(Organization::TABLE);
            $table->string('title');
            Columns::closing($table);
        });
        $management = $this->app->make(OrganizationManagement::class);
        $unit = $management->create(new OrganizationData('Sklep'));
        $department = $management->createDepartment(new OrganizationData('Magazyn'), $unit);
        $account = $this->member($this->account(AlphaAccount::class), $department);
        $this->member($account, $this->organization('Druga'));

        $this->signedIn($account, 'alpha');
        $this->post('/alpha/account/organization', ['organization' => $department->token])->assertRedirect();
        $this->get('/alpha/notes/' . $this->note($unit, 'jednostki'))->assertSee('jednostki');
        $this->get('/alpha/notes/' . $this->note($this->organization('Obca'), 'obca'))->assertNotFound();
        $this->get('/alpha/account/security')->assertSee('Sklep · Magazyn');
    }

    private function note(Organization $organization, string $title): string
    {
        $note = new AlphaNote();
        $note->forceFill(['name' => $title, PermissionTables::TEAM_FOREIGN_KEY => $organization->id, 'title' => $title])->save();

        return $note->token;
    }

    /**
     * @param callable(): mixed $change
     */
    private function assertRejected(callable $change): void
    {
        try {
            $change();
        } catch (OrganizationChangeRejected) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('The organization change was not rejected.');
    }
}
