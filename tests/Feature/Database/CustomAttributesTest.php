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

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Simplysmart\Admiro\Attributes\CustomAttribute;
use Simplysmart\Admiro\Attributes\CustomAttributes;
use Simplysmart\Admiro\Database\History;
use Tests\Fixtures\Database\DemoItem;

/**
 * Globalne atrybuty rekordów (Attributes\CustomAttributes, x-admiro::attributes-editor): wiersze grupa – nazwa –
 * wartość z formularza w kolejności listy, bez pustych nazw, w `json_attributes.custom` obok danych systemowych,
 * jednym wpisem historii; bez zmian nic się nie zapisuje, a pusta lista usuwa klucz; edytor rysuje wiersze, pusty
 * wiersz dla wersji bez skryptu i szablon nowego wiersza.
 */
class CustomAttributesTest extends DatabaseTestCase
{
    public function test_rows_are_saved_in_order_without_blank_names_next_to_system_data_with_one_history_entry(): void
    {
        $item = $this->item(['json_attributes' => ['brand' => ['logo' => 'a.svg']]]);
        $history = DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->count();

        $attributes = CustomAttributes::fromRows([
            ['group' => ' Kontakt ', 'name' => 'Recepcja', 'value' => ' wew. 12 '],
            ['group' => '', 'name' => '   ', 'value' => 'bez nazwy'],
            'nie wiersz',
            ['name' => 'Parking', 'value' => ''],
        ]);

        $this->assertTrue($this->app->make(CustomAttributes::class)->save($item, $attributes));
        $this->assertSame([
            'brand' => ['logo' => 'a.svg'],
            CustomAttributes::KEY => [
                ['group' => 'Kontakt', 'name' => 'Recepcja', 'value' => 'wew. 12'],
                ['group' => null, 'name' => 'Parking', 'value' => null],
            ],
        ], $item->fresh()?->json_attributes);
        $this->assertEquals([new CustomAttribute('Kontakt', 'Recepcja', 'wew. 12'), new CustomAttribute(null, 'Parking', null)], CustomAttributes::of($item->refresh()));
        $this->assertSame($history + 1, DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->count(), 'Jeden wpis historii na zapis.');
    }

    public function test_unchanged_attributes_are_not_saved_and_an_empty_list_removes_the_key(): void
    {
        $item = $this->item(['json_attributes' => ['brand' => ['logo' => 'a.svg']]]);
        $attributes = $this->app->make(CustomAttributes::class);
        $attributes->save($item, [new CustomAttribute(null, 'Parking', 'tak')]);
        $history = DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->count();

        $this->assertFalse($attributes->save($item, [new CustomAttribute(null, 'Parking', 'tak')]));
        $this->assertSame($history, DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->count());

        $this->assertTrue($attributes->save($item, []));
        $this->assertSame(['brand' => ['logo' => 'a.svg']], $item->fresh()?->json_attributes);
    }

    public function test_editor_draws_the_rows_a_blank_row_for_no_script_and_a_template(): void
    {
        $html = Blade::render('<x-admiro::attributes-editor :items="$items" />', ['items' => [new CustomAttribute('Kontakt', 'Recepcja', 'wew. 12')]]);

        $this->assertStringContainsString('<input type="hidden" name="' . CustomAttributes::FIELD . '" value="">', $html);
        $this->assertStringContainsString('name="custom_attributes[0][name]"', $html);
        $this->assertStringContainsString('value="Recepcja"', $html);
        $this->assertSame(1, substr_count($html, 'data-ss-attributes-blank'), 'Jeden pusty wiersz dla wersji bez skryptu.');
        $this->assertStringContainsString('name="custom_attributes[1][name]"', $html);
        $this->assertMatchesRegularExpression('~<template data-ss-attributes-template>.*?name="custom_attributes\[__INDEX__\]\[group\]"~s', $html);
        $this->assertStringContainsString(text('layout.attributes.add'), $html);

        $readOnly = Blade::render('<x-admiro::attributes-editor :items="$items" :disabled="true" />', ['items' => [new CustomAttribute(null, 'Parking', 'tak')]]);
        $this->assertStringNotContainsString('data-ss-attributes-blank', $readOnly);
        $this->assertStringNotContainsString('data-ss-attributes-template', $readOnly);
        $this->assertStringContainsString('value="Parking"', $readOnly);
    }
}
