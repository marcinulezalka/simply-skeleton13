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

use Illuminate\Support\Facades\DB;
use Simplysmart\Admiro\Database\IdSlots;
use Simplysmart\Admiro\Models\Permissions\Permission;
use Simplysmart\Admiro\Models\Permissions\Role;
use Simplysmart\Admiro\Permissions\PermissionFileParser;
use Simplysmart\Admiro\Permissions\PermissionFiles;
use Simplysmart\Admiro\Permissions\PermissionTables;
use Simplysmart\Admiro\Permissions\RoleDelegation;
use Simplysmart\Admiro\Permissions\ZoneRoleSynchronizer;

/**
 * Zapis ról z plików: idempotentny, z id z puli seederów, role globalne dla guardu strefy, przywracanie
 * usuniętych, role delegowane w `json_default_settings`; rekordy spoza pliku tylko raportowane, usuwane przez `--prune` (na produkcji z `--force`).
 */
class ZoneRoleSynchronizerTest extends ZoneRolesTestCase
{
    public function test_sync_twice_gives_the_same_state(): void
    {
        $before = $this->snapshot();

        $report = $this->sync('alpha');

        $this->assertSame([], $report->created);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_roles_are_global_for_the_zone_guard_with_seed_ids(): void
    {
        $owner = Role::query()->where('name', 'owner')->where('guard_name', 'alpha')->firstOrFail();

        $this->assertNull($owner->organization_id);
        $this->assertLessThan(IdSlots::FIRST_USER_ID, $owner->id);
        $this->assertEqualsCanonicalizing(['notes.manage', 'reports.export', 'reports.view'], $owner->permissions->pluck('name')->all());
    }

    public function test_delegated_roles_are_stored_in_default_settings(): void
    {
        $roles = Role::query()->where('guard_name', 'alpha')->get()->keyBy('name');

        $this->assertSame(['manager'], $roles['owner']?->defaultSetting(RoleDelegation::SETTING));
        $this->assertNull($roles['manager']?->json_default_settings);
    }

    public function test_deleted_role_is_restored(): void
    {
        Role::query()->where('name', 'analyst')->firstOrFail()->delete();

        $report = $this->sync('alpha');

        $this->assertSame(['role:analyst'], $report->restored);
        $this->assertNotNull(Role::query()->where('name', 'analyst')->first());
    }

    public function test_records_missing_from_the_file_are_reported_and_pruned_only_on_request(): void
    {
        $this->extraPermission();

        $this->command('admiro:permissions:sync', ['--zone' => 'alpha'])->assertSuccessful();
        $this->assertNotNull(Permission::query()->where('name', 'legacy.view')->first());

        $this->command('admiro:permissions:sync', ['--zone' => 'alpha', '--prune' => true])
            ->expectsConfirmation('Usunąć uprawnienia i role, których nie ma w plikach?', 'yes')
            ->assertSuccessful();
        $this->assertNull(Permission::query()->where('name', 'legacy.view')->first());
    }

    public function test_prune_in_production_needs_force(): void
    {
        $this->extraPermission();
        $this->app['env'] = 'production';

        $this->command('admiro:permissions:sync', ['--zone' => 'alpha', '--prune' => true])->assertFailed();
        $this->assertNotNull(Permission::query()->where('name', 'legacy.view')->first());

        $this->command('admiro:permissions:sync', ['--zone' => 'alpha', '--prune' => true, '--force' => true])->assertSuccessful();
        $this->assertNull(Permission::query()->where('name', 'legacy.view')->first());
    }

    public function test_invalid_file_stops_the_command_before_any_write(): void
    {
        $path = sys_get_temp_dir() . '/roles-' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($path, '{"zone": "beta", "permissions": [], "roles": {}}');
        $this->app->make(PermissionFiles::class)->add($path);
        Role::query()->where('name', 'analyst')->firstOrFail()->delete();

        try {
            $this->command('admiro:permissions:sync')->assertFailed();
        } finally {
            unlink($path);
        }

        $this->assertNull(Role::query()->where('name', 'analyst')->first());
    }

    private function sync(string $zone): \Simplysmart\Admiro\Permissions\ZoneRoleSyncReport
    {
        return $this->app->make(ZoneRoleSynchronizer::class)->sync($this->app->make(PermissionFileParser::class)->parse(self::FIXTURES . "/{$zone}.json"));
    }

    private function extraPermission(): void
    {
        $permission = new Permission();
        $permission->forceFill(['name' => 'legacy.view', 'guard_name' => 'alpha'])->save();
    }

    private function highestRoleId(): int
    {
        $id = DB::table(PermissionTables::ROLES)->max('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    /**
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        return [
            'permissions' => DB::table(PermissionTables::PERMISSIONS)->count(),
            'roles' => DB::table(PermissionTables::ROLES)->count(),
            'role_permissions' => DB::table(PermissionTables::ROLE_HAS_PERMISSIONS)->count(),
            'max_id' => $this->highestRoleId(),
        ];
    }
}
