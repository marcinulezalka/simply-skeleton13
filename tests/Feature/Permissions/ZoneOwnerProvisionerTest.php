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

use LogicException;
use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Permissions\OwnerData;
use Simplysmart\Admiro\Permissions\ZoneOwnerProvisioners;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Zakładanie właściciela strefy przez kontrakt (np. z panelu administratorów): konto w tabeli tej strefy,
 * członkostwo i rola `owner` w organizacji klienta albo operatora.
 */
class ZoneOwnerProvisionerTest extends ZoneRolesTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['alpha', 'beta'] as $zone) {
            $this->app->make(ZoneOwnerProvisioners::class)->addDefault($zone);
        }
    }

    public function test_owner_of_a_zone_without_teams_gets_the_operator_organization(): void
    {
        $token = $this->provisioners()->for('beta')->provisionOwner(new OwnerData('Jan Kowalski', 'jan@example.com'));

        $owner = BetaAccount::query()->where('token', $token)->firstOrFail();

        $this->assertTrue($this->app->make(CurrentOrganization::class)->within(Organization::OPERATOR_ID, static fn(): bool => $owner->hasRole('owner')));
        $this->assertSame(0, AlphaAccount::query()->count());
    }

    public function test_owner_of_a_zone_with_teams_needs_an_organization(): void
    {
        $company = $this->organization();
        $token = $this->provisioners()->for('alpha')->provisionOwner(new OwnerData('Jan Kowalski', 'jan@example.com'), $company);
        $owner = AlphaAccount::query()->where('token', $token)->firstOrFail();

        $this->assertTrue($this->app->make(CurrentOrganization::class)->within($company->id, static fn(): bool => $owner->hasRole('owner')));
        $this->expectException(LogicException::class);

        $this->provisioners()->for('alpha')->provisionOwner(new OwnerData('Anna Nowak', 'anna@example.com'));
    }

    public function test_existing_account_becomes_the_owner(): void
    {
        $account = $this->account(BetaAccount::class);

        $token = $this->provisioners()->for('beta')->provisionOwner(new OwnerData('Anna Nowak', 'anna@example.com'));

        $this->assertSame($account->getAttribute('token'), $token);
        $this->assertContains('beta', $this->provisioners()->zones());
    }

    private function provisioners(): ZoneOwnerProvisioners
    {
        return $this->app->make(ZoneOwnerProvisioners::class);
    }
}
