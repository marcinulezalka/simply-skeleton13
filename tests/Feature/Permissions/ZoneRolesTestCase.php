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

use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Database\Seeders\OperatorOrganizationSeeder;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Models\ZoneAccount;
use Simplysmart\Admiro\Permissions\OrganizationMembership;
use Simplysmart\Admiro\Permissions\PermissionFileParser;
use Simplysmart\Admiro\Permissions\PermissionFiles;
use Simplysmart\Admiro\Permissions\ZoneRoleSynchronizer;
use Tests\Feature\Zones\ZoneTestCase;

/**
 * Baza testów ról stref z plików JSON: organizacja operatora, pliki ról stref testowych (alpha z organizacjami
 * klientów, beta bez) zapisane w bazie oraz pomocnicze organizacje, członkostwa i role.
 */
abstract class ZoneRolesTestCase extends ZoneTestCase
{
    protected const string FIXTURES = __DIR__ . '/../../Fixtures/Permissions';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OperatorOrganizationSeeder::class);
        $this->app->make(PermissionFiles::class)->add(self::FIXTURES);

        foreach (['alpha', 'beta'] as $zone) {
            $this->app->make(ZoneRoleSynchronizer::class)->sync($this->app->make(PermissionFileParser::class)->parse(self::FIXTURES . "/{$zone}.json"));
        }
    }

    protected function organization(string $name = 'Firma'): Organization
    {
        $organization = new Organization();
        $organization->forceFill(['name' => $name, 'is_active' => true])->save();

        return $organization;
    }

    /**
     * Konto jako członek organizacji z rolami.
     */
    protected function member(ZoneAccount $account, Organization $organization, string ...$roles): ZoneAccount
    {
        $this->app->make(OrganizationMembership::class)->add($account, $organization);
        $this->app->make(CurrentOrganization::class)->within($organization->id, static function () use ($account, $roles): void {
            $account->assignRole($roles);
            $account->unsetRelation('roles');
        });

        return $account;
    }
}
