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

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Admiro\Database\ColumnNames;
use Simplysmart\Admiro\Database\History;
use Simplysmart\Admiro\Database\IdSlots;
use Tests\TestCase;

/**
 * Stockowa tabela `users` wyrównana migracją Core do kolumn standardowych, z historią i pulą id;
 * stockowy model i fabryka działają bez zmian.
 */
class UsersTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_the_standard_columns(): void
    {
        $columns = Schema::getColumnListing('users');
        $standard = [
            ColumnNames::TOKEN, ColumnNames::PARENT_ID, ColumnNames::STATUS, ColumnNames::IS_ACTIVE, ColumnNames::IS_DEFAULT,
            ColumnNames::SORT_ORDER, ColumnNames::JSON_ATTRIBUTES, ColumnNames::JSON_DEFAULT_SETTINGS, ColumnNames::JSON_TAGS,
            ColumnNames::JSON_MIGRATIONS, ColumnNames::CHECKSUM, ColumnNames::JSON_ARCHIVE, ColumnNames::DELETED_AT,
        ];

        $this->assertSame([], array_values(array_diff($standard, $columns)));
        $this->assertSame($columns, $this->app->make(History::class)->trackedColumns('users'));
    }

    public function test_stock_user_factory_gets_a_user_id_and_history(): void
    {
        $user = User::factory()->create();

        $this->assertSame(IdSlots::FIRST_USER_ID, $user->id);
        $this->assertSame(1, DB::table(History::tableFor('users'))->where('row_id', $user->id)->count());
    }
}
