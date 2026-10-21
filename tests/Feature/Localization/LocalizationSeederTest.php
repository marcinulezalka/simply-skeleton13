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
use Illuminate\Support\Facades\DB;
use Simplysmart\Mainboard\Database\History;
use Simplysmart\Mainboard\Enums\TaxRateType;
use Simplysmart\Mainboard\Models\Dictionaries\Country;
use Simplysmart\Mainboard\Models\Dictionaries\Currency;
use Simplysmart\Mainboard\Models\Dictionaries\Language;
use Simplysmart\Mainboard\Models\Dictionaries\TaxRate;
use Simplysmart\Mainboard\Queries\Dictionaries\DefaultEntries;
use Tests\TestCase;

/**
 * Komenda mainboard:localization:seed: słowniki z ICU, wartości domyślne z configu, powiązania krajów,
 * stawki VAT, ponowne uruchomienie bez zmian i z zachowaniem wyborów administratora.
 */
class LocalizationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['mainboard.locale.enabled' => ['pl', 'en']]);
        $this->seedLocalization();
    }

    public function test_defaults_are_poland_polish_and_zloty_linked_together(): void
    {
        $defaults = $this->app->make(DefaultEntries::class);
        $country = $defaults->country();

        $this->assertSame('PL', $country?->code);
        $this->assertSame('pl', $defaults->language()?->code);
        $this->assertSame('PLN', $defaults->currency()?->code);
        $this->assertSame(1, Country::query()->where('is_default', true)->count());
        $this->assertSame('PLN', $country->currency?->code);
        $this->assertSame('pl', $country->languages->first()?->code);
        $this->assertTrue($country->is_eu);
        $this->assertSame('+48', $country->dial_code);
        $this->assertSame('Poland', $country->translatedName('en'));
    }

    public function test_switched_on_entries_and_interface_languages(): void
    {
        $this->assertSame(['CHF', 'EUR', 'GBP', 'PLN', 'USD'], Currency::query()->active()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['en', 'pl'], Language::query()->where('is_interface', true)->orderBy('code')->pluck('code')->all());
        $this->assertSame('angielski', Language::query()->where('code', 'en')->value('name'));
        $this->assertSame('English', Language::query()->where('code', 'en')->value('native_name'));
        $this->assertGreaterThan(200, Country::query()->count());
    }

    public function test_polish_vat_rates_are_complete(): void
    {
        $poland = Country::query()->where('code', 'PL')->sole();
        $rates = TaxRate::query()->where('country_id', $poland->id)->orderBy('id')->get();

        $this->assertSame(['23%', '8%', '5%', '0%', 'zw'], $rates->pluck('name')->all());
        $this->assertSame([TaxRateType::Standard, TaxRateType::Reduced, TaxRateType::Reduced, TaxRateType::Zero, TaxRateType::Exempt], $rates->pluck('type')->all());
        $this->assertNull($rates->last()?->rate);
        $this->assertSame('exempt', $rates->last()?->translatedName('en'));
        $this->assertSame('5,5%', TaxRate::query()->where('rate', 5.5)->value('name'));
    }

    public function test_running_again_changes_nothing_and_keeps_the_administrators_choices(): void
    {
        Currency::query()->where('code', 'EUR')->sole()->forceFill(['is_active' => false])->save();
        $polishReduced = TaxRate::query()->where('country_id', Country::query()->where('code', 'PL')->value('id'))->where('name', '8%');
        $polishReduced->sole()->delete();
        $counts = $this->counts();
        $history = DB::table(History::tableFor('dict_countries'))->count();

        $this->seedLocalization();

        $this->assertSame($counts, $this->counts());
        $this->assertSame($history, DB::table(History::tableFor('dict_countries'))->count());
        $this->assertFalse(Currency::query()->where('code', 'EUR')->sole()->is_active);
        $this->assertSame(0, $polishReduced->count());
    }

    private function seedLocalization(): void
    {
        $this->artisan('mainboard:localization:seed');
    }

    /**
     * @return list<int>
     */
    private function counts(): array
    {
        return [Currency::query()->count(), Language::query()->count(), Country::query()->count(), TaxRate::withTrashed()->count()];
    }
}
