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

use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Queries\Organizations\OrganizationMembers;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Członkowie organizacji: konta administratorów (rola wsparcia, RequiredRole::Support) są ukryte w strefach, a panel
 * administratorów (all()) widzi wszystkich.
 */
class OrganizationMembersTest extends ZoneRolesTestCase
{
    public function test_support_accounts_are_hidden_in_zones_and_visible_to_the_admin_panel(): void
    {
        $organization = $this->organization();
        $staff = $this->member($this->account(AlphaAccount::class, ['email' => 'staff@example.com']), $organization, 'analyst');
        $support = $this->member($this->account(AlphaAccount::class, ['email' => 'support@example.com']), $organization, 'support');
        $members = $this->app->make(OrganizationMembers::class);
        $zone = $this->app->make(ZoneRegistry::class)->get('alpha');

        $this->assertSame([$staff->id], array_map(static fn($account): mixed => $account->getKey(), $members->inZone($organization, $zone)));
        $supportKey = $support->getRouteKey();
        $this->assertIsString($supportKey);
        $this->assertNull($members->find($organization, $zone, $supportKey));
        $this->assertEqualsCanonicalizing([$staff->id, $support->id], array_map(static fn(array $member): mixed => $member['account']->getKey(), $members->all($organization)));
    }
}
