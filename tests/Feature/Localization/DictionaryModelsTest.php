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

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Mainboard\Enums\TaxRateType;
use Simplysmart\Mainboard\Models\Dictionaries\Country;
use Simplysmart\Mainboard\Models\Dictionaries\CountryLanguage;
use Simplysmart\Mainboard\Models\Dictionaries\Currency;
use Simplysmart\Mainboard\Models\Dictionaries\Language;
use Simplysmart\Mainboard\Models\Dictionaries\TaxRate;
use Tests\TestCase;

/**
 * Modele słowników lokalizacji: każda kolumna ma cast i etykietę text_data w pl i en, checksum nowego
 * rekordu jest taka sama jak po odczycie z bazy, relacje działają w obie strony.
 */
class DictionaryModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return [
            'currency' => [Currency::class],
            'language' => [Language::class],
            'country' => [Country::class],
            'country language' => [CountryLanguage::class],
            'tax rate' => [TaxRate::class],
        ];
    }

    /**
     * @param class-string<Model> $model
     */
    #[DataProvider('models')]
    public function test_every_column_has_a_cast(string $model): void
    {
        $instance = new $model();

        $this->assertSame([], array_values(array_diff(Schema::getColumnListing($instance->getTable()), array_keys($instance->getCasts()))));
    }

    /**
     * @param class-string<Model> $model
     */
    #[DataProvider('models')]
    public function test_every_column_has_a_label_in_each_language(string $model): void
    {
        foreach (['pl', 'en'] as $locale) {
            $this->app->setLocale($locale);

            foreach (Schema::getColumnListing((new $model())->getTable()) as $column) {
                $this->assertStringNotContainsString('data.', text_data($model, $column), "{$locale}: {$model}.{$column}");
            }
        }
    }

    public function test_checksum_of_a_new_record_matches_the_stored_one(): void
    {
        $records = [
            $this->currency(),
            (new Language())->forceFill(['name' => 'polski', 'code' => 'pl']),
            (new Country())->forceFill(['name' => 'Polska', 'code' => 'PL']),
            (new TaxRate())->forceFill(['name' => '23%', 'type' => TaxRateType::Standard, 'rate' => 23, 'valid_from' => '2025-01-01']),
        ];

        foreach ($records as $record) {
            $record->save();

            $this->assertSame($record->checksum, $record->refresh()->computeChecksum(), $record::class);
        }
    }

    public function test_relations_link_countries_with_currencies_languages_and_tax_rates(): void
    {
        $currency = $this->currency();
        $currency->save();
        $language = (new Language())->forceFill(['name' => 'polski', 'code' => 'pl']);
        $language->save();
        $country = (new Country())->forceFill(['name' => 'Polska', 'code' => 'PL', 'currency_id' => $currency->id]);
        $country->save();
        $country->languages()->sync([$language->id => [CountryLanguage::IS_PRIMARY => true]]);
        $rate = (new TaxRate())->forceFill(['name' => '23%', 'country_id' => $country->id, 'type' => TaxRateType::Standard, 'rate' => 23, 'valid_from' => '2025-01-01']);
        $rate->save();

        $this->assertTrue($country->currency?->is($currency));
        $this->assertSame(['PL'], $currency->countries->pluck('code')->all());
        $this->assertSame(['pl'], $country->languages()->wherePivot(CountryLanguage::IS_PRIMARY, true)->pluck('code')->all());
        $this->assertSame(['PL'], $language->countries->pluck('code')->all());
        $this->assertSame(['23.00'], $country->taxRates->pluck('rate')->all());
        $this->assertTrue($rate->country?->is($country));
    }

    public function test_tax_rate_type_labels_come_from_translations(): void
    {
        $this->assertSame('Zwolniona (zw)', TaxRateType::Exempt->label());
        $this->assertSame(TaxRateType::SuperReduced, TaxRateType::forReducedPercentage(4.8));
        $this->assertSame(TaxRateType::Zero, TaxRateType::forReducedPercentage(0));
        $this->assertFalse(TaxRateType::Exempt->hasPercentage());
    }

    private function currency(): Currency
    {
        return (new Currency())->forceFill(['name' => 'złoty polski', 'code' => 'PLN']);
    }
}
