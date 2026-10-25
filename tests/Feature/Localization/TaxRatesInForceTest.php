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

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Simplysmart\Admiro\Enums\TaxRateType;
use Simplysmart\Admiro\Models\Dictionaries\Country;
use Simplysmart\Admiro\Models\Dictionaries\TaxRate;
use Simplysmart\Admiro\Queries\Dictionaries\TaxRatesInForce;
use Tests\TestCase;

/**
 * Stawki VAT w danym dniu: granice valid_from i valid_to włącznie, kolejność (podstawowa, potem malejąco)
 * i stawka aplikacji z kraju domyślnego albo z configu.
 */
class TaxRatesInForceTest extends TestCase
{
    use RefreshDatabase;

    private Country $poland;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poland = (new Country())->forceFill(['name' => 'Polska', 'alpha_code_2' => 'PL', 'is_default' => true]);
        $this->poland->save();
    }

    public function test_rates_in_force_on_a_day_respect_both_bounds(): void
    {
        $this->rate(TaxRateType::Standard, 22, '2010-01-01', '2010-12-31');
        $this->rate(TaxRateType::Standard, 23, '2011-01-01');
        $this->rate(TaxRateType::Reduced, 8, '2011-01-01');
        $this->rate(TaxRateType::Reduced, 5, '2011-01-01');
        $rates = $this->app->make(TaxRatesInForce::class);

        $this->assertSame(['22.00'], $this->rates($rates, '2010-12-31'));
        $this->assertSame(['23.00', '8.00', '5.00'], $this->rates($rates, '2011-01-01'));
        $this->assertSame('22.00', $rates->standard($this->poland, CarbonImmutable::parse('2010-06-01'))?->rate);
    }

    public function test_application_rate_comes_from_the_default_country_or_config(): void
    {
        $rates = $this->app->make(TaxRatesInForce::class);
        config(['admiro.localization.vat_percent' => '21']);

        $this->assertSame('21', $rates->applicationPercent());

        $this->rate(TaxRateType::Standard, 23, '2011-01-01');

        $this->assertSame('23.00', $rates->applicationPercent());
    }

    private function rate(TaxRateType $type, int $rate, string $from, ?string $to = null): void
    {
        (new TaxRate())->forceFill([
            'name' => $rate . '%',
            'country_id' => $this->poland->id,
            'type' => $type,
            'rate' => $rate,
            'valid_from' => $from,
            'valid_to' => $to,
        ])->save();
    }

    /**
     * @return list<string|null>
     */
    private function rates(TaxRatesInForce $rates, string $day): array
    {
        return array_values($rates->forCountry($this->poland, CarbonImmutable::parse($day))->map(static fn(TaxRate $rate): ?string => $rate->rate)->all());
    }
}
