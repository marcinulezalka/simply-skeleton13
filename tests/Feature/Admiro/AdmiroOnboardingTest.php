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

use Illuminate\Support\Facades\Http;
use Simplysmart\Admiro\Models\Dictionaries\Country;
use Simplysmart\Admiro\Models\Dictionaries\CountryLanguage;
use Simplysmart\Admiro\Models\Dictionaries\Currency;
use Simplysmart\Admiro\Models\Dictionaries\Language;
use Simplysmart\Admiro\Models\AdmiroAccount;
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
use Simplysmart\Admiro\Queries\Permissions\ZoneRoleNames;
use Simplysmart\Admiro\Wiki\Wiki;
use Simplysmart\Admiro\Wiki\WikiEntry;

/**
 * Kreator onboardingu: wejście do pierwszego niezrobionego kroku, krok „Języki” (wyszukiwarka w rogu z podpowiedziami
 * i przełącznikiem, panel „Język aplikacji” z ustawieniami regionalnymi — propozycja i „Zapisz jako domyślne” —,
 * przełączniki ON / OFF zapisywane od razu), krok „Organizacje” (drzewo z przeciąganiem i listą „Przenieś pod”, wiersze ze strefami i właścicielem), zatwierdzenie zamyka krok i prowadzi dalej,
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
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '1', 'total' => '12']))
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

    public function test_languages_step_has_the_search_in_the_corner_and_the_application_language_panel(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSee('<title>' . text('admiro::dashboard.onboarding_title'), false)
            ->assertSeeInOrder(['<div class="ss-onboarding-wizard__header">', 'id="ss-languages-search"', text('admiro::dashboard.onboarding_languages_enabled'), 'id="enabled-language-pl-row"', 'id="enabled-language-en-row"'], false)
            ->assertSee('<input type="hidden" name="in[]" value="languages">', false)
            ->assertSee('<span class="ss-badge" title="' . text('admiro::dashboard.onboarding_languages_active_count', ['active' => 2, 'all' => 3]) . '">2/3</span>', false)
            ->assertDontSee('<form class="ss-search ss-onboarding-picker__search" role="search" method="GET" action="' . url('/admiro/onboarding/languages') . '" data-ss-live-search', false)
            ->assertDontSee('id="ss-languages-all-title"', false)
            ->assertDontSee('id="language-de"', false)
            ->assertSee('<label class="ss-switch ss-switch--knob" for="enabled-language-en">', false)
            ->assertDontSee(route('admiro.onboarding.confirm', ['step' => 'languages']), false)
            ->assertSee('<a class="ss-context__back ss-link" href="' . url('/admiro/onboarding') . '">', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link" href="' . url('/admiro/onboarding/languages') . '" aria-current="step">', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link ss-onboarding-wizard__step-link--done" href="' . url('/admiro/onboarding/installation') . '">', false);
    }

    public function test_default_language_proposes_regional_settings_and_saves_them_as_default_at_once(): void
    {
        $poland = Country::query()->where('alpha_code_2', 'PL')->sole();
        $germany = Country::query()->where('alpha_code_2', 'DE')->sole();
        $zloty = Currency::query()->where('alpha_code_3', 'PLN')->sole();
        $poland->forceFill(['currency_id' => $zloty->id, 'is_default' => false])->save();
        $germany->forceFill(['is_default' => true])->save();
        $polish = Language::query()->where('code', 'pl')->sole();
        $poland->languages()->attach($polish->id, [CountryLanguage::IS_PRIMARY => true]);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSee('<dl class="ss-onboarding-languages__regional ss-onboarding-languages__regional--proposed"', false)
            ->assertSeeInOrder(['id="enabled-language-pl-row"', 'PLN · ', text('admiro::dashboard.onboarding_regional_proposed'), text('admiro::dashboard.onboarding_regional_save'), '<span class="ss-default-mark" role="img" aria-label="' . text('admiro::dashboard.onboarding_language_default') . ': ' . text('admiro::dashboard.onboarding_language_locked') . '"'], false)
            ->assertSee('action="' . route('admiro.onboarding.regional') . '"', false);

        $this->post('/admiro/onboarding/languages/regional', ['language' => 'pl'])
            ->assertRedirect('/admiro/onboarding/languages')
            ->assertSessionHas('status', text('admiro::dashboard.onboarding_regional_saved'));

        $this->assertTrue($poland->fresh()?->is_default);
        $this->assertFalse($germany->fresh()?->is_default);
        $this->assertTrue($zloty->fresh()?->is_default);
        $this->assertTrue($this->stepDone(OnboardingStep::Settings));
        $this->assertTrue($this->stepDone(OnboardingStep::Languages));
        $this->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertDontSee('action="' . route('admiro.onboarding.regional') . '"', false)
            ->assertDontSee('>' . text('admiro::dashboard.onboarding_regional_proposed') . '</span>', false);
        $this->post('/admiro/onboarding/languages/regional', ['language' => 'de'])->assertSessionHasErrors('language');
    }

    public function test_language_suggestions_switch_it_on_and_any_language_switch_closes_the_step(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/search/suggest?q=niem&in[]=languages')
            ->assertOk()
            ->assertSee('data-ss-post-toggle="' . route('admiro.dictionaries.toggle', ['dictionary' => 'languages', 'code' => 'de']) . '"', false)
            ->assertSee(url('/admiro/onboarding/languages') . '?q=niem#language-de', false);
        $this->get('/admiro/search/suggest?q=pl&in[]=languages')
            ->assertOk()
            ->assertSee('<span class="ss-default-mark" role="img" aria-label="' . text('admiro::dashboard.onboarding_language_default') . '" title="' . text('admiro::dashboard.onboarding_language_default') . '"><span class="ss-icon ss-default-mark__icon">', false);

        $this->post('/admiro/dictionaries/languages/de/toggle')->assertRedirect();

        $this->assertTrue(Language::query()->where('code', 'de')->sole()->is_active);
        $this->assertTrue($this->stepDone(OnboardingStep::Languages));
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
            ->assertSee('<span class="ss-default-mark" role="img" aria-label="' . text('admiro::dashboard.onboarding_organization_default') . ': ' . text('admiro::dashboard.onboarding_organization_locked') . '"', false)
            ->assertSeeInOrder(['<div class="ss-onboarding-organizations__row ss-onboarding-organizations__row--inactive" data-ss-tree-row>', 'Wyłączona Firma', text('admiro::admin.state_inactive')], false)
            ->assertDontSee(route('admiro.onboarding.confirm', ['step' => 'organizations']), false);
        $this->assertMatchesRegularExpression('~<form class="ss-search ss-onboarding-picker__search ss-onboarding-wizard__search".*?<p class="ss-search__menu-title">' . preg_quote(text('layout.search_actions'), '~') . '</p>\s*<ul class="ss-account-menu__list">\s*<li><a class="ss-account-menu__link" href="' . preg_quote(url('/admiro/organizations/create'), '~') . '">~s', (string) $response->getContent());
    }

    public function test_organization_rows_link_member_zones_and_show_the_owner_or_a_link_to_add_one(): void
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

        $this->assertMatchesRegularExpression('~Przychodnia Zdrowie.*?<a class="ss-onboarding-organizations__zone"\s+href="' . preg_quote(route('admiro.zones.accounts', ['managedZone' => 'alpha']), '~') . '"\s+title="alpha · /alpha">\s*<span class="ss-icon ss-onboarding-organizations__zone-icon">.*?</span>\s*alpha\s*</a>.*?Jan Kowalski.*?</li>~s', $html);
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
            ->assertSee('<span class="ss-default-mark" role="img" aria-label="' . text('admiro::dashboard.onboarding_organization_default') . '" title="' . text('admiro::dashboard.onboarding_organization_default') . '"><span class="ss-icon ss-default-mark__icon">', false);

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
        $this->assertMatchesRegularExpression('~<span class="ss-avatars">\s*<a class="ss-onboarding-organizations__owner" href="' . preg_quote(e(route('admiro.zones.accounts', ['managedZone' => 'alpha', 'email' => 'jan@example.com'])), '~') . '" title="Jan Kowalski"><span class="ss-avatar ss-avatar--sm[^"]*"\s+role="img" aria-label="Jan Kowalski"~', $html);
        $this->assertMatchesRegularExpression('~<label class="ss-switch ss-switch--knob" for="[^"]+">.*?<span class="ss-switch__track" aria-hidden="true"\s+data-on="ON" data-off="OFF"\s*></span>~s', $html);

        $this->post('/admiro/organizations/' . $clinic->token . '/toggle');
        $this->assertSame(RecordStatus::Active, $clinic->fresh()?->status);
        $this->assertTrue($clinic->fresh()?->is_active);
    }

    public function test_organizations_step_explains_the_term_with_a_wiki_tip_and_shows_zone_favicons(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $html = (string) $this->get('/admiro/onboarding/organizations')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<aside class="ss-wiki-tip" id="wiki-organizacja" role="note" aria-labelledby="wiki-organizacja-term">.*?wiki-organizacja.*?<strong class="ss-wiki-tip__term" id="wiki-organizacja-term">' . preg_quote(text('wiki.organizacja.term'), '~') . '</strong>.*?' . preg_quote(e(text('wiki.organizacja.definition')), '~') . '.*?<span class="ss-wiki-tip__example-label">' . text('wiki.example') . '</span>~s', $html);
        $this->assertLessThan(strpos($html, 'ss-onboarding-picker__enabled'), strpos($html, 'id="wiki-organizacja"'));
        $this->assertMatchesRegularExpression('~<a class="ss-onboarding-organizations__zone"\s+href="' . preg_quote(route('admiro.zones.accounts', ['managedZone' => 'admiro']), '~') . '"\s+title="admiro · /admiro">\s*<img class="ss-onboarding-organizations__zone-favicon" src="[^"]*/favicon/svg~', $html);
    }

    public function test_wiki_lists_core_terms_with_the_prefix_and_packages_add_their_own(): void
    {
        $wiki = $this->app->make(Wiki::class);
        $wiki->add('cennik', 'invoice', 'shop::');

        $keys = array_map(static fn(WikiEntry $entry): string => $entry->key, $wiki->all());
        $this->assertSame(['organizacja', 'organizacja-operatora', 'departament', 'strefa', 'wlasciciel'], array_slice($keys, 0, 5));
        $this->assertSame('cennik', $keys[array_key_last($keys)]);
        $this->assertSame(text('wiki.departament.question'), $wiki->entry('departament')?->question);
        $this->assertSame(['departament'], array_map(static fn(WikiEntry $entry): string => $entry->key, $wiki->search('RÓŻNI SIĘ OD')));
        $this->assertSame('wiki-departament', $wiki->entry('departament')?->id());
        $this->assertNull($wiki->entry('nieznane'));
        $this->assertSame('shop::wiki.cennik.term', $wiki->entry('cennik')?->term);
        $this->assertNull($wiki->entry('cennik')?->example);
    }

    public function test_wiki_page_lists_tips_and_questions_with_search_and_steps_explain_their_term(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/wiki')
            ->assertOk()
            ->assertSee('id="wiki-organizacja"', false)
            ->assertSee('id="wiki-departament"', false)
            ->assertSee('<p class="ss-wiki-tip__question"><span class="ss-wiki-tip__qa" aria-hidden="true">Q</span>' . e(text('wiki.departament.question')) . '</p>', false)
            ->assertSee(route('admiro.wiki'), false);
        $this->get('/admiro/wiki?q=strefach')->assertOk()->assertSee('id="wiki-strefa"', false)->assertDontSee('id="wiki-organizacja"', false);
        $this->get('/admiro/wiki?q=zzz')->assertOk()->assertSee(text('wiki.none', ['term' => 'zzz']));
        $this->get('/admiro/onboarding/owners')->assertOk()->assertSee('id="wiki-wlasciciel"', false);
        $this->get('/admiro/onboarding/organizations')->assertOk()->assertSee(text('admiro::dashboard.onboarding_organizations_enabled'))->assertSee('id="wiki-organizacja"', false);
        $this->get('/admiro/onboarding/languages')->assertOk()->assertDontSee('class="ss-wiki-tip"', false);
    }

    public function test_profiles_step_lists_role_profiles_and_toggles_administrator_roles_at_once(): void
    {
        $hero = $this->admin('hero@example.com', 'hero');
        $support = $this->admin('support@example.com', 'support');
        $this->signedIn($hero, 'admiro');
        $wizard = '/admiro/onboarding/administrators';

        $html = (string) $this->get($wizard)->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<div class="ss-onboarding-wizard__header">.*?id="ss-admins-search"~s', $html);
        $this->assertStringContainsString(text('admiro::dashboard.onboarding_admins_profiles'), $html);
        $this->assertStringContainsString(e(text('roles.admiro.owner')), $html);
        $this->assertStringContainsString('id="administrator-' . $support->getRouteKey() . '"', $html);
        $this->assertStringContainsString('action="' . route('admiro.administrators.roles.toggle', ['account' => $support->getRouteKey(), 'role' => 'owner']) . '" data-ss-autosubmit>', $html);
        $this->assertStringNotContainsString(route('admiro.administrators.roles.toggle', ['account' => $hero->getRouteKey(), 'role' => 'owner']), $html);
        $this->assertStringContainsString(text('admiro::dashboard.onboarding_admins_self'), $html);
        $this->assertStringNotContainsString(text('admiro::dashboard.onboarding_open', ['name' => text('admiro::dashboard.onboarding_administrators_title')]), $html);

        $this->from($wizard)->post(route('admiro.administrators.roles.toggle', ['account' => $support->getRouteKey(), 'role' => 'owner']))->assertRedirect($wizard);
        $this->assertContains('owner', $this->rolesOf($support));
        $this->from($wizard)->post(route('admiro.administrators.roles.toggle', ['account' => $support->getRouteKey(), 'role' => 'owner']))->assertRedirect($wizard);
        $this->assertNotContains('owner', $this->rolesOf($support));
        $this->assertTrue($this->stepDone(OnboardingStep::Administrators));

        $this->get($wizard . '?q=support')->assertOk()->assertSee('id="ss-admins-all-title"', false)->assertSee('id="administrator-' . $support->getRouteKey() . '"', false);
        $this->get('/admiro/search/suggest?q=support&in[]=directory')->assertOk()->assertSee(e(route('admiro.onboarding.show', ['step' => 'administrators', 'q' => 'support'])) . '#administrator-' . $support->getRouteKey(), false);
    }

    public function test_dictionary_steps_list_enabled_entries_with_their_facts_and_switch_them_at_once(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/countries')
            ->assertOk()
            ->assertSeeInOrder(['<div class="ss-onboarding-wizard__header">', 'id="ss-countries-search"', text('admiro::dashboard.onboarding_countries_panel'), 'id="country-PL"'], false)
            ->assertSee('<input type="hidden" name="in[]" value="dictionaries">', false)
            ->assertSee('<span class="ss-default-mark" role="img" aria-label="' . text('admiro::dashboard.onboarding_dictionary_default') . ': ' . text('admiro::dashboard.onboarding_dictionary_default_hint') . '"', false)
            ->assertSee('id="country-DE"', false)
            ->assertSee('action="' . route('admiro.dictionaries.toggle', ['dictionary' => 'countries', 'code' => 'DE']) . '" data-ss-autosubmit>', false)
            ->assertDontSee('action="' . route('admiro.dictionaries.toggle', ['dictionary' => 'countries', 'code' => 'PL']) . '"', false);

        $this->post('/admiro/dictionaries/countries/DE/toggle')->assertRedirect();
        $this->assertTrue($this->stepDone(OnboardingStep::Countries));
        $this->get('/admiro/onboarding/countries')->assertDontSee('id="country-DE"', false);
        $this->get('/admiro/onboarding/countries?q=DE')->assertOk()->assertSee('id="ss-dictionary-results-title"', false)->assertSee('id="country-DE"', false);

        $this->get('/admiro/onboarding/currencies')
            ->assertOk()
            ->assertSee('id="currency-PLN"', false)
            ->assertSee(text('admiro::dashboard.onboarding_currency_base'))
            ->assertSee('action="' . route('admiro.exchange-rates.sync') . '"', false)
            ->assertSee('<input type="hidden" name="onboarding" value="currencies">', false);
        $this->post('/admiro/dictionaries/currencies/EUR/toggle');
        $this->assertTrue($this->stepDone(OnboardingStep::Currencies));

        $this->get('/admiro/onboarding/taxes')->assertOk()->assertSee('id="country-PL"', false)->assertSee(route('admiro.tax-rates.index', ['country' => 'PL']), false);
        $this->post('/admiro/tax-rates/PL', ['type' => 'standard', 'rate' => '23', 'valid_from' => '2011-01-01']);
        $this->assertTrue($this->stepDone(OnboardingStep::Taxes));
        $this->get('/admiro/onboarding/taxes')->assertSee(text('admiro::dashboard.onboarding_taxes_standard'))->assertSee('23%');
    }

    public function test_calendar_step_fetches_holidays_and_returns_to_the_wizard(): void
    {
        $year = (int) now()->year;
        Http::fake(['date.nager.at/*' => Http::response([['date' => $year . '-12-25', 'localName' => 'Boże Narodzenie', 'name' => 'Christmas Day', 'global' => true, 'types' => ['Public']]])]);
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/calendar')
            ->assertOk()
            ->assertSee('id="country-PL"', false)
            ->assertSee(text('admiro::dashboard.onboarding_calendar_sync', ['year' => $year]));

        $this->post('/admiro/holidays/sync', ['country' => 'PL', 'year' => $year, 'onboarding' => 'calendar'])->assertRedirect('/admiro/onboarding/calendar');

        $this->assertTrue($this->stepDone(OnboardingStep::Calendar));
        $this->get('/admiro/onboarding/calendar')->assertOk()->assertSee('Boże Narodzenie');
    }

    public function test_confirming_languages_closes_the_step_and_moves_on(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->post('/admiro/onboarding/languages/confirm')->assertRedirect('/admiro/onboarding/countries');
        $this->get('/admiro/onboarding')->assertOk()->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '2', 'total' => '12']));
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

    private function stepDone(OnboardingStep $step): bool
    {
        return $this->app->make(OnboardingProgress::class)->checklist([$step])[0]['done'];
    }

    /**
     * @return list<string>
     */
    private function rolesOf(AdmiroAccount $account): array
    {
        return $this->app->make(ZoneRoleNames::class)->of($account, Organization::OPERATOR_ID);
    }
}
