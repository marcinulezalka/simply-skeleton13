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

use Simplysmart\Admiro\Models\Dictionaries\Language;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Enums\RecordStatus;
use Simplysmart\Admiro\Organizations\OrganizationBadges;
use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Panel\Onboarding\OnboardingProgress;
use Simplysmart\Admiro\Panel\Enums\OnboardingStep;
use Simplysmart\Admiro\Permissions\OwnerData;
use Simplysmart\Admiro\Permissions\PermissionFiles;
use Simplysmart\Admiro\Permissions\ZoneOwnerProvisioners;

/**
 * Kreator onboardingu: wejście do pierwszego niezrobionego kroku, krok „Języki” (włączone osobno nad listą,
 * przełączniki, flagi krajów w kolumnie kodu, filtr z wyszukiwarki), krok „Organizacje” (drzewo z przeciąganiem i listą „Przenieś pod”, wiersze ze strefami i właścicielem), zatwierdzenie zamyka krok i prowadzi dalej,
 * kroki spoza uprawnień to 404.
 */
class AdmiroOnboardingTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
        (new Language())->forceFill(['name' => 'niemiecki', 'code' => 'de', 'is_active' => false, 'json_attributes' => [Language::FLAG_ICONS => ['de', 'at', 'ch']]])->save();
    }

    public function test_entry_shows_the_steps_overview_with_the_installation_done_and_links_to_the_wizard(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '1', 'total' => '8']))
            ->assertSee('href="' . url('/admiro/onboarding/languages') . '"', false);

        $this->get('/admiro/onboarding/installation')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_installation_title'))
            ->assertSee(text('admiro::dashboard.onboarding_installation_guide.hero.title'))
            ->assertSee('php artisan admiro:account:create &lt;e-mail&gt; &quot;&lt;imię i nazwisko&gt;&quot; --role=hero', false)
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . ' <time class="ss-onboarding-wizard__completed"', false)
            ->assertSee(local_date(Organization::query()->findOrFail(Organization::OPERATOR_ID)->created_at ?? now(), 'D MMM YYYY'))
            ->assertSee('<nav class="ss-onboarding-wizard__remaining" aria-label="' . text('admiro::dashboard.onboarding_remaining') . '">', false)
            ->assertSee('<a class="ss-icon-button ss-onboarding-wizard__remaining-link" href="' . url('/admiro/onboarding/languages') . '"', false)
            ->assertDontSee('<a class="ss-icon-button ss-onboarding-wizard__remaining-link" href="' . url('/admiro/onboarding/installation') . '"', false);
    }

    public function test_languages_step_shows_enabled_languages_above_a_search_field_without_the_full_list(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSee('<title>' . text('admiro::dashboard.onboarding_title'), false)
            ->assertSeeInOrder([text('admiro::dashboard.onboarding_languages_enabled'), text('admiro::dashboard.onboarding_languages_search_title'), 'id="ss-languages-search"'], false)
            ->assertSee('data-ss-live-search="ss-languages-results"', false)
            ->assertSee('<div class="ss-onboarding-picker__live" id="ss-languages-results" aria-live="polite">', false)
            ->assertSee(text('admiro::dashboard.onboarding_languages_hint'))
            ->assertDontSee('id="language-de"', false)
            ->assertDontSee('<p class="ss-context__meta">', false)
            ->assertSee('<a class="ss-context__back ss-link" href="' . url('/admiro/onboarding') . '">', false)
            ->assertSee('<span class="ss-onboarding-wizard__step-number">' . text('admiro::dashboard.onboarding_step', ['number' => '1']) . '</span>', false)
            ->assertDontSee('type="submit" data-ss-autosubmit-fallback', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link" href="' . url('/admiro/onboarding/languages') . '" aria-current="step">', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link ss-onboarding-wizard__step-link--done" href="' . url('/admiro/onboarding/installation') . '">', false)
            ->assertDontSee('<span class="ss-onboarding-wizard__step-lead">' . e(text('admiro::dashboard.onboarding_installation_lead')), false)
            ->assertSee('<span class="ss-onboarding-wizard__step-lead">' . e(text('admiro::dashboard.onboarding_settings_lead')), false)
            ->assertSee(route('admiro.onboarding.confirm', ['step' => 'languages']), false);
    }

    public function test_enabled_languages_show_the_default_one_first_with_regional_settings_and_the_others_one_per_row(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSeeInOrder([
                '<header class="ss-onboarding-languages__header">',
                '<div class="ss-onboarding-languages__default">',
                '<span class="ss-badge ss-badge--outline ss-badge--info" title="' . text('admiro::dashboard.onboarding_language_locked') . '">' . text('admiro::dashboard.onboarding_language_default') . '</span>',
                '<a class="ss-onboarding-languages__regional ss-onboarding-languages__regional--missing" href="' . url('/admiro/onboarding/settings') . '">',
                '<ul class="ss-onboarding-languages__rows">',
                'role="switch" id="enabled-language-en"',
            ], false);

        $this->app->make(OnboardingProgress::class)->mark(OnboardingStep::Settings->value);

        $this->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertDontSee('ss-onboarding-languages__regional--missing" href', false)
            ->assertSee('<dl class="ss-onboarding-languages__regional" aria-label="' . text('admiro::dashboard.onboarding_regional_title') . '">', false)
            ->assertSee('PLN · ', false)
            ->assertSee('<div class="ss-onboarding-languages__regional-item ss-onboarding-languages__regional-item--missing">', false)
            ->assertSee('<a class="ss-link" href="' . url('/admiro/holidays') . '">' . text('admiro::dashboard.onboarding_regional_set') . '</a>', false);
    }

    public function test_search_puts_the_default_language_first(): void
    {
        (new Language())->forceFill(['name' => 'laotański', 'code' => 'lo', 'is_active' => false])->save();

        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages?q=l')
            ->assertOk()
            ->assertSeeInOrder(['id="language-pl"', 'id="language-lo"'], false);
    }

    public function test_search_term_shows_matching_languages_in_panels_with_switches_and_flags(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages?q=niem')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_languages_results', ['term' => 'niem']))
            ->assertSee('<li class="ss-card ss-onboarding-picker__result" id="language-de">', false)
            ->assertSee('id="flag-icons-at"', false)
            ->assertSee('role="switch" id="language-de"', false)
            ->assertDontSee('type="submit" data-ss-autosubmit-fallback', false)
            ->assertDontSee('id="language-en"', false);

        $this->get('/admiro/search/suggest?q=niem&in[]=languages')
            ->assertOk()
            ->assertSee(url('/admiro/onboarding/languages') . '?q=niem#language-de', false);
    }

    public function test_organizations_step_shows_active_organizations_above_a_search_field_with_the_add_menu(): void
    {
        (new Organization())->forceFill(['name' => 'Wyłączona Firma', 'is_active' => false])->save();
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);

        $response = $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/organizations');

        $response->assertOk()
            ->assertSeeInOrder(['<h2 class="ss-onboarding-wizard__title"', '<p class="ss-onboarding-wizard__status">', 'id="ss-organizations-search"', text('admiro::dashboard.onboarding_organizations_enabled'), $operator->name], false)
            ->assertDontSee('<p class="ss-muted ss-onboarding-wizard__lead">', false)
            ->assertSee('<input type="hidden" name="in[]" value="directory">', false)
            ->assertDontSee('role="switch" id="enabled-organization-' . $operator->token . '"', false)
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--info" title="' . text('admiro::dashboard.onboarding_organization_locked') . '">' . text('admiro::dashboard.onboarding_organization_default') . '</span>', false)
            ->assertSeeInOrder(['<div class="ss-onboarding-organizations__row ss-onboarding-organizations__row--inactive" data-ss-tree-row>', 'Wyłączona Firma', text('admiro::admin.state_inactive')], false)
            ->assertDontSee(route('admiro.onboarding.confirm', ['step' => 'organizations']), false);
        $this->assertMatchesRegularExpression('~<form class="ss-search ss-onboarding-picker__search ss-onboarding-wizard__search".*?<p class="ss-search__menu-title">' . preg_quote(text('layout.search_actions'), '~') . '</p>\s*<ul class="ss-account-menu__list">\s*<li><a class="ss-account-menu__link" href="' . preg_quote(url('/admiro/organizations/create'), '~') . '">~s', (string) $response->getContent());
    }

    public function test_organization_rows_show_member_zones_and_the_owner_or_a_link_to_add_one(): void
    {
        $this->app->make(ZoneOwnerProvisioners::class)->addDefault('alpha');
        $this->app->make(PermissionFiles::class)->add(__DIR__ . '/../../Fixtures/Permissions');
        $this->command('admiro:permissions:sync', ['--zone' => 'alpha'])->assertSuccessful();
        $clinic = new Organization();
        $clinic->forceFill(['name' => 'Przychodnia Zdrowie', 'is_active' => true])->save();
        $empty = new Organization();
        $empty->forceFill(['name' => 'Firma Bez Strefy', 'is_active' => true])->save();
        $this->app->make(ZoneOwnerProvisioners::class)->for('alpha')->provisionOwner(new OwnerData('Jan Kowalski', 'jan@example.com'), $clinic);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $response = $this->get('/admiro/onboarding/organizations')->assertOk();
        $html = (string) $response->getContent();

        $this->assertMatchesRegularExpression('~Przychodnia Zdrowie.*?<span class="ss-badge ss-badge--outline" title="/alpha">alpha</span>.*?Jan Kowalski.*?</li>~s', $html);
        $this->assertMatchesRegularExpression('~Firma Bez Strefy.*?' . preg_quote(text('admiro::dashboard.onboarding_organization_no_zone'), '~') . '.*?href="' . preg_quote(route('admiro.owners.create', ['organization' => $empty->token]), '~') . '">' . preg_quote(text('admiro::dashboard.onboarding_organization_add_owner'), '~') . '</a>~s', $html);
        $this->get(route('admiro.owners.create', ['organization' => $empty->token]))
            ->assertOk()
            ->assertSee('<option value="' . $empty->token . '" selected>', false);
    }

    public function test_organizations_are_a_draggable_tree_without_a_parent_select(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $company = $management->create(new OrganizationData('Grupa Medyczna'));
        $branch = $management->create(new OrganizationData('Oddział Północ'), $company);
        $department = $management->createDepartment(new OrganizationData('Poradnia K'), $branch);
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $html = (string) $this->get('/admiro/onboarding/organizations')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="ss-onboarding-organizations__tree" data-ss-tree>', $html);
        $this->assertMatchesRegularExpression('~data-ss-tree-item="' . $company->token . '"\s+draggable="true".*?<ul class="ss-onboarding-organizations ss-onboarding-organizations--children">.*?data-ss-tree-item="' . $branch->token . '"\s+draggable="true".*?data-ss-tree-item="' . $department->token . '"\s+data-ss-tree-locked~s', $html);
        $this->assertDoesNotMatchRegularExpression('~data-ss-tree-item="' . $operator->token . '"\\s+draggable~', $html);
        $this->assertStringContainsString('action="' . route('admiro.organizations.move', ['organization' => $branch->token]) . '" hidden data-ss-tree-move>', $html);
        $this->assertStringNotContainsString('<select class="ss-input" id="move-', $html);
        $this->assertStringNotContainsString('action="' . route('admiro.organizations.move', ['organization' => $department->token]) . '"', $html);
        $this->assertStringContainsString('data-ss-tree-root', $html);
    }

    public function test_organization_suggestions_switch_it_on_and_lead_to_the_results_under_the_search(): void
    {
        $company = new Organization();
        $company->forceFill(['name' => 'Wyłączona Firma', 'is_active' => false])->save();
        $operator = Organization::query()->findOrFail(Organization::OPERATOR_ID);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $wizard = route('admiro.onboarding.show', ['step' => 'organizations', 'q' => 'wyłączona']);

        $this->get('/admiro/search/suggest?q=wy%C5%82%C4%85czona&in[]=directory')
            ->assertOk()
            ->assertSee('href="' . e($wizard) . '#organization-' . $company->token . '"', false)
            ->assertSee('data-ss-post-toggle="' . route('admiro.organizations.toggle', ['organization' => $company->token]) . '"', false)
            ->assertSee(text('admiro::admin.state_inactive'));
        $this->get('/admiro/search/suggest?q=' . urlencode($operator->name) . '&in[]=directory')
            ->assertOk()
            ->assertDontSee('data-ss-post-toggle="' . route('admiro.organizations.toggle', ['organization' => $operator->token]) . '"', false)
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--info">' . text('admiro::dashboard.onboarding_organization_default') . '</span>', false);

        $this->from($wizard)->post('/admiro/organizations/' . $company->token . '/toggle', [], ['X-Requested-With' => 'XMLHttpRequest'])->assertRedirect($wizard);
        $this->assertTrue($company->fresh()?->is_active);
        $this->get('/admiro/onboarding/organizations')->assertSeeInOrder(['id="ss-organizations-search"', text('admiro::dashboard.onboarding_organizations_enabled'), 'Wyłączona Firma'], false);
    }

    public function test_disabled_organization_from_the_results_is_dragged_into_the_active_tree(): void
    {
        $company = new Organization();
        $company->forceFill(['name' => 'Wyłączona Firma', 'is_active' => false])->save();
        $parent = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Grupa Aktywna'));
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $wizard = '/admiro/onboarding/organizations?q=wy%C5%82%C4%85czona';

        $html = (string) $this->get($wizard)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<li class="ss-card ss-onboarding-picker__result" id="organization-' . $company->token . '"\s+draggable="true" data-ss-tree-item="' . $company->token . '"~', $html);
        $this->assertStringContainsString('<input type="hidden" name="activate" value="1">', $html);

        $this->from($wizard)->put('/admiro/organizations/' . $company->token . '/parent', ['parent' => $parent->token, 'activate' => '1'])->assertRedirect($wizard);

        $this->assertTrue($company->fresh()?->is_active);
        $this->assertSame($parent->id, $company->fresh()?->parent_id);
    }

    public function test_organization_found_in_the_wizard_is_switched_on_at_once_and_the_wizard_stays_open(): void
    {
        $company = new Organization();
        $company->forceFill(['name' => 'Wyłączona Firma', 'is_active' => false])->save();
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $wizard = '/admiro/onboarding/organizations?q=wy%C5%82%C4%85czona';

        $this->get($wizard)
            ->assertOk()
            ->assertSee('<li class="ss-card ss-onboarding-picker__result" id="organization-' . $company->token . '"', false)
            ->assertSee('<form method="POST" action="' . route('admiro.organizations.toggle', ['organization' => $company->token]) . '" data-ss-autosubmit>', false);

        $this->from($wizard)->post('/admiro/organizations/' . $company->token . '/toggle')->assertRedirect($wizard);
        $this->assertTrue($company->fresh()?->is_active);
    }

    public function test_task_card_has_the_state_under_the_title_and_done_tasks_as_filled_icons_in_the_footer(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $html = (string) $this->get('/admiro/onboarding/organizations?q=xyz')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<p class="ss-onboarding-wizard__status">\s*<span class="ss-badge ss-badge--outline ss-badge--~', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-onboarding-wizard__header">.*?<form class="ss-search ss-onboarding-picker__search ss-onboarding-wizard__search"~s', $html);
        $this->assertStringContainsString('<nav class="ss-onboarding-wizard__remaining ss-onboarding-wizard__finished" aria-label="' . text('admiro::dashboard.onboarding_finished') . '">', $html);
        $this->assertStringContainsString('<a class="ss-icon-button ss-onboarding-wizard__finished-link" href="' . route('admiro.onboarding.show', ['step' => 'installation']) . '"', $html);
        $this->assertStringNotContainsString('arrow-left', substr($html, (int) strpos($html, 'ss-onboarding-wizard__footer')));
        $this->assertLessThan(strpos($html, 'ss-onboarding-picker__enabled'), strpos($html, text('admiro::dashboard.onboarding_organizations_none', ['term' => 'xyz'])));
    }

    public function test_disabled_organization_is_blocked_and_stays_in_the_tree_with_owner_avatars_and_package_badges(): void
    {
        $this->app->make(ZoneOwnerProvisioners::class)->addDefault('alpha');
        $this->app->make(PermissionFiles::class)->add(__DIR__ . '/../../Fixtures/Permissions');
        $this->command('admiro:permissions:sync', ['--zone' => 'alpha'])->assertSuccessful();
        $clinic = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Przychodnia Zdrowie'));
        $this->app->make(ZoneOwnerProvisioners::class)->for('alpha')->provisionOwner(new OwnerData('Jan Kowalski', 'jan@example.com'), $clinic);
        $this->app->make(OrganizationBadges::class)->add(static fn(array $ids): array => in_array($clinic->id, $ids, true) ? [$clinic->id => [['label' => 'Partner', 'tone' => 'info']]] : []);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->from('/admiro/onboarding/organizations')->post('/admiro/organizations/' . $clinic->token . '/toggle')->assertRedirect('/admiro/onboarding/organizations');

        $clinic->refresh();
        $this->assertFalse($clinic->is_active);
        $this->assertSame(RecordStatus::Blocked, $clinic->status);
        $html = (string) $this->get('/admiro/onboarding/organizations')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~ss-onboarding-organizations__row--inactive" data-ss-tree-row>.*?Przychodnia Zdrowie.*?<span class="ss-badge ss-badge--outline ss-badge--warning">' . preg_quote(RecordStatus::Blocked->label(), '~') . '</span>\s*<span class="ss-badge ss-badge--outline ss-badge--info">Partner</span>~s', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-avatars">\s*<span class="ss-avatar ss-avatar--sm[^"]*" title="Jan Kowalski"\s+role="img" aria-label="Jan Kowalski"~', $html);

        $this->post('/admiro/organizations/' . $clinic->token . '/toggle');
        $this->assertSame(RecordStatus::Active, $clinic->fresh()?->status);
        $this->assertTrue($clinic->fresh()?->is_active);
    }

    public function test_confirming_languages_closes_the_step_and_moves_on(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->post('/admiro/onboarding/languages/confirm')->assertRedirect('/admiro/onboarding/settings');
        $this->get('/admiro/onboarding')->assertOk()->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '2', 'total' => '8']));
        $this->get('/admiro/onboarding/settings')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_open', ['name' => text('admiro::dashboard.onboarding_settings_title')]))
            ->assertSee(route('admiro.onboarding.show', ['step' => 'languages']), false);
        $this->get('/admiro/onboarding/languages')
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . ' <time class="ss-onboarding-wizard__completed"', false)
            ->assertSee(local_date(now(), 'D MMM YYYY'));
        $this->post('/admiro/onboarding/settings/confirm')->assertNotFound();
    }

    public function test_opening_the_audit_log_closes_the_audit_step(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/audit')->assertSee(text('admiro::dashboard.onboarding_states.not_started'));
        $this->get('/admiro/audit')->assertOk();
        $this->get('/admiro/onboarding/audit')->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . ' <time class="ss-onboarding-wizard__completed"', false);
    }

    public function test_steps_outside_the_permissions_are_not_found(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/onboarding/languages')->assertNotFound();
        $this->post('/admiro/onboarding/languages/confirm')->assertForbidden();
        $this->assertNotNull(Organization::query()->find(Organization::OPERATOR_ID));
    }
}
