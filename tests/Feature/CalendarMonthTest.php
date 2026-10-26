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
use Simplysmart\Admiro\View\Calendar\CalendarMonth;
use Tests\TestCase;

/**
 * Miesiąc kalendarza panelu: pełne tygodnie od poniedziałku z dniami sąsiednich miesięcy, dziś i dni wolne.
 */
class CalendarMonthTest extends TestCase
{
    public function test_grid_has_whole_weeks_from_monday_with_today_and_days_off(): void
    {
        $calendar = new CalendarMonth(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-26'), ['2026-11-01' => 'Wszystkich Świętych']);
        $weeks = $calendar->weeks();

        $this->assertCount(5, $weeks);
        $this->assertSame('2026-09-28', $weeks[0][0]->toDateString());
        $this->assertSame('2026-11-01', $weeks[4][6]->toDateString());
        $this->assertFalse($calendar->isInMonth($weeks[0][0]));
        $this->assertTrue($calendar->isToday($weeks[4][0]));
        $this->assertSame('Wszystkich Świętych', $calendar->holiday($weeks[4][6]));
        $this->assertNull($calendar->holiday($weeks[4][0]));
    }
}
