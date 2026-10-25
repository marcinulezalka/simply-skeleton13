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

use Illuminate\Support\Facades\App;
use Simplysmart\Admiro\Panel\Enums\Permission;
use Simplysmart\Admiro\Panel\Enums\Role;
use Simplysmart\Admiro\Permissions\PermissionFileParser;
use Tests\Feature\Zones\ZoneTestCase;

/**
 * Role strefy administratorów: enumy paczki admiro zgodne z plikiem ról Core (jedyne źródło przypisań),
 * `owner` bez zarządzania superadministratorami, a każde uprawnienie i rola plików Core z etykietą (pl, en).
 */
class AdmiroPermissionsTest extends ZoneTestCase
{
    private const string FILE = __DIR__ . '/../../../packages/simplysmart/admiro/database/seeders/permissions/admiro.json';

    public function test_enums_match_the_roles_file(): void
    {
        $set = $this->app->make(PermissionFileParser::class)->parse(self::FILE);

        $this->assertEqualsCanonicalizing(array_column(Permission::cases(), 'value'), $set->permissions);
        $this->assertEqualsCanonicalizing(array_column(Role::cases(), 'value'), array_keys($set->roles));
        $this->assertNotContains(Permission::HeroesManage->value, $set->permissionsOf(Role::Owner->value));
        $this->assertContains(Permission::HeroesManage->value, $set->permissionsOf(Role::Hero->value));
    }

    public function test_every_core_permission_and_role_has_a_label(): void
    {
        $parser = $this->app->make(PermissionFileParser::class);

        foreach (['pl', 'en'] as $locale) {
            App::setLocale($locale);

            foreach (glob(dirname(self::FILE) . '/*.json') ?: [] as $file) {
                $set = $parser->parse($file);

                foreach ($set->permissions as $permission) {
                    $key = "permissions.{$set->zone}.{$permission}";
                    $this->assertNotSame($key, text($key), "Missing {$locale} label {$key}.");
                }

                foreach (array_keys($set->roles) as $role) {
                    $key = "roles.{$set->zone}.{$role}";
                    $this->assertNotSame($key, text($key), "Missing {$locale} label {$key}.");
                }
            }
        }
    }
}
