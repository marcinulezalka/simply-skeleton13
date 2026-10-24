<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Simplysmart\Mainboard\Enums\ExchangeRateSource;
use Simplysmart\Mainboard\ExchangeRates\ExchangeRateSync;
use Simplysmart\Mainboard\ExchangeRates\ExchangeRatesRejected;
use Simplysmart\Mainboard\ExchangeRates\ManualExchangeRates;
use Simplysmart\Mainboard\Models\Dictionaries\Currency;
use Simplysmart\Mainboard\Models\Dictionaries\ExchangeRate;
use Simplysmart\Mainboard\Queries\Dictionaries\ExchangeRatesOn;
use Tests\TestCase;

/**
 * Kursy walut z tabeli A NBP: zapis tylko aktywnych walut, okres dzielony po 93 dni, 404 w dni bez tabeli, kurs
 * ręczny nienadpisywany przez synchronizację, kurs z ostatniego dnia publikacji przed datą i przeliczenie kwot.
 */
class ExchangeRatesTest extends TestCase
{
    use RefreshDatabase;

    private Currency $pln;

    private Currency $eur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pln = $this->currency('PLN', true);
        $this->eur = $this->currency('EUR', true);
        $this->currency('USD', false);
    }

    public function test_sync_saves_active_currencies_and_keeps_manual_rates(): void
    {
        $this->app->make(ManualExchangeRates::class)->addManual($this->eur, CarbonImmutable::parse('2026-10-22'), '4.3');
        Http::fake(['api.nbp.pl/*' => Http::response([
            $this->table('2026-10-22', '205/A/NBP/2026', ['EUR' => 4.2512, 'USD' => 3.9]),
            $this->table('2026-10-23', '206/A/NBP/2026', ['EUR' => 4.2601, 'USD' => 3.91]),
        ])]);

        $saved = $this->app->make(ExchangeRateSync::class)->sync(CarbonImmutable::parse('2026-10-22'), CarbonImmutable::parse('2026-10-23'));

        $this->assertSame(1, $saved);
        $rates = ExchangeRate::query()->orderBy('rate_date')->get();
        $this->assertSame([['2026-10-22', '4.300000', ExchangeRateSource::Manual], ['2026-10-23', '4.260100', ExchangeRateSource::Nbp]], $rates->map(fn(ExchangeRate $rate): array => [$rate->rate_date->toDateString(), $rate->rate, $rate->source])->all());
        $this->assertSame('206/A/NBP/2026', $rates->last()?->table_number);

        $this->assertSame(0, $this->app->make(ExchangeRateSync::class)->sync(CarbonImmutable::parse('2026-10-22'), CarbonImmutable::parse('2026-10-23')));
    }

    public function test_long_periods_are_split_and_days_without_a_table_are_not_errors(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::response('404 NotFound - Not Found - Brak danych', 404)]);

        $this->assertSame(0, $this->app->make(ExchangeRateSync::class)->sync(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-06-30')));

        Http::assertSentCount(2);
        Http::assertSent(static fn(Request $request): bool => str_contains($request->url(), '/tables/A/2026-01-01/2026-04-03/'));
        Http::assertSent(static fn(Request $request): bool => str_contains($request->url(), '/tables/A/2026-04-04/2026-06-30/'));
    }

    public function test_command_reports_an_unavailable_provider(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::response('error', 500)]);

        $this->artisan('mainboard:exchange-rates:sync', ['--from' => '2026-10-20', '--to' => '2026-10-23'])->assertFailed();
        $this->artisan('mainboard:exchange-rates:sync', ['--from' => '2026-10-24', '--to' => '2026-10-23'])->assertExitCode(2);
    }

    public function test_rate_before_a_date_skips_days_without_publication_and_converts_amounts(): void
    {
        $manual = $this->app->make(ManualExchangeRates::class);
        $manual->addManual($this->eur, CarbonImmutable::parse('2026-10-23'), '4.2601');
        $rates = $this->app->make(ExchangeRatesOn::class);

        $this->assertSame('4.260100', $rates->lastBefore($this->eur, CarbonImmutable::parse('2026-10-26'))?->rate);
        $this->assertNull($rates->lastBefore($this->eur, CarbonImmutable::parse('2026-10-23')));
        $this->assertSame('426.01', $rates->convert('100', $this->eur, $this->pln, CarbonImmutable::parse('2026-10-26')));
        $this->assertSame('23.47', $rates->convert('100', $this->pln, $this->eur, CarbonImmutable::parse('2026-10-26')));
        $this->assertNull($rates->convert('100', $this->eur, $this->pln, CarbonImmutable::parse('2026-10-01')));
    }

    public function test_manual_rates_are_validated_and_only_they_can_be_removed(): void
    {
        $manual = $this->app->make(ManualExchangeRates::class);
        $rate = $manual->addManual($this->eur, CarbonImmutable::parse('2026-10-23'), '4.2601');

        $this->assertRejected(fn() => $manual->addManual($this->eur, CarbonImmutable::parse('2026-10-23'), '4.1'));
        $this->assertRejected(fn() => $manual->addManual($this->eur, CarbonImmutable::parse('2026-10-24'), '0'));

        $nbp = (new ExchangeRate())->forceFill(['name' => 'EUR 2026-10-20', 'currency_id' => $this->eur->id, 'rate_date' => '2026-10-20', 'rate' => '4.25', 'source' => ExchangeRateSource::Nbp]);
        $nbp->save();
        $this->assertRejected(fn() => $manual->removeManual($nbp));

        $manual->removeManual($rate);
        $this->assertSame(1, ExchangeRate::query()->count());
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the change to be rejected.');
        } catch (ExchangeRatesRejected) {
            $this->addToAssertionCount(1);
        }
    }

    private function currency(string $code, bool $active): Currency
    {
        $currency = (new Currency())->forceFill(['name' => $code, 'alpha_code_3' => $code, 'is_active' => $active, 'is_default' => $code === 'PLN']);
        $currency->save();

        return $currency;
    }

    /**
     * @param array<string, float> $rates
     *
     * @return array<string, mixed>
     */
    private function table(string $date, string $number, array $rates): array
    {
        return [
            'table' => 'A',
            'no' => $number,
            'effectiveDate' => $date,
            'rates' => array_map(static fn(string $code, float $mid): array => ['currency' => $code, 'code' => $code, 'mid' => $mid], array_keys($rates), $rates),
        ];
    }
}
