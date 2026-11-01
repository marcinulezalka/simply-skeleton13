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
 * Pola dodatkowe rekordów (Attributes\CustomAttributes, x-admiro::attributes-editor): sekcje rekordu z formularza
 * (klucz sekcji ze wzoru), wiersze grupa – nazwa – wartość w kolejności listy, bez pustych nazw, w
 * `json_attributes.custom.<sekcja>` obok danych systemowych, jednym wpisem historii; zapis sekcji nie rusza innych,
 * pusta sekcja znika, bez zmian nic się nie zapisuje, a dawna płaska lista należy do sekcji domyślnej; edytor rysuje
 * podgląd, wiersze sekcji, pierwszy pusty wiersz i szablon nowego wiersza.
 */
class CustomAttributesTest extends DatabaseTestCase
{
    public function test_sections_are_saved_in_order_without_blank_names_next_to_system_data_with_one_history_entry(): void
    {
        $item = $this->item(['json_attributes' => ['brand' => ['logo' => 'a.svg']]]);
        $history = $this->historyCount($item);

        $sections = CustomAttributes::fromInput([
            'contact' => [
                ['group' => ' Kontakt ', 'name' => 'Recepcja', 'value' => ' wew. 12 '],
                ['group' => '', 'name' => '   ', 'value' => 'bez nazwy'],
                'nie wiersz',
                ['name' => 'Parking', 'value' => ''],
            ],
            'Zła sekcja' => [['name' => 'X']],
            7 => [['name' => 'Y']],
        ]);

        $this->assertSame(['contact'], array_keys($sections));
        $this->assertTrue($this->app->make(CustomAttributes::class)->save($item, $sections));
        $this->assertSame([
            'brand' => ['logo' => 'a.svg'],
            CustomAttributes::KEY => ['contact' => [
                ['group' => 'Kontakt', 'name' => 'Recepcja', 'value' => 'wew. 12'],
                ['group' => null, 'name' => 'Parking', 'value' => null],
            ]],
        ], $item->refresh()->json_attributes);
        $this->assertEquals([new CustomAttribute('Kontakt', 'Recepcja', 'wew. 12'), new CustomAttribute(null, 'Parking', null)], CustomAttributes::of($item, 'contact'));
        $this->assertSame([], CustomAttributes::of($item));
        $this->assertSame($history + 1, $this->historyCount($item), 'Jeden wpis historii na zapis.');
    }

    public function test_a_section_save_keeps_the_others_unchanged_saves_nothing_and_an_empty_section_disappears(): void
    {
        $item = $this->item(['json_attributes' => ['brand' => ['logo' => 'a.svg']]]);
        $attributes = $this->app->make(CustomAttributes::class);
        $attributes->save($item, ['contact' => [new CustomAttribute(null, 'Parking', 'tak')], 'address' => [new CustomAttribute(null, 'Piętro', '2')]]);
        $history = $this->historyCount($item);

        $this->assertFalse($attributes->save($item, ['contact' => [new CustomAttribute(null, 'Parking', 'tak')]]));
        $this->assertSame($history, $this->historyCount($item));

        $this->assertTrue($attributes->save($item, ['contact' => []]));
        $this->assertSame(['brand' => ['logo' => 'a.svg'], CustomAttributes::KEY => ['address' => [['group' => null, 'name' => 'Piętro', 'value' => '2']]]], $item->refresh()->json_attributes);
        $this->assertTrue($attributes->save($item, ['address' => []]));
        $this->assertSame(['brand' => ['logo' => 'a.svg']], $item->refresh()->json_attributes);
    }

    public function test_an_old_flat_list_belongs_to_the_default_section(): void
    {
        $item = $this->item(['json_attributes' => [CustomAttributes::KEY => [['group' => null, 'name' => 'Parking', 'value' => 'tak']]]]);

        $this->assertEquals([new CustomAttribute(null, 'Parking', 'tak')], CustomAttributes::of($item));
        $this->app->make(CustomAttributes::class)->save($item, ['contact' => [new CustomAttribute(null, 'Recepcja', null)]]);
        $this->assertSame([CustomAttributes::DEFAULT_SECTION, 'contact'], array_keys((array) ($item->refresh()->json_attributes[CustomAttributes::KEY] ?? [])));
    }

    public function test_editor_draws_the_preview_the_section_rows_a_first_blank_row_and_a_template(): void
    {
        $html = Blade::render('<x-admiro::attributes-editor :items="$items" section="contact" />', ['items' => [new CustomAttribute('Kontakt', 'Recepcja', 'wew. 12')]]);

        $this->assertMatchesRegularExpression('~<div class="ss-folder-card__preview">\s*<dl class="ss-attributes__preview">.*?Kontakt · </span>Recepcja</dt>\s*<dd>wew. 12</dd>~s', $html);
        $this->assertStringContainsString('<input type="hidden" name="' . CustomAttributes::FIELD . '[contact]" value="">', $html);
        $this->assertStringContainsString('name="custom_attributes[contact][0][name]"', $html);
        $this->assertStringContainsString('id="custom-attributes-contact-0-name"', $html);
        $this->assertSame(1, substr_count($html, 'data-ss-attributes-blank'), 'Pusty wiersz na nowe pole (skrypt zostawia go tylko bez pól).');
        $this->assertMatchesRegularExpression('~<template data-ss-attributes-template>.*?name="custom_attributes\[contact\]\[__INDEX__\]\[group\]"~s', $html);
        $this->assertStringContainsString(text('layout.attributes.add'), $html);

        $empty = Blade::render('<x-admiro::attributes-editor :items="[]" />');
        $this->assertStringContainsString(text('layout.attributes.empty'), $empty);
        $this->assertStringContainsString('name="custom_attributes[' . CustomAttributes::DEFAULT_SECTION . '][0][name]"', $empty, 'Bez pól pierwszy wiersz formularza.');

        $readOnly = Blade::render('<x-admiro::attributes-editor :items="$items" readonly />', ['items' => [new CustomAttribute(null, 'Parking', 'tak')]]);
        $this->assertStringContainsString('Parking</dt>', $readOnly);
        $this->assertStringNotContainsString('<input', $readOnly);
    }

    private function historyCount(DemoItem $item): int
    {
        return DB::table(History::tableFor(DemoItem::TABLE))->where('row_id', $item->id)->count();
    }
}
