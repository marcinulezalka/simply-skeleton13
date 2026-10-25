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

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Simplysmart\Admiro\Database\History;
use Simplysmart\Admiro\Database\IdSlots;
use Simplysmart\Admiro\Database\Seeders\LocalizationData;
use Simplysmart\Admiro\Enums\Continent;
use Simplysmart\Admiro\Enums\TaxRateType;
use Simplysmart\Admiro\Models\Dictionaries\Country;
use Simplysmart\Admiro\Models\Dictionaries\Currency;
use Simplysmart\Admiro\Models\Dictionaries\Language;
use Simplysmart\Admiro\Models\Dictionaries\TaxRate;
use Simplysmart\Admiro\Queries\Dictionaries\DefaultEntries;
use Tests\TestCase;

/**
 * Komenda admiro:localization:seed: słowniki z ICU, wartości domyślne z configu, powiązania krajów,
 * stawki VAT, ponowne uruchomienie bez zmian i z zachowaniem wyborów administratora.
 */
class LocalizationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admiro.locale.enabled' => ['pl', 'en']]);
        $this->seedLocalization();
    }

    public function test_defaults_are_poland_polish_and_zloty_linked_together(): void
    {
        $defaults = $this->app->make(DefaultEntries::class);
        $country = $defaults->country();

        $this->assertSame('PL', $country?->alpha_code_2);
        $this->assertSame('pl', $defaults->language()?->code);
        $this->assertSame('PLN', $defaults->currency()?->alpha_code_3);
        $this->assertSame(1, Country::query()->where('is_default', true)->count());
        $this->assertSame('PLN', $country->currency?->alpha_code_3);
        $this->assertSame('pl', $country->languages->first()?->code);
        $this->assertTrue($country->is_eu);
        $this->assertSame('+48', $country->dial_code);
        $this->assertSame('Poland', $country->translatedName('en'));
    }

    public function test_switched_on_entries_and_interface_languages(): void
    {
        $this->assertSame(['CHF', 'EUR', 'GBP', 'PLN', 'USD'], Currency::query()->active()->orderBy('alpha_code_3')->pluck('alpha_code_3')->all());
        $this->assertSame(['en', 'pl'], Language::query()->where('is_interface', true)->orderBy('code')->pluck('code')->all());
        $this->assertSame('angielski', Language::query()->where('code', 'en')->value('name'));
        $this->assertSame('English', Language::query()->where('code', 'en')->value('native_name'));
        $this->assertGreaterThan(200, Country::query()->count());
    }

    public function test_countries_and_languages_get_flag_icons(): void
    {
        $this->assertSame('pl', Country::query()->where('alpha_code_2', 'PL')->value('flag_icon'));
        $this->assertSame(['cs' => 'cz', 'el' => 'gr', 'en' => 'gb', 'pl' => 'pl', 'sv' => 'se'], Language::query()->whereIn('code', ['cs', 'el', 'en', 'pl', 'sv'])->orderBy('code')->pluck('flag_icon', 'code')->all());
        $this->assertSame(0, Language::query()->whereNull('flag_icon')->count());

        $icons = Country::query()->pluck('flag_icon')->merge(Language::query()->pluck('flag_icon'))->unique();
        $missing = $icons->reject(static fn(?string $icon): bool => str_contains(Blade::render('<x-admiro::flag :icon="$icon" />', ['icon' => $icon]), 'id="flag-icons-' . $icon . '"'));
        $this->assertSame([], $missing->values()->all());
    }

    public function test_countries_have_iso_codes_and_territory(): void
    {
        $poland = Country::query()->where('alpha_code_2', 'PL')->sole();
        $zloty = Currency::query()->where('alpha_code_3', 'PLN')->sole();

        $this->assertSame(['POL', '616', Continent::Europe, '151'], [$poland->alpha_code_3, $poland->numeric_code, $poland->continent, $poland->region_code]);
        $this->assertGreaterThan(30_000_000, $poland->population);
        $this->assertSame('004', Country::query()->where('alpha_code_2', 'AF')->value('numeric_code'));
        $this->assertSame(Continent::SouthAmerica, Country::query()->where('alpha_code_2', 'BR')->sole()->continent);
        $this->assertSame('985', $zloty->numeric_code);
        $this->assertSame(0, Country::query()->whereNull('continent')->count());
    }

    public function test_country_lists_are_grouped_by_continent_with_the_application_country_first(): void
    {
        $continents = Country::query()->orderBy('sort_order')->orderBy('name')->get()->map(static fn(Country $country): ?Continent => $country->continent)->unique()->values()->all();

        $this->assertSame(array_values(array_filter(LocalizationData::CONTINENT_ORDER, static fn(Continent $continent): bool => in_array($continent, $continents, true))), $continents);
        $this->assertSame([Continent::Europe, Continent::Asia], array_slice($continents, 0, 2));
        $this->assertSame('PL', Country::query()->orderBy('sort_order')->value('alpha_code_2'));
    }

    public function test_seeded_entries_use_seed_id_slots(): void
    {
        foreach ([Country::class, Currency::class, Language::class, TaxRate::class] as $model) {
            $this->assertLessThanOrEqual(IdSlots::SEED_MAX, $model::query()->max('id'), $model);
        }

        $country = (new Country())->forceFill(['name' => 'Testland', 'alpha_code_2' => 'QZ']);
        $country->save();

        $this->assertSame(IdSlots::FIRST_USER_ID, $country->id);
    }

    public function test_polish_vat_rates_are_complete(): void
    {
        $poland = Country::query()->where('alpha_code_2', 'PL')->sole();
        $rates = TaxRate::query()->where('country_id', $poland->id)->orderBy('id')->get();

        $this->assertSame(['23%', '8%', '5%', '0%', 'zw'], $rates->pluck('name')->all());
        $this->assertSame([TaxRateType::Standard, TaxRateType::Reduced, TaxRateType::Reduced, TaxRateType::Zero, TaxRateType::Exempt], $rates->pluck('type')->all());
        $this->assertNull($rates->last()?->rate);
        $this->assertSame('exempt', $rates->last()?->translatedName('en'));
        $this->assertSame('5,5%', TaxRate::query()->where('rate', 5.5)->value('name'));
    }

    public function test_running_again_changes_nothing_and_keeps_the_administrators_choices(): void
    {
        Currency::query()->where('alpha_code_3', 'EUR')->sole()->forceFill(['is_active' => false])->save();
        Language::query()->where('code', 'en')->sole()->forceFill(['flag_icon' => 'us'])->save();
        $polishReduced = TaxRate::query()->where('country_id', Country::query()->where('alpha_code_2', 'PL')->value('id'))->where('name', '8%');
        $polishReduced->sole()->delete();
        $counts = $this->counts();
        $history = DB::table(History::tableFor('locale_countries'))->count();

        $this->seedLocalization();

        $this->assertSame($counts, $this->counts());
        $this->assertSame($history, DB::table(History::tableFor('locale_countries'))->count());
        $this->assertFalse(Currency::query()->where('alpha_code_3', 'EUR')->sole()->is_active);
        $this->assertSame('us', Language::query()->where('code', 'en')->value('flag_icon'));
        $this->assertSame(0, $polishReduced->count());
    }

    private function seedLocalization(): void
    {
        $this->artisan('admiro:localization:seed');
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        return [Currency::query()->count(), Language::query()->count(), Country::query()->count(), TaxRate::withTrashed()->count()];
    }
}
