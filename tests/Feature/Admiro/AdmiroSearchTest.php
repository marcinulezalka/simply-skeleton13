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

use Simplysmart\Admiro\Models\Dictionaries\Country;
use Simplysmart\Admiro\Models\Dictionaries\Language;
use Simplysmart\Admiro\Models\Organization;

/**
 * Globalna wyszukiwarka strefy administratorów: pole z zakresami i podpowiedziami w layoucie, języki także po krajach
 * („polska” → polski) z flagami i odnośnikiem do kreatora języków, zakresy z `in[]` i z uprawnień, strony panelu,
 * strona wszystkich wyników.
 */
class AdmiroSearchTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
        $poland = Country::query()->where('alpha_code_2', 'PL')->sole();
        $poland->forceFill(['name' => 'Polska', 'json_i18n' => ['pl' => 'Polska', 'en' => 'Poland']])->save();
        $polish = Language::query()->where('code', 'pl')->sole();
        $polish->forceFill(['name' => 'polski', 'native_name' => 'polski', 'json_i18n' => ['pl' => 'polski', 'en' => 'Polish'], 'json_attributes' => [Language::FLAG_ICONS => ['pl']]])->save();
        $polish->countries()->attach($poland->id, ['is_primary' => true]);
    }

    public function test_layout_has_the_search_with_scopes_and_suggestions(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro')
            ->assertOk()
            ->assertSee('data-ss-search-suggest="' . route('admiro.search.suggest') . '"', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('<label class="ss-switch ss-search__scope" for="ss-search-scope-languages">', false)
            ->assertSee('value="languages"', false)
            ->assertSee('value="pages"', false)
            ->assertSee("const MIN_LENGTH = 2;", false);
    }

    public function test_country_name_finds_its_language_with_flags_and_a_link_to_the_languages_wizard(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/search/suggest?q=polska')
            ->assertOk()
            ->assertSeeInOrder([text('admiro::search.groups.languages'), 'polski'])
            ->assertSee('id="flag-icons-pl"', false)
            ->assertSee(e(route('admiro.onboarding.show', ['step' => 'languages', 'q' => 'polska'])) . '#language-pl', false)
            ->assertSee(text('admiro::search.groups.countries'));
    }

    public function test_only_ticked_scopes_are_searched(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/search/suggest?q=polska&in[]=pages')->assertOk()->assertDontSee(text('admiro::search.groups.languages'));
        $this->get('/admiro/search/suggest?q=s%C5%82own&in[]=pages')->assertOk()->assertSee(text('admiro::search.groups.pages'))->assertSee(text('admiro::admin.dictionaries_title'));
        $this->get('/admiro/search/suggest?q=slown&in[]=')->assertOk()->assertSee(text('layout.search_empty'));
    }

    public function test_scopes_follow_the_permissions(): void
    {
        (new Organization())->forceFill(['name' => 'Polska Firma'])->save();
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/search?q=polska')
            ->assertOk()
            ->assertSee(text('admiro::search.results_for', ['term' => 'polska']))
            ->assertSee('Polska Firma')
            ->assertSee(e(route('admiro.dictionaries.index', ['dictionary' => 'languages'])) . '#language-pl', false);
    }

    public function test_empty_and_unknown_searches_explain_what_to_do(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/search')->assertOk()->assertSee(text('admiro::search.prompt'));
        $this->get('/admiro/search?q=xyzzy')->assertOk()->assertSee(text('admiro::search.empty', ['term' => 'xyzzy']));
    }
}
