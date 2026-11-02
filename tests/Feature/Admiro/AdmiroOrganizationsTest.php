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

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Simplysmart\Admiro\Attributes\CustomAttributes;
use Simplysmart\Admiro\Http\Controllers\FlagController;
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

        $this->post('/admiro/organizations', ['name' => 'Firma'])->assertRedirect($this->zoneUrl('/admiro/organizations'));
        $company = Organization::query()->where('name', 'Firma')->sole();
        $this->post('/admiro/organizations', ['name' => 'Oddział', 'parent' => $company->token]);

        $this->assertSame($company->id, Organization::query()->where('name', 'Oddział')->sole()->parent_id);
        $this->get('/admiro/organizations')->assertOk()->assertSee('Oddział')->assertSee('Firma');
    }

    public function test_short_name_is_saved_cleared_and_falls_back_to_the_full_name(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/organizations', ['name' => 'Hurtownia Materiałów Budowlanych', 'short_name' => 'HMB'])->assertRedirect($this->zoneUrl('/admiro/organizations'));
        $organization = Organization::query()->where('name', 'Hurtownia Materiałów Budowlanych')->sole();
        $this->assertSame('HMB', $organization->shortName());
        $this->get('/admiro/organizations/' . $organization->token . '/edit')->assertOk()->assertSee('value="HMB"', false);

        $this->put('/admiro/organizations/' . $organization->token, ['name' => 'Hurtownia Materiałów Budowlanych', 'short_name' => '']);
        $organization->refresh();
        $this->assertNull($organization->short_name);
        $this->assertSame('Hurtownia Materiałów Budowlanych', $organization->shortName());

        $longest = str_repeat('ż', Organization::SHORT_NAME_MAX_LENGTH);
        $this->assertSame(100, Organization::SHORT_NAME_MAX_LENGTH);
        $this->put('/admiro/organizations/' . $organization->token, ['name' => 'Hurtownia Materiałów Budowlanych', 'short_name' => $longest])->assertSessionHasNoErrors();
        $this->assertSame($longest, $organization->fresh()?->short_name, 'Kolumna mieści najdłuższą nazwę skróconą.');

        $this->post('/admiro/organizations', ['name' => 'Firma', 'short_name' => str_repeat('x', Organization::SHORT_NAME_MAX_LENGTH + 1)]);
        $this->assertNotSame('', $this->sessionError(Organization::SHORT_NAME));
        $this->assertFalse(Organization::query()->where('name', 'Firma')->exists());
    }

    public function test_organization_details_are_saved_and_billing_email_starts_as_the_organization_email(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $details = ['nip' => '123-456-32-18', 'regon' => '123456785', 'krs' => '0000123456', 'phone' => '+48 600 100 200', 'email' => 'biuro@example.com', 'street' => 'ul. Prosta 1', 'postal_code' => '00-001', 'city' => 'Warszawa'];

        $this->post('/admiro/organizations', ['name' => 'Firma', ...$details])->assertRedirect($this->zoneUrl('/admiro/organizations'));
        $organization = Organization::query()->where('name', 'Firma')->sole();
        $this->assertSame('1234563218', $organization->nip);
        $this->assertSame('biuro@example.com', $organization->billing_email);
        $this->assertSame('ul. Prosta 1, 00-001 Warszawa', $organization->address());
        $form = (string) $this->get('/admiro/organizations/' . $organization->token . '/edit')->assertOk()
            ->assertSeeInOrder([text('layout.organization_form.details'), text('layout.organization_form.address'), text('layout.organization_form.contact')])
            ->getContent();
        $this->assertSame(3, substr_count($form, '<h2 class="ss-folder__tab ss-folder__tab--title"'), 'Nagłówek każdej karty z ikoną na wypustce teczki.');
        $this->assertStringNotContainsString('ss-form-row', Str::between($form, 'id="organization-details-title"', '</form>'), 'Każde pole w osobnym wierszu.');
        $this->assertSame(3, substr_count($form, '<div class="ss-input-wrap ss-input-wrap--icon ss-flag-select"'), 'Kraj adresu i oba prefiksy telefonu z flagą w polu.');
        $this->assertMatchesRegularExpression('~<div class="ss-phone">\s*<div class="ss-input-wrap ss-input-wrap--icon ss-flag-select"[^>]*>\s*<img[^>]*data-ss-flag-image>\s*<select class="ss-input ss-phone__prefix" id="phone_country" name="phone_country"~', $form, 'Telefon w osobnym wierszu: prefiks z flagą i numer.');
        $select = Blade::render('<x-admiro::flag-select :flags="$flags" selected="PL" id="country" name="country"><option value="PL">Polska</option></x-admiro::flag-select>', ['flags' => ['PL' => 'pl', 'DE' => 'de', 'XK' => null]]);
        $this->assertStringContainsString('data-ss-flag-url="' . route('admiro.flags.show', ['flag' => FlagController::FLAG_PLACEHOLDER]) . '" data-ss-flags="' . e('{"PL":"pl","DE":"de"}') . '" data-ss-flag-placeholder="' . FlagController::FLAG_PLACEHOLDER . '"', $select, 'Strona niesie kody flag i szablon adresu, nie pliki flag.');
        $this->assertMatchesRegularExpression('~<img class="ss-input-wrap__icon ss-flag ss-flag-select__flag"\s+src="' . preg_quote(route('admiro.flags.show', ['flag' => 'pl']), '~') . '"~', $select);
        $this->assertMatchesRegularExpression('~<img[^>]*\shidden\s[^>]*data-ss-flag-image>~', Blade::render('<x-admiro::flag-select :flags="[]" id="c" name="c" />'), 'Bez flagi obrazek ukryty, bez pustego adresu.');
        $this->assertStringNotContainsString('<svg class="ss-flag"', $form);
        $this->get(route('admiro.flags.show', ['flag' => 'pl']))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('id="flag-icons-pl"', false);
        $this->get(route('admiro.flags.show', ['flag' => 'zz-none']))->assertOk()->assertSee('id="flag-icons-xx"', false);
        $this->get(str_replace(FlagController::FLAG_PLACEHOLDER, 'PL_1', route('admiro.flags.show', ['flag' => FlagController::FLAG_PLACEHOLDER])))->assertNotFound();

        $this->put('/admiro/organizations/' . $organization->token, ['name' => 'Firma', ...$details, 'billing_email' => 'faktury@example.com']);
        $this->assertSame('faktury@example.com', $organization->fresh()?->billing_email);

        $this->post('/admiro/organizations', ['name' => 'Zły NIP', 'nip' => '123-456-32-19']);
        $this->assertSame(text('validation.nip'), $this->sessionError('nip'));
        $this->assertFalse(Organization::query()->where('name', 'Zły NIP')->exists());
    }

    public function test_editable_organization_cards_open_for_editing_only_where_a_field_has_an_error(): void
    {
        $cards = static fn(string $html): array => preg_match_all('~<section aria-labelledby="organization-(\w+)-title"([^>]*)>~', $html, $found, PREG_SET_ORDER) > 0
            ? array_column(array_map(static fn(array $card): array => [$card[1], $card[2]], $found), 1, 0)
            : [];
        view()->share('errors', new ViewErrorBag());

        $this->assertSame([], array_filter($cards(Blade::render('<x-admiro::organization.fields />')), static fn(string $attributes): bool => str_contains($attributes, 'data-ss-edit-card')), 'Bez editable zwykłe karty formularza.');

        $editable = $cards(Blade::render('<x-admiro::organization.fields :editable="true" />'));
        $this->assertSame(['details', 'address', 'contact'], array_keys($editable));
        $this->assertSame(3, count(array_filter($editable, static fn(string $attributes): bool => str_contains($attributes, 'data-ss-edit-card="data-ss-edit-card" data-state="edit"') && ! str_contains($attributes, 'data-ss-edit-open'))));
        $this->assertSame([], array_filter($cards(Blade::render('<x-admiro::organization.fields :editable="true" :disabled="true" />')), static fn(string $attributes): bool => str_contains($attributes, 'data-ss-edit-card')), 'Bez uprawnienia do zmiany karty tylko do odczytu.');

        view()->share('errors', (new ViewErrorBag())->put('default', new MessageBag(['phone' => [text('validation.required')]])));
        $withError = $cards(Blade::render('<x-admiro::organization.fields :editable="true" />'));
        $this->assertSame(['contact'], array_keys(array_filter($withError, static fn(string $attributes): bool => str_contains($attributes, 'data-ss-edit-open'))), 'Karta z błędem walidacji otwiera się w edycji.');
    }

    public function test_read_only_cards_present_the_data_without_labels_and_custom_fields_get_a_tab_on_each_card(): void
    {
        view()->share('errors', new ViewErrorBag());
        $organization = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Przychodnia Centrum', null, ['nip' => '1234563218', 'street' => 'ul. Prosta 1', 'postal_code' => '00001', 'city' => 'Warszawa', 'email' => 'biuro@example.com']));

        $preview = Blade::render('<x-admiro::organization.fields :organization="$organization" :disabled="true" />', ['organization' => $organization]);
        $this->assertStringNotContainsString('name="name"', $preview, 'Tylko do odczytu bez pól formularza.');
        $this->assertMatchesRegularExpression('~<p class="ss-preview__title">Przychodnia Centrum</p>\s*<ul class="ss-preview__facts">\s*<li><span class="ss-preview__fact-label">' . preg_quote(text_data(Organization::class, 'nip'), '~') . '</span> 123-456-32-18</li>\s*</ul>~', $preview, 'Nazwa, a pod nią w jednym wierszu tylko wypełnione NIP, REGON, KRS.');
        $this->assertStringContainsString('<p class="ss-preview__line">00-001 Warszawa</p>', $preview);
        $this->assertStringContainsString('biuro@example.com', $preview);
        $this->assertStringContainsString(e(text('layout.organization_form.empty')), Blade::render('<x-admiro::organization.fields :organization="$organization" :disabled="true" />', ['organization' => $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Pusta'))]));

        $editable = Blade::render('<x-admiro::organization.fields :organization="$organization" :editable="true" custom-fields />', ['organization' => $organization]);
        $this->assertSame(3, substr_count($editable, '<div class="ss-folder-card__preview ss-preview">'), 'Karty edytowalne mają podgląd obok formularza.');
        $this->assertSame(3, substr_count($editable, 'class="ss-form ss-fieldset ss-folder-card__form" data-ss-edit-fields'));
        $this->assertSame(3, substr_count($editable, '-extra-tab" aria-controls="'), 'Każda karta ma zakładkę pól dodatkowych.');
        foreach (['details' => CustomAttributes::DEFAULT_SECTION, 'address' => 'address', 'contact' => 'contact'] as $card => $section) {
            $this->assertMatchesRegularExpression('~id="organization-' . $card . '-extra-tab"[^>]*>.*?' . preg_quote(text('layout.attributes.title'), '~') . '.*?<div class="ss-folder-card__pane" id="organization-' . $card . '-extra".*?name="custom_attributes\[' . $section . '\]\[0\]\[name\]"~s', $editable, 'Zakładka „Pola dodatkowe” przy karcie ' . $card . '.');
        }
        $this->assertStringNotContainsString('-extra-tab"', Blade::render('<x-admiro::organization.fields :organization="$organization" :editable="true" />', ['organization' => $organization]), 'Bez customFields karty bez drugiej zakładki.');
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
        ])->assertRedirect($this->zoneUrl('/admiro/organizations'));
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

        $this->from($this->zoneUrl('/admiro/onboarding/organizations'))->put('/admiro/organizations/' . $branch->token . '/parent', ['parent' => $company->token])
            ->assertRedirect($this->zoneUrl('/admiro/onboarding/organizations'))
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
