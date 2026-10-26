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

    public function test_entry_shows_the_steps_overview_with_links_to_the_wizard(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding')
            ->assertOk()
            ->assertSee('<section class="ss-card ss-onboarding"', false)
            ->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '0', 'total' => '6']))
            ->assertSee('href="' . url('/admiro/onboarding/languages') . '"', false);
    }

    public function test_languages_step_lists_enabled_languages_above_all_languages_with_switches_and_flags(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages')
            ->assertOk()
            ->assertSee('<title>' . text('admiro::dashboard.onboarding_title'), false)
            ->assertSeeInOrder([text('admiro::dashboard.onboarding_languages_enabled'), text('admiro::dashboard.onboarding_languages_all'), 'id="language-de"'], false)
            ->assertSee('id="flag-icons-at"', false)
            ->assertSee('role="switch" id="language-de"', false)
            ->assertSee('role="switch" id="language-pl" disabled', false)
            ->assertSee('aria-current="step"', false)
            ->assertSee(route('admiro.onboarding.confirm', ['step' => 'languages']), false);
    }

    public function test_search_term_filters_the_languages(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro')->get('/admiro/onboarding/languages?q=niem')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_languages_results', ['term' => 'niem']))
            ->assertSee('id="language-de"', false)
            ->assertDontSee('id="language-en"', false);
    }

    public function test_confirming_languages_closes_the_step_and_moves_on(): void
    {
        $this->signedIn($this->admin('hero@example.com', 'hero'), 'admiro');

        $this->post('/admiro/onboarding/languages/confirm')->assertRedirect('/admiro/onboarding/settings');
        $this->get('/admiro/onboarding')->assertOk()->assertSee(text('admiro::dashboard.onboarding_progress', ['done' => '1', 'total' => '6']));
        $this->get('/admiro/onboarding/settings')
            ->assertOk()
            ->assertSee(text('admiro::dashboard.onboarding_open', ['name' => text('admiro::dashboard.onboarding_settings_title')]))
            ->assertSee(route('admiro.onboarding.show', ['step' => 'languages']), false);
        $this->post('/admiro/onboarding/settings/confirm')->assertNotFound();
    }

    public function test_steps_outside_the_permissions_are_not_found(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/onboarding/languages')->assertNotFound();
        $this->post('/admiro/onboarding/languages/confirm')->assertForbidden();
        $this->assertNotNull(Organization::query()->find(Organization::OPERATOR_ID));
    }
}
