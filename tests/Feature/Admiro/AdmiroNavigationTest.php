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

/**
 * Menu strefy administratorów: pozycje tylko z uprawnieniem (puste grupy znikają), role administratora pod nazwą konta,
 * widoki panelu na design systemie (pulpit z kartami-skrótami, tabele w kartach).
 */
class AdmiroNavigationTest extends AdmiroTestCase
{
    public function test_owner_sees_every_group_and_its_roles_under_the_name(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro')->assertOk()->getContent();

        foreach (['zones', 'organizations', 'system'] as $group) {
            $this->assertStringContainsString(text('admiro::navigation.groups.' . $group), $html);
        }

        $this->assertStringContainsString('href="' . url('/admiro/audit') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/dictionaries/countries') . '"', $html);
        $this->assertStringContainsString('<a class="ss-icon-button ss-sidebar__settings-toggle" href="' . url('/admiro/settings') . '" title="' . text('layout.settings') . '"', $html);
        $this->assertStringContainsString(text('roles.admiro.owner'), $html);
        $this->assertStringContainsString('class="ss-card ss-card--link"', $html);
    }

    public function test_settings_pages_switch_the_menu_to_the_settings_groups_with_a_way_back(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/dictionaries/countries')->assertOk()->getContent();

        foreach (['application', 'dictionaries'] as $group) {
            $this->assertStringContainsString('<p class="ss-sidebar__group">' . text('admiro::navigation.groups.' . $group) . '</p>', $html);
        }

        $this->assertMatchesRegularExpression('~href="' . preg_quote(url('/admiro/dictionaries/countries'), '~') . '" title="[^"]+"\s+aria-current="page"~', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link ss-sidebar__back" href="' . url('/admiro') . '"', $html);
        $this->assertStringContainsString('href="' . url('/admiro/account/appearance') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/audit') . '"', $html);
    }

    public function test_support_sees_only_what_its_permissions_allow(): void
    {
        $html = (string) $this->signedIn($this->admin('support@example.com', 'support'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . url('/admiro/zones') . '"', $html);
        $this->assertStringNotContainsString('href="' . url('/admiro/administrators') . '"', $html);
        $this->assertStringNotContainsString('href="' . url('/admiro/owners/create') . '"', $html);
    }

    public function test_panel_views_use_design_system_cards_and_tables(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/zones')->assertOk()->assertSee('<table class="ss-table">', false);
        $this->get('/admiro/organizations')->assertOk()->assertSee('class="ss-card ss-card--flush"', false);
        $this->get('/admiro/settings')->assertOk()->assertSee('class="ss-tabs"', false)->assertSee('role="switch"', false);
    }
}
