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

use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Permissions\OrganizationMembership;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Organizacje w panelu administratora: lista i członkowie we wszystkich strefach (`organizations.view`),
 * zakładanie, drzewo bez cykli (także przenoszenie jednostek, bez departamentów), chroniona organizacja operatora, wyłączanie i usuwanie członków
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

    public function test_short_name_is_saved_cleared_and_falls_back_to_the_full_name(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/organizations', ['name' => 'Hurtownia Materiałów Budowlanych', 'short_name' => 'HMB'])->assertRedirect('/admiro/organizations');
        $organization = Organization::query()->where('name', 'Hurtownia Materiałów Budowlanych')->sole();
        $this->assertSame('HMB', $organization->shortName());
        $this->get('/admiro/organizations/' . $organization->token . '/edit')->assertOk()->assertSee('value="HMB"', false);

        $this->put('/admiro/organizations/' . $organization->token, ['name' => 'Hurtownia Materiałów Budowlanych', 'short_name' => '']);
        $organization->refresh();
        $this->assertNull($organization->short_name);
        $this->assertSame('Hurtownia Materiałów Budowlanych', $organization->shortName());

        $this->post('/admiro/organizations', ['name' => 'Firma', 'short_name' => str_repeat('x', Organization::SHORT_NAME_MAX_LENGTH + 1)]);
        $this->assertNotSame('', $this->sessionError(Organization::SHORT_NAME));
        $this->assertFalse(Organization::query()->where('name', 'Firma')->exists());
    }

    public function test_organization_details_are_saved_and_billing_email_starts_as_the_organization_email(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $details = ['nip' => '123-456-32-18', 'regon' => '123456785', 'krs' => '0000123456', 'phone' => '+48 600 100 200', 'email' => 'biuro@example.com', 'street' => 'ul. Prosta 1', 'postal_code' => '00-001', 'city' => 'Warszawa'];

        $this->post('/admiro/organizations', ['name' => 'Firma', ...$details])->assertRedirect('/admiro/organizations');
        $organization = Organization::query()->where('name', 'Firma')->sole();
        $this->assertSame('1234563218', $organization->nip);
        $this->assertSame('biuro@example.com', $organization->billing_email);
        $this->assertSame('ul. Prosta 1, 00-001 Warszawa', $organization->address());
        $this->get('/admiro/organizations/' . $organization->token . '/edit')->assertOk()
            ->assertSeeInOrder([text('layout.organization_form.details'), text('layout.organization_form.contact'), text('layout.organization_form.address')]);

        $this->put('/admiro/organizations/' . $organization->token, ['name' => 'Firma', ...$details, 'billing_email' => 'faktury@example.com']);
        $this->assertSame('faktury@example.com', $organization->fresh()?->billing_email);

        $this->post('/admiro/organizations', ['name' => 'Zły NIP', 'nip' => '123-456-32-19']);
        $this->assertSame(text('validation.nip'), $this->sessionError('nip'));
        $this->assertFalse(Organization::query()->where('name', 'Zły NIP')->exists());
    }

    public function test_phones_are_saved_with_the_country_prefix_and_a_new_organization_starts_in_the_default_country(): void
    {
        $this->seedDictionaries();
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/organizations/create')->assertOk()
            ->assertSee('<option value="PL" selected>', false)
            ->assertSee(text_data(Organization::class, Organization::PHONE))
            ->assertSee(text_data(Organization::class, Organization::MOBILE_PHONE));

        $this->post('/admiro/organizations', [
            'name' => 'Firma',
            'phone_country' => 'PL',
            'phone' => '22 100 20 30',
            'mobile_phone_country' => 'DE',
            'mobile_phone' => '151 2345 6789',
            'country_code' => 'PL',
        ])->assertRedirect('/admiro/organizations');
        $organization = Organization::query()->where('name', 'Firma')->sole();
        $this->assertSame('+48 22 100 20 30', $organization->phone);
        $this->assertSame('+49 151 2345 6789', $organization->mobile_phone);
        $this->assertSame('PL', $organization->country_code);

        $html = (string) $this->get('/admiro/organizations/' . $organization->token . '/edit')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~name="mobile_phone_country"[^>]*>.*?<option value="DE" selected>~s', $html);
        $this->assertStringContainsString('value="151 2345 6789"', $html);

        $this->post('/admiro/organizations', ['name' => 'Zły kraj', 'country_code' => 'XX', 'phone' => 'abc']);
        $this->assertNotSame('', $this->sessionError('country_code'));
        $this->assertNotSame('', $this->sessionError('phone'));
        $this->assertFalse(Organization::query()->where('name', 'Zły kraj')->exists());
    }

    public function test_tree_cycle_and_operator_changes_are_rejected(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $company = $management->create(new OrganizationData('Firma'));
        $branch = $management->create(new OrganizationData('Oddział'), $company);
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->put('/admiro/organizations/' . $company->token, ['name' => 'Firma', 'parent' => $branch->token]);
        $this->post('/admiro/organizations/' . $operator->token . '/toggle');

        $this->assertNull($company->fresh()?->parent_id);
        $this->assertTrue($operator->fresh()?->is_active);
        $this->assertSessionError('organization', text('admiro::admin.organization_rejected'));
    }

    public function test_unit_is_moved_in_the_tree_while_cycles_the_operator_and_departments_stay(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $company = $management->create(new OrganizationData('Firma'));
        $branch = $management->create(new OrganizationData('Oddział'));
        $department = $management->createDepartment(new OrganizationData('Poradnia'), $branch);
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->from('/admiro/onboarding/organizations')->put('/admiro/organizations/' . $branch->token . '/parent', ['parent' => $company->token])
            ->assertRedirect('/admiro/onboarding/organizations')
            ->assertSessionHas('status', text('admiro::admin.organization_moved', ['name' => 'Oddział']));
        $this->assertSame($company->id, $branch->fresh()?->parent_id);

        $this->put('/admiro/organizations/' . $company->token . '/parent', ['parent' => $branch->token]);
        $this->assertSessionError('organization', text('admiro::admin.organization_rejected'));
        $this->put('/admiro/organizations/' . $operator->token . '/parent', ['parent' => $company->token]);
        $this->put('/admiro/organizations/' . $department->token . '/parent', ['parent' => $company->token]);
        $this->put('/admiro/organizations/' . $branch->token . '/parent', ['parent' => $department->token]);
        $this->put('/admiro/organizations/' . $branch->token . '/parent', ['parent' => 'unknown'])->assertNotFound();

        $this->assertNull($company->refresh()->parent_id);
        $this->assertNull($operator->refresh()->parent_id);
        $this->assertSame($branch->id, $department->refresh()->parent_id);
        $this->assertSame($company->id, $branch->refresh()->parent_id);

        $this->put('/admiro/organizations/' . $branch->token . '/parent', ['parent' => ''])->assertRedirect();
        $branch->refresh();
        $this->assertNull($branch->parent_id);
    }

    public function test_disabled_organization_is_not_a_parent_choice_and_can_be_enabled_again(): void
    {
        $company = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Firma'));
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
        $company = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Firma'));
        $this->app->make(OrganizationMembership::class)->add($this->account(AlphaAccount::class), $company);
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/organizations/' . $company->token . '/members')->assertOk()->assertSee('anna@example.com')->assertSee('alpha')->assertDontSee(text('admiro::admin.member_remove'));
        $this->get('/admiro/organizations/create')->assertForbidden();
        $this->post('/admiro/organizations', ['name' => 'Nowa'])->assertForbidden();
    }

    public function test_member_of_another_zone_is_removed_and_signed_out(): void
    {
        $company = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Firma'));
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
