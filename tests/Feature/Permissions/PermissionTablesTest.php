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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Mainboard\Database\ColumnNames;
use Simplysmart\Mainboard\Database\History;
use Simplysmart\Mainboard\Database\IdSlots;
use Simplysmart\Mainboard\Models\MainModel;
use Simplysmart\Mainboard\Models\Organization;
use Simplysmart\Mainboard\Models\Permissions\Permission;
use Simplysmart\Mainboard\Models\Permissions\PermissionDenial;
use Simplysmart\Mainboard\Models\Permissions\Role;
use Simplysmart\Mainboard\Permissions\PermissionTables;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tabele uprawnień spatie i organizacji: standard tabel danych (role, uprawnienia, organizacje), historia
 * wszystkich tabel, casty i etykiety kolumn oraz konfiguracja spatie ustawiana przez Core.
 */
class PermissionTablesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return [
            'organization' => [Organization::class],
            'role' => [Role::class],
            'permission' => [Permission::class],
            'permission denial' => [PermissionDenial::class],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function standardTables(): array
    {
        return [
            'organizations' => [Organization::TABLE],
            'roles' => [PermissionTables::ROLES],
            'permissions' => [PermissionTables::PERMISSIONS],
        ];
    }

    #[DataProvider('standardTables')]
    public function test_standard_columns_frame_the_domain_columns(string $table): void
    {
        $head = [ColumnNames::ID, ColumnNames::TOKEN, ColumnNames::PARENT_ID, ColumnNames::NAME, ColumnNames::STATUS, ColumnNames::IS_ACTIVE, ColumnNames::IS_DEFAULT, ColumnNames::SORT_ORDER];
        $tail = [ColumnNames::JSON_ATTRIBUTES, ColumnNames::JSON_DEFAULT_SETTINGS, ColumnNames::JSON_TAGS, ColumnNames::JSON_MIGRATIONS, ColumnNames::CHECKSUM, ColumnNames::JSON_ARCHIVE, ColumnNames::CREATED_AT, ColumnNames::UPDATED_AT, ColumnNames::DELETED_AT];

        // SQLite ignores ->after(), so domain columns added later by ALTER land at the end; only the standard order is checked.
        $this->assertSame([...$head, ...$tail], array_values(array_intersect(Schema::getColumnListing($table), [...$head, ...$tail])));
    }

    #[DataProvider('standardTables')]
    public function test_user_records_get_ids_from_the_user_pool(string $table): void
    {
        DB::table($table)->insert([ColumnNames::TOKEN => 'token', ColumnNames::NAME => 'x', ...($table === Organization::TABLE ? [] : [PermissionTables::GUARD_NAME => 'alpha'])]);

        $this->assertDatabaseHas($table, [ColumnNames::ID => IdSlots::FIRST_USER_ID]);
    }

    public function test_every_permission_table_has_triggers_matching_its_columns(): void
    {
        $history = $this->app->make(History::class);

        foreach ([Organization::TABLE, ...PermissionTables::ALL] as $table) {
            $this->assertTrue(Schema::hasTable(History::tableFor($table)), $table);
            $this->assertSame(Schema::getColumnListing($table), $history->trackedColumns($table), $table);
        }
    }

    /**
     * @param class-string<Model> $model
     */
    #[DataProvider('models')]
    public function test_every_column_has_a_cast(string $model): void
    {
        $instance = new $model();

        $this->assertSame([], array_values(array_diff(Schema::getColumnListing($instance->getTable()), array_keys($instance->getCasts()))));
    }

    /**
     * @param class-string<Model> $model
     */
    #[DataProvider('models')]
    public function test_every_column_has_a_label_in_each_language(string $model): void
    {
        foreach (['pl', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach (Schema::getColumnListing((new $model())->getTable()) as $column) {
                $this->assertStringNotContainsString('data.', text_data($model, $column), "{$locale}: {$model}.{$column}");
            }
        }
    }

    public function test_spatie_uses_core_models_organizations_as_teams_and_a_shared_cache_key(): void
    {
        $registrar = $this->app->make(PermissionRegistrar::class);

        $this->assertSame(Role::class, $registrar->getRoleClass());
        $this->assertSame(Permission::class, $registrar->getPermissionClass());
        $this->assertTrue($registrar->teams);
        $this->assertSame(PermissionTables::TEAM_FOREIGN_KEY, $registrar->teamsKey);
        $this->assertSame('zone:shared:spatie.permission', $registrar->cacheKey);
        $this->assertNull($registrar->getPermissionsTeamId());
    }

    public function test_new_role_gets_a_token_checksum_and_history(): void
    {
        $role = Role::create(['name' => 'owner', 'guard_name' => 'alpha']);
        $this->assertInstanceOf(Role::class, $role);

        $this->assertNull($role->organization_id);
        $this->assertSame(MainModel::TOKEN_LENGTH, strlen($role->token));
        $this->assertSame($role->checksum, $role->refresh()->computeChecksum());
        $this->assertSame(1, DB::table(History::tableFor(PermissionTables::ROLES))->where('row_id', $role->id)->count());
    }

    public function test_deleted_permission_is_soft_and_keeps_its_name_taken(): void
    {
        $permission = Permission::create(['name' => 'zones.view', 'guard_name' => 'alpha']);
        $permission->delete();

        $this->assertNotNull(Permission::withTrashed()->find($permission->id));
        $this->expectException(QueryException::class);

        Permission::create(['name' => 'zones.view', 'guard_name' => 'alpha']);
    }
}
