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

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\DB;
use OverflowException;
use Simplysmart\Admiro\Database\IdSlots;
use Tests\Fixtures\Database\DemoItem;

/**
 * Pule id: rekordy użytkowników od 1000, sloty seederów 1–999 nadawane po kolei, pełna pula to błąd.
 */
class IdSlotsTest extends DatabaseTestCase
{
    public function test_user_records_start_at_the_first_user_id(): void
    {
        $this->assertSame(IdSlots::FIRST_USER_ID, $this->item()->id);
    }

    public function test_seed_slots_follow_the_highest_seeded_id(): void
    {
        $slots = $this->app->make(IdSlots::class);
        $this->assertSame(1, $slots->next(DemoItem::TABLE));

        $this->item(['id' => $slots->next(DemoItem::TABLE)]);
        $this->item(['code' => 'U1']);

        $this->assertSame(2, $slots->next(DemoItem::TABLE));
    }

    public function test_full_seed_pool_is_an_error(): void
    {
        $this->item(['id' => IdSlots::SEED_MAX]);

        $this->expectException(OverflowException::class);

        $this->app->make(IdSlots::class)->next(DemoItem::TABLE);
    }

    public function test_seeded_id_does_not_move_the_user_counter(): void
    {
        $this->item(['id' => 5]);

        $this->assertSame(IdSlots::FIRST_USER_ID, $this->item(['code' => 'U1'])->id);
        $this->assertSame(1, DB::table(DemoItem::TABLE)->where('id', '<', IdSlots::FIRST_USER_ID)->count());
    }
}
