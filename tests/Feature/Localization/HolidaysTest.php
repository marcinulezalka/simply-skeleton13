<?php

declare(strict_types=1);

namespace Tests\Feature\Localization;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Simplysmart\Mainboard\Enums\HolidaySource;
use Simplysmart\Mainboard\Holidays\HolidaySync;
use Simplysmart\Mainboard\Holidays\HolidaysRejected;
use Simplysmart\Mainboard\Holidays\ManualHolidays;
use Simplysmart\Mainboard\Models\Dictionaries\Country;
use Simplysmart\Mainboard\Models\Dictionaries\Holiday;
use Simplysmart\Mainboard\Queries\Dictionaries\HolidaysIn;
use Tests\TestCase;

/**
 * Święta i dni wolne krajów z Nager.Date: tylko ogólnokrajowe święta „Public”, dwa święta jednego dnia we wspólnym
 * wpisie, wpis ręczny nienadpisywany, kraj nieobsługiwany bez błędu, dni wolne w okresie, dni robocze i komenda.
 */
class HolidaysTest extends TestCase
{
    use RefreshDatabase;

    private Country $poland;

    protected function setUp(): void
    {
        parent::setUp();

        $this->poland = (new Country())->forceFill(['name' => 'Polska', 'alpha_code_2' => 'PL', 'is_active' => true, 'is_default' => true]);
        $this->poland->save();
    }

    public function test_sync_keeps_nationwide_public_holidays_and_manual_entries(): void
    {
        $this->app->make(ManualHolidays::class)->add($this->poland, CarbonImmutable::parse('2026-11-10'), 'Dzień wolny firmy');
        Http::fake(['date.nager.at/*' => Http::response([
            $this->holiday('2026-11-01', 'Wszystkich Świętych', 'All Saints Day'),
            $this->holiday('2026-11-11', 'Narodowe Święto Niepodległości', 'Independence Day'),
            $this->holiday('2026-11-11', 'Dzień Pamięci', 'Remembrance', types: ['Observance']),
            $this->holiday('2026-11-10', 'Święto regionalne', 'Regional', global: false),
            $this->holiday('2026-12-25', 'Boże Narodzenie', 'Christmas Day'),
            $this->holiday('2026-12-25', 'Pierwszy dzień świąt', 'First Day', ),
        ])]);

        $saved = $this->app->make(HolidaySync::class)->sync($this->poland, 2026);

        $this->assertSame(3, $saved);
        $holidays = Holiday::query()->orderBy('holiday_date')->get();
        $this->assertSame(['2026-11-01', '2026-11-10', '2026-11-11', '2026-12-25'], $holidays->map(fn(Holiday $holiday): string => $holiday->holiday_date->toDateString())->all());
        $this->assertSame(HolidaySource::Manual, $holidays[1]->source);
        $this->assertSame('Boże Narodzenie / Pierwszy dzień świąt', $holidays[3]->name);
        $this->assertSame('Independence Day', $holidays[2]->translatedName('en'));
        $this->assertSame(0, $this->app->make(HolidaySync::class)->sync($this->poland, 2026));
    }

    public function test_unsupported_country_has_no_holidays(): void
    {
        Http::fake(['date.nager.at/*' => Http::response('', 404)]);

        $this->assertSame(0, $this->app->make(HolidaySync::class)->sync($this->poland, 2026));
    }

    public function test_days_off_and_business_days(): void
    {
        $this->app->make(ManualHolidays::class)->add($this->poland, CarbonImmutable::parse('2026-11-11'), 'Święto Niepodległości');
        $holidays = $this->app->make(HolidaysIn::class);

        $this->assertSame(['2026-11-11'], array_keys($holidays->applicationDaysOff(CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30'))));
        $this->assertFalse($holidays->isBusinessDay($this->poland, CarbonImmutable::parse('2026-11-11')));
        $this->assertFalse($holidays->isBusinessDay($this->poland, CarbonImmutable::parse('2026-11-14')));
        $this->assertTrue($holidays->isBusinessDay($this->poland, CarbonImmutable::parse('2026-11-12')));
        $this->assertSame([], $holidays->daysOff(null, CarbonImmutable::parse('2026-11-01'), CarbonImmutable::parse('2026-11-30')));
    }

    public function test_manual_days_off_are_validated_and_only_they_can_be_removed(): void
    {
        $manual = $this->app->make(ManualHolidays::class);
        $entry = $manual->add($this->poland, CarbonImmutable::parse('2026-11-10'), 'Dzień wolny');

        $this->assertRejected(fn() => $manual->add($this->poland, CarbonImmutable::parse('2026-11-10'), 'Drugi'));
        $this->assertRejected(fn() => $manual->add($this->poland, CarbonImmutable::parse('2026-11-12'), ' '));

        $nager = (new Holiday())->forceFill(['name' => 'Święto', 'country_id' => $this->poland->id, 'holiday_date' => '2026-11-11', 'source' => HolidaySource::Nager]);
        $nager->save();
        $this->assertRejected(fn() => $manual->remove($nager));

        $manual->remove($entry);
        $this->assertSame(1, Holiday::query()->count());
    }

    public function test_command_syncs_active_countries_for_the_given_years(): void
    {
        Http::fake(['date.nager.at/*' => Http::response([$this->holiday('2027-01-01', 'Nowy Rok', "New Year's Day")])]);

        $this->artisan('mainboard:holidays:sync', ['--year' => ['2027']])->assertSuccessful();

        Http::assertSent(static fn($request): bool => str_ends_with($request->url(), '/PublicHolidays/2027/PL'));
        $this->assertSame('Nowy Rok', Holiday::query()->sole()->name);
    }

    private function assertRejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected the change to be rejected.');
        } catch (HolidaysRejected) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @param list<string> $types
     *
     * @return array<string, mixed>
     */
    private function holiday(string $date, string $local, string $english, bool $global = true, array $types = ['Public']): array
    {
        return ['date' => $date, 'localName' => $local, 'name' => $english, 'countryCode' => 'PL', 'global' => $global, 'counties' => null, 'types' => $types];
    }
}
