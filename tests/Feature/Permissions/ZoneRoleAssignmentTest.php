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
use Simplysmart\Mainboard\Contracts\CurrentOrganization;
use Simplysmart\Mainboard\Contracts\ZonePermissionChecker;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Simplysmart\Mainboard\Models\Organization;
use Simplysmart\Mainboard\Models\ZoneAccount;
use Simplysmart\Mainboard\Permissions\OrganizationMembership;
use Simplysmart\Mainboard\Permissions\PermissionTables;
use Simplysmart\Mainboard\Permissions\RoleAssignmentDenied;
use Simplysmart\Mainboard\Permissions\ZoneRoleAssignment;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Nadawanie ról bez eskalacji (rola nie szersza niż uprawnienia nadającego w tej organizacji, ta sama strefa,
 * oba konta członkami) oraz odbieranie ról i członkostwa z unieważnieniem sesji konta.
 */
class ZoneRoleAssignmentTest extends ZoneRolesTestCase
{
    private Organization $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->organization();
    }

    public function test_manager_assigns_a_narrower_role(): void
    {
        $manager = $this->member($this->account(AlphaAccount::class), $this->company, 'manager');
        $clerk = $this->member($this->account(AlphaAccount::class, ['email' => 'clerk@example.com']), $this->company);

        $this->assignment()->assign($manager, $clerk, 'analyst', $this->company);

        $this->assertTrue($this->allows($clerk, 'reports.export'));
    }

    public function test_role_broader_than_the_assigner_is_refused(): void
    {
        $owner = $this->member($this->account(AlphaAccount::class), $this->company, 'owner');
        $other = $this->member($this->account(AlphaAccount::class, ['email' => 'other@example.com']), $this->company);

        $this->expectException(RoleAssignmentDenied::class);
        $this->expectExceptionMessage('grants more than the assigner has');

        $this->assignment()->assign($owner, $other, 'hero', $this->company);
    }

    public function test_denied_permission_cannot_be_passed_on(): void
    {
        $manager = $this->member($this->account(AlphaAccount::class), $this->company, 'manager');
        $other = $this->member($this->account(AlphaAccount::class, ['email' => 'other@example.com']), $this->company);
        $manager->permissionDenials()->forceCreate([
            PermissionTables::PERMISSION_FOREIGN_KEY => DB::table(PermissionTables::PERMISSIONS)->where('name', 'reports.export')->value('id'),
            PermissionTables::TEAM_FOREIGN_KEY => $this->company->id,
        ]);
        $manager->unsetRelation('permissionDenials');

        $this->expectException(RoleAssignmentDenied::class);
        $this->expectExceptionMessage('grants more than the assigner has');

        $this->assignment()->assign($manager, $other, 'analyst', $this->company);
    }

    public function test_roles_from_another_organization_do_not_count(): void
    {
        $manager = $this->member($this->account(AlphaAccount::class), $this->organization('Inna'), 'manager');
        $this->member($manager, $this->company);
        $other = $this->member($this->account(AlphaAccount::class, ['email' => 'other@example.com']), $this->company);

        $this->expectException(RoleAssignmentDenied::class);
        $this->expectExceptionMessage('grants more than the assigner has');

        $this->assignment()->assign($manager, $other, 'analyst', $this->company);
    }

    public function test_account_outside_the_organization_cannot_get_a_role(): void
    {
        $manager = $this->member($this->account(AlphaAccount::class), $this->company, 'manager');

        $this->expectException(RoleAssignmentDenied::class);
        $this->expectExceptionMessage('members of the organization');

        $this->assignment()->assign($manager, $this->account(AlphaAccount::class, ['email' => 'outsider@example.com']), 'analyst', $this->company);
    }

    public function test_assigner_from_another_zone_is_refused(): void
    {
        $this->member($admin = $this->account(BetaAccount::class), $this->company, 'owner');
        $account = $this->member($this->account(AlphaAccount::class), $this->company);

        $this->expectException(RoleAssignmentDenied::class);
        $this->expectExceptionMessage('another zone');

        $this->assignment()->assign($admin, $account, 'guest', $this->company);
    }

    public function test_revoking_a_role_signs_the_account_out(): void
    {
        $manager = $this->member($this->account(AlphaAccount::class), $this->company, 'manager');
        $analyst = $this->member($this->account(AlphaAccount::class, ['email' => 'analyst@example.com']), $this->company, 'analyst');

        $this->assignment()->revoke($manager, $analyst, 'analyst', $this->company);

        $this->assertFalse($this->allows($analyst, 'reports.view'));
        $this->assertSame(1, $analyst->fresh()?->authEpoch());
    }

    public function test_removing_a_member_clears_its_roles_and_denials_in_that_organization(): void
    {
        $other = $this->organization('Inna');
        $account = $this->member($this->account(AlphaAccount::class), $this->company, 'analyst');
        $this->member($account, $other, 'analyst');

        $this->app->make(OrganizationMembership::class)->remove($account, $this->company);

        $this->assertFalse($this->allows($account, 'reports.view'));
        $this->assertTrue($this->allows($account, 'reports.view', $other));
        $this->assertDatabaseMissing(PermissionTables::ORGANIZATION_MEMBERS, [PermissionTables::TEAM_FOREIGN_KEY => $this->company->id]);
        $this->assertSame(1, $account->fresh()?->authEpoch());
    }

    private function allows(ZoneAccount $account, string $permission, ?Organization $organization = null): bool
    {
        return $this->app->make(CurrentOrganization::class)->within(($organization ?? $this->company)->id, function () use ($account, $permission): bool {
            $account->unsetRelation('roles')->unsetRelation('permissions');

            return $this->app->make(ZonePermissionChecker::class)->allows($this->app->make(ZoneRegistry::class)->get('alpha'), $account, $permission);
        });
    }

    private function assignment(): ZoneRoleAssignment
    {
        return $this->app->make(ZoneRoleAssignment::class);
    }
}
