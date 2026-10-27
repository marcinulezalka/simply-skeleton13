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
use Simplysmart\Admiro\Panel\Onboarding\OnboardingProgress;
use Simplysmart\Admiro\Panel\Enums\OnboardingStep;

/**
 * Kreator onboardingu: wejście do pierwszego niezrobionego kroku, krok „Języki” (włączone osobno nad listą,
 * przełączniki, flagi krajów w kolumnie kodu, filtr z wyszukiwarki), zatwierdzenie zamyka krok i prowadzi dalej,
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
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . '</span>', false)
            ->assertSee(text('admiro::dashboard.onboarding_completed_at', ['date' => local_date(Organization::query()->findOrFail(Organization::OPERATOR_ID)->created_at ?? now(), 'D MMM YYYY')]))
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
                '<span class="ss-badge ss-badge--outline ss-badge--info">' . text('admiro::dashboard.onboarding_language_default') . '</span>',
                'role="switch" id="enabled-language-pl"',
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
            ->assertSeeInOrder([text('admiro::dashboard.onboarding_organizations_enabled'), $operator->name, text('admiro::dashboard.onboarding_organizations_search_title'), 'id="ss-organizations-search"'], false)
            ->assertSee('<input type="hidden" name="in[]" value="directory">', false)
            ->assertSee('role="switch" id="enabled-organization-' . $operator->token . '"', false)
            ->assertDontSee('Wyłączona Firma')
            ->assertDontSee(route('admiro.onboarding.confirm', ['step' => 'organizations']), false);
        $this->assertMatchesRegularExpression('~<form class="ss-search ss-onboarding-picker__search".*?<p class="ss-search__menu-title">' . preg_quote(text('layout.search_actions'), '~') . '</p>\s*<ul class="ss-account-menu__list">\s*<li><a class="ss-account-menu__link" href="' . preg_quote(url('/admiro/organizations/create'), '~') . '">~s', (string) $response->getContent());
    }

    public function test_organization_found_in_the_wizard_is_switched_on_at_once_and_the_wizard_stays_open(): void
    {
        $company = new Organization();
        $company->forceFill(['name' => 'Wyłączona Firma', 'is_active' => false])->save();
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');
        $wizard = '/admiro/onboarding/organizations?q=wy%C5%82%C4%85czona';

        $this->get($wizard)
            ->assertOk()
            ->assertSee('<li class="ss-card ss-onboarding-picker__result" id="organization-' . $company->token . '">', false)
            ->assertSee('<form method="POST" action="' . route('admiro.organizations.toggle', ['organization' => $company->token]) . '" data-ss-autosubmit>', false);

        $this->from($wizard)->post('/admiro/organizations/' . $company->token . '/toggle')->assertRedirect($wizard);
        $this->assertTrue($company->fresh()?->is_active);
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
            ->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . '</span>', false)
            ->assertSee(text('admiro::dashboard.onboarding_completed_at', ['date' => local_date(now(), 'D MMM YYYY')]));
        $this->post('/admiro/onboarding/settings/confirm')->assertNotFound();
    }

    public function test_opening_the_audit_log_closes_the_audit_step(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->get('/admiro/onboarding/audit')->assertSee(text('admiro::dashboard.onboarding_states.not_started'));
        $this->get('/admiro/audit')->assertOk();
        $this->get('/admiro/onboarding/audit')->assertSee('<span class="ss-badge ss-badge--outline ss-badge--success">' . text('admiro::dashboard.onboarding_states.done') . '</span>', false);
    }

    public function test_steps_outside_the_permissions_are_not_found(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/onboarding/languages')->assertNotFound();
        $this->post('/admiro/onboarding/languages/confirm')->assertForbidden();
        $this->assertNotNull(Organization::query()->find(Organization::OPERATOR_ID));
    }
}
