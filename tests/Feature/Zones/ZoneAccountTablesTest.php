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

namespace Tests\Feature\Zones;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simplysmart\Mainboard\Contracts\AccountLocator;
use Simplysmart\Mainboard\Contracts\CurrentZone;
use Simplysmart\Mainboard\Contracts\ZoneRegistry;
use Simplysmart\Mainboard\Database\ColumnNames;
use Simplysmart\Mainboard\Database\History;
use Simplysmart\Mainboard\Database\IdSlots;
use Simplysmart\Mainboard\Database\ZoneTables;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Tabela kont strefy ma pełny standard tabel danych: kolumny Columns::base()/closing(), historię
 * `<strefa>_accounts_history`, pulę id od 1000, checksum i soft delete (konto usunięte się nie loguje).
 */
class ZoneAccountTablesTest extends ZoneTestCase
{
    private const string ACCOUNTS = 'alpha_accounts';

    public function test_accounts_table_frames_sign_in_columns_with_standard_columns(): void
    {
        $columns = Schema::getColumnListing(self::ACCOUNTS);

        $this->assertSame([ColumnNames::ID, ColumnNames::TOKEN, ColumnNames::PARENT_ID, ColumnNames::NAME, ColumnNames::STATUS, ColumnNames::IS_ACTIVE, ColumnNames::IS_DEFAULT, ColumnNames::SORT_ORDER], array_slice($columns, 0, 8));
        $this->assertSame([ColumnNames::JSON_ATTRIBUTES, ColumnNames::JSON_DEFAULT_SETTINGS, ColumnNames::JSON_TAGS, ColumnNames::JSON_MIGRATIONS, ColumnNames::CHECKSUM, ColumnNames::JSON_ARCHIVE, ColumnNames::CREATED_AT, ColumnNames::UPDATED_AT, ColumnNames::DELETED_AT], array_slice($columns, -9));
        $this->assertSame($columns, $this->app->make(History::class)->trackedColumns(self::ACCOUNTS));
    }

    public function test_account_gets_a_user_id_checksum_and_history(): void
    {
        $account = $this->account(AlphaAccount::class, ['json_default_settings' => ['dashboard' => 'compact']]);

        $this->assertSame(IdSlots::FIRST_USER_ID, $account->id);
        $this->assertSame($account->computeChecksum(), $account->refresh()->checksum);
        $this->assertSame('compact', $account->defaultSetting('dashboard'));
        $this->assertSame(1, DB::table(History::tableFor(self::ACCOUNTS))->where('row_id', $account->id)->where('action', 'insert')->count());
    }

    public function test_deleted_account_cannot_sign_in_but_keeps_its_address(): void
    {
        $this->account(AlphaAccount::class)->delete();
        $this->app->make(CurrentZone::class)->set($this->app->make(ZoneRegistry::class)->get('alpha'));
        $accounts = $this->app->make(AccountLocator::class);

        $this->assertNull($accounts->findByEmail('anna@example.com'));
        $this->assertTrue($accounts->isEmailTaken('Anna@Example.com'));
        $this->assertFalse($accounts->isEmailTaken('jan@example.com'));
    }

    public function test_dropping_zone_tables_removes_the_accounts_history(): void
    {
        $this->app->make(ZoneTables::class)->drop('beta');

        $this->assertFalse(Schema::hasTable('beta_accounts'));
        $this->assertFalse(Schema::hasTable(History::tableFor('beta_accounts')));
    }
}
