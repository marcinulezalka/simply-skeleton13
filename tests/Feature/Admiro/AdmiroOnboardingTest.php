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
            ->assertSee(text('admiro::dashboard.onboarding_next', ['number' => '2']))
            ->assertSee('href="' . url('/admiro/onboarding/languages') . '"', false);
    }

    public function test_languages_step_shows_enabled_languages_above_a_search_field_without_the_full_list(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSee('<title>' . text('admiro::dashboard.onboarding_title'), false)
            ->assertSeeInOrder([text('admiro::dashboard.onboarding_languages_enabled'), text('admiro::dashboard.onboarding_languages_search_title'), 'id="ss-languages-search"'], false)
            ->assertSee('data-ss-search-suggest="' . route('admiro.search.suggest') . '"', false)
            ->assertSee('<input type="hidden" name="in[]" value="languages">', false)
            ->assertSee(text('admiro::dashboard.onboarding_languages_hint'))
            ->assertDontSee('id="language-de"', false)
            ->assertSee('<p class="ss-context__meta">' . text('admiro::dashboard.onboarding_step', ['number' => '2']) . ' · ' . text('admiro::dashboard.onboarding_states.not_started') . '</p>', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link" href="' . url('/admiro/onboarding/languages') . '" aria-current="step">', false)
            ->assertSee('<a class="ss-onboarding-wizard__step-link ss-onboarding-wizard__step-link--done" href="' . url('/admiro/onboarding/installation') . '">', false)
            ->assertDontSee('<span class="ss-onboarding-wizard__step-lead">' . e(text('admiro::dashboard.onboarding_installation_lead')), false)
            ->assertSee('<span class="ss-onboarding-wizard__step-lead">' . e(text('admiro::dashboard.onboarding_settings_lead')), false)
            ->assertSee(route('admiro.onboarding.confirm', ['step' => 'languages']), false);
    }

    public function test_search_term_shows_matching_languages_in_panels_with_switches_and_flags(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages?q=niem')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_languages_results', ['term' => 'niem']))
            ->assertSee('<li class="ss-card ss-onboarding-languages__result" id="language-de">', false)
            ->assertSee('id="flag-icons-at"', false)
            ->assertSee('role="switch" id="language-de"', false)
            ->assertSee('data-ss-autosubmit-fallback', false)
            ->assertDontSee('id="language-en"', false);

        $this->get('/admiro/search/suggest?q=niem&in[]=languages')
            ->assertOk()
            ->assertSee(url('/admiro/onboarding/languages') . '?q=niem#language-de', false);
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
        $this->get('/admiro/onboarding/languages')->assertSee('<p class="ss-context__meta">' . text('admiro::dashboard.onboarding_step', ['number' => '2']) . ' · ' . text('admiro::dashboard.onboarding_states.done') . '</p>', false);
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
