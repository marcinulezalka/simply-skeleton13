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

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Helpery local_* z Core (admiro): formatowanie kwot, liczb, dat, dni i miesięcy według języka.
 */
class LocalHelpersTest extends TestCase
{
    public function test_money_uses_locale_and_default_currency(): void
    {
        App::setLocale('pl');

        $this->assertSame(Number::currency(1234.5, 'PLN', 'pl'), local_money(1234.5));
        $this->assertSame(Number::currency(1234.5, 'EUR', 'en'), local_money(1234.5, 'EUR', 'en'));
    }

    public function test_money_default_currency_comes_from_config(): void
    {
        config(['admiro.locale.currency' => 'EUR']);

        $this->assertSame(Number::currency(10, 'EUR', 'pl'), local_money(10, locale: 'pl'));
    }

    public function test_number_uses_locale_separators(): void
    {
        $this->assertSame(Number::format(9876543.21, 2, locale: 'pl'), local_number(9876543.21, 2, 'pl'));
        $this->assertSame('9,876,543.21', local_number(9876543.21, 2, 'en'));
    }

    public function test_date_formats_follow_locale(): void
    {
        App::setLocale('pl');

        $this->assertSame('20.10.2026', local_date('2026-10-20'));
        $this->assertSame('20 października 2026', local_date(new CarbonImmutable('2026-10-20'), 'D MMMM YYYY'));
        $this->assertSame('10/20/2026', local_date('2026-10-20', locale: 'en'));
    }

    public function test_day_and_month_names(): void
    {
        App::setLocale('pl');

        $this->assertSame('poniedziałek', local_day(1));
        $this->assertSame('niedziela', local_day(7));
        $this->assertSame('wtorek', local_day(new CarbonImmutable('2026-10-20')));
        $this->assertSame('marzec', local_month(3));
        $this->assertSame('March', local_month(3, locale: 'en'));
    }

    public function test_day_out_of_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        local_day(0);
    }

    public function test_month_out_of_range_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        local_month(13);
    }
}
