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

use Simplysmart\Mainboard\Models\Organization;
use Simplysmart\Mainboard\Organizations\OrganizationManagement;
use Simplysmart\Mainboard\Permissions\OrganizationMembership;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Organizacje w panelu administratora: lista i członkowie we wszystkich strefach (`organizations.view`),
 * zakładanie, drzewo bez cykli, chroniona organizacja operatora, wyłączanie i usuwanie członków
 * (`organizations.manage`) z ochroną innych administratorów.
 */
class AdmiroOrganizationsTest extends AdmiroTestCase
{
    public function test_owner_creates_an_organization_with_a_branch(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/organizations', ['name' => 'Firma'])->assertRedirect('/admiro/organizations');
        $company = Organization::query()->where('name', 'Firma')->sole();
        $this->post('/admiro/organizations', ['name' => 'Oddział', 'parent' => $company->token]);

        $this->assertSame($company->id, Organization::query()->where('name', 'Oddział')->sole()->parent_id);
        $this->get('/admiro/organizations')->assertOk()->assertSee('Oddział')->assertSee('Firma');
    }

    public function test_tree_cycle_and_operator_changes_are_rejected(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $company = $management->create('Firma');
        $branch = $management->create('Oddział', $company);
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->put('/admiro/organizations/' . $company->token, ['name' => 'Firma', 'parent' => $branch->token]);
        $this->post('/admiro/organizations/' . $operator->token . '/toggle');

        $this->assertNull($company->fresh()?->parent_id);
        $this->assertTrue($operator->fresh()?->is_active);
        $this->assertSessionError('organization', text('admiro::admin.organization_rejected'));
    }

    public function test_disabled_organization_is_not_a_parent_choice_and_can_be_enabled_again(): void
    {
        $company = $this->app->make(OrganizationManagement::class)->create('Firma');
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/organizations/' . $company->token . '/toggle');
        $company->refresh();
        $this->assertFalse($company->is_active);
        $this->get('/admiro/organizations/create')->assertDontSee($company->token);

        $this->post('/admiro/organizations/' . $company->token . '/toggle');
        $company->refresh();
        $this->assertTrue($company->is_active);
    }

    public function test_support_views_members_but_cannot_change_anything(): void
    {
        $company = $this->app->make(OrganizationManagement::class)->create('Firma');
        $this->app->make(OrganizationMembership::class)->add($this->account(AlphaAccount::class), $company);
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/organizations/' . $company->token . '/members')->assertOk()->assertSee('anna@example.com')->assertSee('alpha')->assertDontSee(text('admiro::admin.member_remove'));
        $this->get('/admiro/organizations/create')->assertForbidden();
        $this->post('/admiro/organizations', ['name' => 'Nowa'])->assertForbidden();
    }

    public function test_member_of_another_zone_is_removed_and_signed_out(): void
    {
        $company = $this->app->make(OrganizationManagement::class)->create('Firma');
        $member = $this->account(AlphaAccount::class);
        $this->app->make(OrganizationMembership::class)->add($member, $company);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->delete('/admiro/organizations/' . $company->token . '/members/alpha/' . $this->routeKey($member))->assertRedirect();

        $this->assertDatabaseMissing('organization_members', ['organization_id' => $company->id]);
        $this->assertSame(1, $member->fresh()?->authEpoch());
    }

    public function test_administrator_with_more_permissions_stays_in_the_operator_organization(): void
    {
        $hero = $this->admin('hero@example.com', 'hero');
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);

        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')
            ->delete('/admiro/organizations/' . $operator->token . '/members/admiro/' . $this->routeKey($hero))
            ->assertForbidden();

        $this->assertDatabaseHas('organization_members', ['organization_id' => Organization::OPERATOR_ID, 'model_id' => $hero->getKey()]);
    }

    public function test_unknown_organization_is_not_found(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/organizations/missing/members')->assertNotFound();
    }
}
