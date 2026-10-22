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

use Illuminate\Support\Facades\Schema;
use Simplysmart\Mainboard\Database\ColumnNames;
use Simplysmart\Mainboard\Models\MainModel;
use Tests\Fixtures\Database\DemoItem;

/**
 * Kolumny standardowe (Columns) i DataModel: token, soft delete, checksum, active(), tłumaczenia json_i18n.
 */
class DataModelTest extends DatabaseTestCase
{
    public function test_standard_columns_frame_the_domain_columns(): void
    {
        $this->assertSame([
            ColumnNames::ID, ColumnNames::TOKEN, ColumnNames::PARENT_ID, ColumnNames::NAME, ColumnNames::STATUS,
            ColumnNames::IS_ACTIVE, ColumnNames::IS_DEFAULT, ColumnNames::SORT_ORDER,
            'code',
            ColumnNames::JSON_ATTRIBUTES, ColumnNames::JSON_DEFAULT_SETTINGS, ColumnNames::JSON_TAGS, ColumnNames::JSON_I18N,
            ColumnNames::JSON_MIGRATIONS, ColumnNames::CHECKSUM, ColumnNames::JSON_ARCHIVE, ColumnNames::CREATED_AT, ColumnNames::UPDATED_AT, ColumnNames::DELETED_AT,
        ], Schema::getColumnListing(DemoItem::TABLE));
    }

    public function test_every_column_has_a_cast(): void
    {
        $this->assertSame([], array_values(array_diff(Schema::getColumnListing(DemoItem::TABLE), array_keys((new DemoItem())->getCasts()))));
    }

    public function test_new_record_gets_a_random_token_used_as_the_route_key(): void
    {
        $item = $this->item();

        $this->assertSame(MainModel::TOKEN_LENGTH, strlen($item->token));
        $this->assertSame($item->token, $item->getRouteKey());
        $this->assertNotSame($item->token, $this->item(['code' => 'B2'])->token);
    }

    public function test_delete_is_soft(): void
    {
        $item = $this->item();
        $item->delete();

        $this->assertNull(DemoItem::query()->find($item->id));
        $this->assertNotNull(DemoItem::withTrashed()->find($item->id));
    }

    public function test_checksum_follows_domain_columns_only(): void
    {
        $item = $this->item(['json_attributes' => ['b' => 1, 'a' => 2]]);
        $checksum = $item->checksum;

        $this->assertSame($checksum, $item->refresh()->computeChecksum());
        $this->assertSame($checksum, $this->item(['code' => 'A1-copy', 'json_attributes' => ['a' => 2, 'b' => 1]])->forceFill(['code' => 'A1'])->computeChecksum());

        $item->forceFill(['json_migrations' => ['source' => 'erp'], 'json_archive' => ['old' => 1]])->save();
        $this->assertSame($checksum, $item->refresh()->checksum);

        $item->forceFill(['name' => 'Inna pozycja'])->save();
        $this->assertNotSame($checksum, $item->refresh()->checksum);
    }

    public function test_active_scope_keeps_switched_on_records(): void
    {
        $this->item(['code' => 'ON', 'is_active' => true]);
        $this->item(['code' => 'OFF']);

        $this->assertSame(['ON'], DemoItem::query()->active()->pluck('code')->all());
    }

    public function test_default_scope_keeps_the_default_record(): void
    {
        $this->item(['code' => 'MAIN', 'is_default' => true]);
        $this->item(['code' => 'OTHER']);

        $this->assertSame(['MAIN'], DemoItem::query()->default()->pluck('code')->all());
        $this->assertFalse((new DemoItem())->is_default);
    }

    public function test_default_settings_and_tags_are_read_from_json(): void
    {
        $item = $this->item(['json_default_settings' => ['invoice' => ['due_days' => 14]], 'json_tags' => ['eu', 'b2b']])->refresh();

        $this->assertSame(14, $item->defaultSetting('invoice.due_days'));
        $this->assertSame(30, $item->defaultSetting('invoice.reminder_days', 30));
        $this->assertSame(['eu', 'b2b'], $item->json_tags);
    }

    public function test_translated_name_has_no_fallback_language(): void
    {
        $item = $this->item(['json_i18n' => ['pl' => 'Pozycja', 'en' => 'Item']]);

        $this->assertSame('Item', $item->translatedName('en'));
        $this->assertSame('Pozycja', $item->translatedName());
        $this->assertSame('A1', $item->translatedName('de'));
    }
}
