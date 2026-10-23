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

use Simplysmart\Mainboard\Enums\TaxRateType;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Models\Dictionaries\Country;
use Simplysmart\Mainboard\Models\Dictionaries\Language;
use Simplysmart\Mainboard\Models\Dictionaries\TaxRate;

/**
 * Słowniki w panelu: włączanie i wyłączanie krajów, języków i walut (bez wpisów domyślnych i języków
 * interfejsu), stawki VAT kraju (nowa podstawowa kończy poprzednią, zakończenie stawki), uprawnienia ustawień.
 */
class AdmiroDictionariesTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_entries_are_disabled_and_enabled_with_an_audit_entry(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/dictionaries/countries/DE/toggle')->assertRedirect('/admiro/dictionaries/countries');
        $this->assertFalse(Country::query()->where('alpha_code_2', 'DE')->sole()->is_active);

        $this->post('/admiro/dictionaries/countries/DE/toggle');
        $this->assertTrue(Country::query()->where('alpha_code_2', 'DE')->sole()->is_active);
        $this->assertSame(['dictionary entry disabled', 'dictionary entry enabled'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_default_entry_and_interface_language_stay_enabled(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/dictionaries/countries/PL/toggle');
        $this->post('/admiro/dictionaries/languages/pl/toggle');

        $this->assertTrue(Country::query()->where('alpha_code_2', 'PL')->sole()->is_active);
        $this->assertTrue(Language::query()->where('code', 'pl')->sole()->is_active);
        $this->assertSessionError('dictionary', text('admiro::admin.dictionary_rejected'));
    }

    public function test_new_standard_rate_ends_the_previous_one(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/tax-rates/PL', ['type' => 'standard', 'rate' => '23', 'valid_from' => '2011-01-01'])->assertRedirect('/admiro/tax-rates/PL');
        $this->post('/admiro/tax-rates/PL', ['type' => 'standard', 'rate' => '24', 'valid_from' => '2030-01-01']);

        [$previous, $current] = TaxRate::query()->orderBy('valid_from')->get()->all();
        $this->assertSame('2029-12-31', $previous->valid_to?->toDateString());
        $this->assertNull($current->valid_to);
        $this->assertSame('24%', $current->name);
    }

    public function test_invalid_rates_are_rejected(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $this->post('/admiro/tax-rates/PL', ['type' => 'standard', 'rate' => '23', 'valid_from' => '2030-01-01']);

        $this->post('/admiro/tax-rates/PL', ['type' => 'reduced', 'valid_from' => '2030-01-01']);
        $this->post('/admiro/tax-rates/PL', ['type' => 'standard', 'rate' => '22', 'valid_from' => '2029-01-01']);

        $this->assertSame(1, TaxRate::query()->count());
        $this->assertSessionError('rate', text('admiro::admin.tax_rate_rejected'));
    }

    public function test_exempt_rate_needs_no_percentage_and_rates_can_end(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $this->post('/admiro/tax-rates/PL', ['type' => TaxRateType::Exempt->value, 'rate' => '5', 'valid_from' => '2020-01-01']);
        $rate = TaxRate::query()->sole();

        $this->put('/admiro/tax-rates/PL/' . $rate->token, ['valid_to' => '2019-12-31']);
        $this->assertNull($rate->fresh()?->valid_to);

        $this->put('/admiro/tax-rates/PL/' . $rate->token, ['valid_to' => '2025-12-31']);
        $rate->refresh();
        $this->assertNull($rate->rate);
        $this->assertSame('2025-12-31', $rate->valid_to?->toDateString());
    }

    public function test_support_sees_dictionaries_without_changing_them(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/dictionaries/currencies')->assertOk()->assertSee('EUR')->assertDontSee('/toggle"', false);
        $this->get('/admiro/tax-rates/PL')->assertOk();
        $this->post('/admiro/dictionaries/countries/DE/toggle')->assertForbidden();
        $this->get('/admiro/dictionaries/planets')->assertNotFound();
    }
}
