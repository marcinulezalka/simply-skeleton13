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

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Admiro\Database\Columns;
use Simplysmart\Admiro\Database\History;
use Simplysmart\Admiro\Database\IdSlots;
use Tests\Fixtures\Database\DemoItem;
use Tests\TestCase;

/**
 * Baza testów fundamentu bazy: migracje Core i tabela testowa `demo_items` z kolumnami standardowymi
 * historią i pulą id seederów (SQLite w pamięci).
 */
abstract class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(DemoItem::TABLE, static function (Blueprint $table): void {
            Columns::base($table);
            $table->string('code', 20)->unique();
            Columns::closing($table, i18n: true);
        });

        $this->app->make(History::class)->create(DemoItem::TABLE);
        $this->app->make(IdSlots::class)->reserve(DemoItem::TABLE);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function item(array $attributes = []): DemoItem
    {
        $item = new DemoItem();
        $item->forceFill(['name' => 'Pozycja', 'code' => 'A1', ...$attributes])->save();

        return $item;
    }
}
