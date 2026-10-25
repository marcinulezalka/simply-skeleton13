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

use Illuminate\Database\QueryException;
use Simplysmart\Admiro\Contracts\ZonePermissionChecker;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Database\Seeders\OperatorOrganizationSeeder;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Models\Permissions\Permission;
use Simplysmart\Admiro\Models\Permissions\PermissionDenial;
use Simplysmart\Admiro\Models\Permissions\Role;
use Simplysmart\Admiro\Permissions\PermissionTables;
use Simplysmart\Admiro\Zones\ZoneDefinition;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Zones\ZoneTestCase;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Role i uprawnienia kont stref: izolacja stref (guard), zakres organizacji (zespół spatie), brak domyślnej
 * organizacji (fail closed), odmowy wygrywające z rolą i kontrakt ZonePermissionChecker.
 */
class ZonePermissionsTest extends ZoneTestCase
{
    private const string PERMISSION = 'zones.view';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OperatorOrganizationSeeder::class);
        $this->inOrganization(Organization::OPERATOR_ID);
    }

    public function test_role_of_one_zone_gives_no_permission_in_another(): void
    {
        $alpha = $this->account(AlphaAccount::class);
        $beta = $this->account(BetaAccount::class);
        $alpha->assignRole($this->role('alpha', 'owner', [self::PERMISSION]));
        $beta->assignRole($this->role('beta', 'owner', []));
        Permission::create(['name' => self::PERMISSION, 'guard_name' => 'beta']);

        $this->assertTrue($alpha->hasPermissionTo(self::PERMISSION, 'alpha'));
        $this->assertFalse($beta->hasPermissionTo(self::PERMISSION, 'beta'));
        $this->assertFalse($this->checker()->allows($this->zone('beta'), $alpha, self::PERMISSION));
    }

    public function test_role_assigned_in_one_organization_does_not_apply_in_another(): void
    {
        $company = $this->organization();
        $account = $this->account(AlphaAccount::class);
        $role = $this->role('alpha', 'manager', [self::PERMISSION]);

        $this->inOrganization($company->id);
        $account->assignRole($role);

        $this->assertTrue($account->hasPermissionTo(self::PERMISSION));

        $this->inOrganization(Organization::OPERATOR_ID);
        $account->unsetRelation('roles');

        $this->assertFalse($account->hasPermissionTo(self::PERMISSION));
    }

    public function test_without_a_current_organization_roles_are_neither_assigned_nor_checked(): void
    {
        $account = $this->account(AlphaAccount::class);
        $role = $this->role('alpha', 'owner', [self::PERMISSION]);
        $account->assignRole($role);
        $this->inOrganization(null);
        $account->unsetRelation('roles');

        $this->assertFalse($this->checker()->allows($this->zone('alpha'), $account, self::PERMISSION));
        $this->expectException(QueryException::class);

        $this->account(BetaAccount::class)->assignRole($this->role('beta', 'owner', []));
    }

    public function test_deleting_an_organization_removes_its_roles_and_assignments(): void
    {
        $company = $this->organization();
        $this->inOrganization($company->id);
        $ownRole = new Role();
        $ownRole->forceFill(['name' => 'auditor', 'guard_name' => 'alpha', PermissionTables::TEAM_FOREIGN_KEY => $company->id])->save();
        $this->account(AlphaAccount::class)->assignRole($ownRole);

        $company->forceDelete();

        $this->assertDatabaseMissing(PermissionTables::MODEL_HAS_ROLES, [PermissionTables::TEAM_FOREIGN_KEY => $company->id]);
        $this->assertNull(Role::withTrashed()->find($ownRole->id));
    }

    public function test_denial_wins_over_a_role_and_the_gate(): void
    {
        $account = $this->account(AlphaAccount::class);
        $account->assignRole($this->role('alpha', 'owner', [self::PERMISSION]));
        $permission = Permission::findByName(self::PERMISSION, 'alpha');

        $denial = new PermissionDenial();
        $denial->forceFill([
            PermissionTables::PERMISSION_FOREIGN_KEY => $permission->getKey(),
            PermissionTables::TEAM_FOREIGN_KEY => Organization::OPERATOR_ID,
        ]);
        $denial->model()->associate($account);
        $denial->save();
        $account->unsetRelation('permissionDenials');

        $this->assertFalse($account->hasPermissionTo(self::PERMISSION));
        $this->assertFalse($account->can(self::PERMISSION));
        $this->assertFalse($this->checker()->allows($this->zone('alpha'), $account, self::PERMISSION));
    }

    public function test_denial_in_another_organization_does_not_apply(): void
    {
        $company = $this->organization();
        $account = $this->account(AlphaAccount::class);
        $account->assignRole($this->role('alpha', 'owner', [self::PERMISSION]));

        $denial = new PermissionDenial();
        $denial->forceFill([
            PermissionTables::PERMISSION_FOREIGN_KEY => Permission::findByName(self::PERMISSION, 'alpha')->getKey(),
            PermissionTables::TEAM_FOREIGN_KEY => $company->id,
        ]);
        $denial->model()->associate($account);
        $denial->save();

        $this->assertTrue($account->hasPermissionTo(self::PERMISSION));
    }

    public function test_checker_denies_an_unknown_permission(): void
    {
        $account = $this->account(AlphaAccount::class);

        $this->assertFalse($this->checker()->allows($this->zone('alpha'), $account, 'unknown.permission'));
    }

    /**
     * Globalna rola strefy (bez organizacji, jawnie: Role::create() spatie wpisałby bieżącą) z uprawnieniami tworzonymi, gdy ich brak.
     *
     * @param list<string> $permissions
     */
    private function role(string $guard, string $name, array $permissions): Role
    {
        $role = new Role();
        $role->forceFill(['name' => $name, 'guard_name' => $guard, PermissionTables::TEAM_FOREIGN_KEY => null])->save();

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, $guard));
        }

        return $role;
    }

    private function organization(): Organization
    {
        $organization = new Organization();
        $organization->forceFill(['name' => 'Firma A', 'is_active' => true])->save();

        return $organization;
    }

    private function inOrganization(?int $id): void
    {
        $this->app->make(PermissionRegistrar::class)->setPermissionsTeamId($id);
    }

    private function zone(string $name): ZoneDefinition
    {
        return $this->app->make(ZoneRegistry::class)->get($name);
    }

    private function checker(): ZonePermissionChecker
    {
        return $this->app->make(ZonePermissionChecker::class);
    }
}
