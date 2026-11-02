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

use Simplysmart\Admiro\Database\Seeders\OrganizationUnitTypeSeeder;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Models\OrganizationUnitType;
use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Organizations\OrganizationUnitTypeManagement;

/**
 * Słownik typów jednostek organizacyjnych: typy domyślne z migracji w slotach 1–999 (struktura wewnętrzna poza
 * publicznym grafikiem), lista w panelu, dodanie, zmiana i wyłączenie typu (`settings.manage`) oraz typ jednostki
 * z numerem w widoku z prefiksem.
 */
class AdmiroUnitTypesTest extends AdmiroTestCase
{
    public function test_default_types_come_with_the_migration_in_seed_slots(): void
    {
        $this->assertSame(count(OrganizationUnitTypeSeeder::DEFAULTS), OrganizationUnitType::query()->count());
        $this->assertTrue(OrganizationUnitType::query()->where('id', '>=', 1000)->doesntExist(), 'Typy domyślne w slotach 1–999.');
        $administration = OrganizationUnitType::query()->where('code', 'administracja')->sole();
        $this->assertTrue($administration->is_internal);
        $this->assertFalse($administration->is_public, 'Struktura wewnętrzna poza publicznym grafikiem.');
        $this->assertSame('ADM-12', $administration->displayNumber('12'));
        $this->assertNull($administration->displayNumber(null));
        $this->assertSame('cross', OrganizationUnitType::query()->where('code', 'poradnia')->sole()->iconName(), 'Poradnia z krzyżem medycznym.');

        $this->app->make(OrganizationUnitTypeSeeder::class)->run();
        $this->assertSame(count(OrganizationUnitTypeSeeder::DEFAULTS), OrganizationUnitType::query()->count(), 'Seeder jest idempotentny.');
    }

    public function test_admin_adds_edits_and_disables_a_type_and_a_unit_keeps_it(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $this->get('/admiro/unit-types')->assertOk()->assertSee('Dział administracyjny')->assertSee('ADM-');

        $this->post('/admiro/unit-types', ['name' => 'Punkt pobrań', 'number_prefix' => 'pp-', 'icon' => 'activity', 'is_public' => '1'])
            ->assertRedirect()->assertSessionHas('status', text('admiro::admin.unit_types.saved'));
        $type = OrganizationUnitType::query()->where('name', 'Punkt pobrań')->sole();
        $this->assertSame(['punkt_pobran', 'PP-', 'activity', false, true, true], [$type->code, $type->number_prefix, $type->icon, $type->is_internal, $type->is_public, (bool) $type->is_active]);

        $this->put('/admiro/unit-types/' . $type->token, ['name' => 'Punkt pobrań krwi', 'number_prefix' => 'PPK-', 'icon' => 'heart', 'is_internal' => '1'])->assertRedirect();
        $type->refresh();
        $this->assertSame(['Punkt pobrań krwi', 'PPK-', true, false], [$type->name, $type->number_prefix, $type->is_internal, $type->is_public]);
        $this->post('/admiro/unit-types', ['name' => 'X', 'icon' => 'rocket'])->assertSessionHasErrors('icon');

        $unit = $this->app->make(OrganizationManagement::class)->create(new OrganizationData('Laboratorium'));
        $this->app->make(OrganizationUnitTypeManagement::class)->assign($unit, $type);
        $this->post('/admiro/unit-types/' . $type->token . '/toggle')->assertRedirect();
        $this->assertFalse((bool) $type->refresh()->is_active);
        $this->assertSame($type->id, Organization::query()->findOrFail($unit->id)->unitType?->id, 'Wyłączony typ zostaje przy jednostce.');
    }

    public function test_support_sees_types_but_cannot_change_them(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');
        $type = OrganizationUnitType::query()->firstOrFail();
        $this->get('/admiro/unit-types')->assertOk()->assertDontSee(text('admiro::admin.unit_types.add'));

        $this->post('/admiro/unit-types/' . $type->token . '/toggle')->assertForbidden();
    }
}
