<?php

declare(strict_types=1);

namespace Tests\Feature\Admiro;

use Illuminate\Support\Facades\Http;
use Simplysmart\Mainboard\Enums\ExchangeRateSource;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Models\Dictionaries\ExchangeRate;

/**
 * Kursy walut w panelu: lista z filtrem dla `settings.view`, pobranie z NBP, kurs ręczny i usunięcie tylko kursu
 * ręcznego dla `settings.manage`, każda zmiana w dzienniku zdarzeń.
 */
class AdmiroExchangeRatesTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_owner_fetches_rates_from_nbp_and_manages_manual_rates(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::response([['table' => 'A', 'no' => '206/A/NBP/2026', 'effectiveDate' => '2026-10-23', 'rates' => [['currency' => 'euro', 'code' => 'EUR', 'mid' => 4.2601]]]])]);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/exchange-rates/sync', ['sync_from' => '2026-10-23', 'sync_to' => '2026-10-23'])->assertRedirect('/admiro/exchange-rates');
        $this->post('/admiro/exchange-rates', ['rate_currency' => 'EUR', 'rate_date' => '2026-10-24', 'rate' => '4.3'])->assertRedirect();
        $this->post('/admiro/exchange-rates', ['rate_currency' => 'EUR', 'rate_date' => '2026-10-24', 'rate' => '4.1']);
        $this->assertSessionError('rate', text('admiro::admin.exchange_rate_rejected'));

        $this->get('/admiro/exchange-rates?currency=EUR')->assertOk()->assertSee('4.260100')->assertSee('206/A/NBP/2026')->assertSee('4.300000');

        $nbp = ExchangeRate::query()->where('source', ExchangeRateSource::Nbp->value)->sole();
        $manual = ExchangeRate::query()->where('source', ExchangeRateSource::Manual->value)->sole();
        $this->delete('/admiro/exchange-rates/' . $nbp->token);
        $this->assertSessionError('rate', text('admiro::admin.exchange_rate_rejected'));
        $this->delete('/admiro/exchange-rates/' . $manual->token)->assertRedirect();

        $this->assertSame(1, ExchangeRate::query()->count());
        $this->assertSame(['exchange rates synced', 'exchange rate added', 'exchange rate removed'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_viewer_sees_rates_but_cannot_change_them(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/exchange-rates')->assertOk()->assertDontSee(text('admiro::admin.exchange_rates_sync_action'));
        $this->post('/admiro/exchange-rates', ['rate_currency' => 'EUR', 'rate_date' => '2026-10-24', 'rate' => '4.3'])->assertForbidden();
        $this->assertSame(0, ExchangeRate::query()->count());
    }

    public function test_unavailable_nbp_is_reported(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::response('error', 500)]);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/exchange-rates/sync', ['sync_from' => '2026-10-20', 'sync_to' => '2026-10-23'])->assertRedirect();

        $this->assertSessionError('sync_from', text('admiro::admin.exchange_rates_unavailable'));
    }
}
