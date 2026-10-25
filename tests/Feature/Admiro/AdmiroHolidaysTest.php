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

use Illuminate\Support\Facades\Http;
use Simplysmart\Mainboard\Enums\HolidaySource;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Models\Dictionaries\Holiday;

/**
 * Dni wolne w panelu: lista kraju w roku dla `settings.view`, pobranie z kalendarza świąt, dzień wolny ręczny
 * i usunięcie tylko wpisu ręcznego dla `settings.manage`, każda zmiana w dzienniku zdarzeń.
 */
class AdmiroHolidaysTest extends AdmiroTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDictionaries();
    }

    public function test_owner_fetches_holidays_and_manages_manual_days_off(): void
    {
        Http::fake(['date.nager.at/*' => Http::response([['date' => '2026-12-25', 'localName' => 'Boże Narodzenie', 'name' => 'Christmas Day', 'global' => true, 'types' => ['Public']]])]);
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->post('/admiro/holidays/sync', ['country' => 'PL', 'year' => 2026])->assertRedirect('/admiro/holidays?country=PL&year=2026');
        $this->post('/admiro/holidays', ['country' => 'PL', 'holiday_date' => '2026-12-24', 'holiday_name' => 'Wigilia (dzień wolny)'])->assertRedirect();
        $this->post('/admiro/holidays', ['country' => 'PL', 'holiday_date' => '2026-12-24', 'holiday_name' => 'Drugi']);
        $this->assertSessionError('holiday', text('admiro::admin.holiday_rejected'));

        $this->get('/admiro/holidays?country=PL&year=2026')->assertOk()->assertSee('Boże Narodzenie')->assertSee('Wigilia (dzień wolny)');

        $nager = Holiday::query()->where('source', HolidaySource::Nager->value)->sole();
        $manual = Holiday::query()->where('source', HolidaySource::Manual->value)->sole();
        $this->delete('/admiro/holidays/PL/' . $nager->token);
        $this->assertSessionError('holiday', text('admiro::admin.holiday_rejected'));
        $this->delete('/admiro/holidays/PL/' . $manual->token)->assertRedirect();

        $this->assertSame(1, Holiday::query()->count());
        $this->assertSame(['holidays synced', 'holiday added', 'holiday removed'], AuditEvent::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_viewer_sees_days_off_but_cannot_change_them(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->get('/admiro/holidays')->assertOk()->assertDontSee(text('admiro::admin.holidays_sync_action'));
        $this->post('/admiro/holidays', ['country' => 'PL', 'holiday_date' => '2026-12-24', 'holiday_name' => 'X'])->assertForbidden();
        $this->assertSame(0, Holiday::query()->count());
    }
}
