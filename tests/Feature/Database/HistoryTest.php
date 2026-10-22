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

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Mainboard\Database\History;
use Simplysmart\Mainboard\Database\History\MysqlHistoryTriggers;
use Tests\Fixtures\Database\DemoItem;

/**
 * Historia zmian na triggerach: wiersz przed i po zmianie jako JSON, synchronizacja po zmianie
 * kolumn, zgodność triggerów z kolumnami każdej tabeli słownika.
 */
class HistoryTest extends DatabaseTestCase
{
    private const array DICTIONARY_TABLES = ['locale_currencies', 'locale_languages', 'locale_countries', 'locale_country_language', 'locale_tax_rates'];

    public function test_insert_update_and_delete_are_recorded_with_the_whole_row(): void
    {
        $item = $this->item();
        $item->forceFill(['name' => 'Nowa nazwa'])->save();
        DB::table(DemoItem::TABLE)->where('id', $item->id)->delete();

        $entries = DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->orderBy('id')->get();
        $this->assertSame(['insert', 'update', 'delete'], $entries->pluck('action')->all());
        [$insert, $update, $delete] = $entries->all();

        $this->assertNull($insert->old_values);
        $this->assertSame('Pozycja', $this->decoded($update->old_values)['name']);
        $this->assertSame('Nowa nazwa', $this->decoded($update->new_values)['name']);
        $this->assertSame('Nowa nazwa', $this->decoded($delete->old_values)['name']);
        $this->assertNull($delete->new_values);
        $this->assertNull($insert->actor_zone);
    }

    public function test_changes_made_outside_eloquent_are_recorded_too(): void
    {
        $item = $this->item();
        DB::table(DemoItem::TABLE)->where('id', $item->id)->update(['code' => 'SQL']);

        $this->assertSame('SQL', $this->decoded(DB::table(History::tableFor(DemoItem::TABLE))->where('action', 'update')->value('new_values'))['code']);
    }

    public function test_sync_tracks_a_new_column(): void
    {
        $history = $this->app->make(History::class);
        Schema::table(DemoItem::TABLE, static function (Blueprint $table): void {
            $table->string('color')->nullable();
        });

        $this->assertNotContains('color', $history->trackedColumns(DemoItem::TABLE));

        $history->sync(DemoItem::TABLE);

        $this->assertSame(Schema::getColumnListing(DemoItem::TABLE), $history->trackedColumns(DemoItem::TABLE));
    }

    public function test_every_dictionary_table_has_triggers_matching_its_columns(): void
    {
        $history = $this->app->make(History::class);

        foreach (self::DICTIONARY_TABLES as $table) {
            $this->assertTrue(Schema::hasTable(History::tableFor($table)), $table);
            $this->assertSame(Schema::getColumnListing($table), $history->trackedColumns($table), $table);
        }
    }

    public function test_drop_removes_triggers_and_the_history_table(): void
    {
        $this->app->make(History::class)->drop(DemoItem::TABLE);
        $this->item();

        $this->assertFalse(Schema::hasTable(History::tableFor(DemoItem::TABLE)));
    }

    public function test_mysql_triggers_read_the_actor_from_session_variables(): void
    {
        $connection = DB::connection();
        $this->assertInstanceOf(Connection::class, $connection);

        $sql = implode("\n", (new MysqlHistoryTriggers($connection))->createStatements('demo', 'demo_history', ['id', 'name']));

        $this->assertStringContainsString('AFTER UPDATE', $sql);
        $this->assertStringContainsString("JSON_OBJECT('id', OLD.", $sql);
        $this->assertStringContainsString('@actor_zone, @actor_id, @request_id', $sql);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decoded(mixed $json): array
    {
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
