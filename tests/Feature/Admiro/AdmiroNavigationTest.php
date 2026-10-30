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
    public function test_workspace_has_no_side_menu_and_the_sitemap_lists_every_page(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringNotContainsString('<aside class="ss-sidebar"', $html);
        $this->assertStringNotContainsString('<header class="ss-topbar">', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-backdrop" aria-hidden="true">\s*<svg class="ss-backdrop__defs".*?<filter id="ss-backdrop-deboss".*?</svg>\s*<span class="ss-logo ss-backdrop__logo">~s', $html);
        $this->assertStringNotContainsString('aria-controls="ss-sidebar"', $html);
        $this->assertStringNotContainsString('data-ss-sitemap-toggle', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . $this->zoneUrl('/admiro/onboarding') . '"', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . $this->zoneUrl('/admiro/audit') . '"', $html);

        foreach (['zones', 'organizations', 'system', 'application', 'dictionaries'] as $group) {
            $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('admiro::navigation.groups.' . $group) . '</h2>', $html);
        }

        $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('account.settings_group') . '</h2>', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . $this->zoneUrl('/admiro/dictionaries/countries') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-icon-button ss-sidebar__settings-toggle"', $html);
        $this->assertMatchesRegularExpression('~id="ss-context-switch".*?' . preg_quote(text('roles.admiro.owner'), '~') . '~s', $html);
        $this->assertStringContainsString('class="ss-card ss-card--feature ss-onboarding-card"', $html);
    }

    public function test_workspace_footer_has_the_account_menu_tools_and_the_search(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/dictionaries/countries')->assertOk()->getContent();

        $this->assertStringContainsString('class="ss-shell ss-footer-stage ss-shell--workspace"', $html);
        $this->assertMatchesRegularExpression('~<a class="ss-link ss-workspace-footer__link" href="' . preg_quote($this->zoneUrl('/admiro/dictionaries/countries'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringNotContainsString('<div class="ss-workspace-notch"', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__bar">\s*<details class="ss-account-menu ss-workspace-footer__account" data-ss-dropdown>\s*<summary class="ss-workspace-footer__account-toggle">.*?<span class="ss-workspace-footer__account-name">owner@example.com</span>~s', $html);
        $this->assertStringNotContainsString('<aside class="ss-sidebar"', (string) $this->get('/admiro/settings')->getContent());
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__tools">\s*<details class="ss-account-menu ss-context-switch" id="ss-context-switch" data-ss-dropdown>.*?' . preg_quote(text('roles.admiro.owner'), '~') . '.*?<details class="ss-calendar-panel" data-ss-dropdown>.*?<details class="ss-side-panel__section" open>.*?' . preg_quote(text('layout.notifications'), '~') . '.*?<details class="ss-side-panel__section" open>~s', $html);
        $this->assertStringNotContainsString('ss-notifications__toggle" ', $html);
        $this->assertMatchesRegularExpression('~<details class="ss-account-menu ss-workspace-footer__account" data-ss-dropdown>.*?' . preg_quote($this->zoneUrl('/admiro/account/security'), '~') . '.*?' . preg_quote($this->zoneUrl('/admiro/settings'), '~') . '.*?href="#ss-sitemap">.*?' . preg_quote(text('layout.sitemap_open'), '~') . '.*?<form class="ss-workspace-footer__sign-out"~s', $html);
        $this->assertStringContainsString('<details class="ss-calendar-panel" data-ss-dropdown>', $html);
        $this->assertStringContainsString('<summary class="ss-calendar-panel__toggle" data-ss-clock>', $html);
        $this->assertStringNotContainsString('data-ss-clock-week', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-calendar-panel__time" data-ss-clock-time>\d{2}:\d{2}</span><span class="ss-calendar-panel__day" data-ss-clock-day>\d{2}\.\d{2}\.\d{4}</span>~', $html);
        $this->assertStringContainsString(text('layout.mailbox_connect'), $html);
        $this->assertStringNotContainsString('ss-account-card', $html);
        $this->assertStringNotContainsString('ss-workspace-footer__wordmark', $html);
        $this->assertStringContainsString('<a class="ss-account-menu__link" href="' . $this->zoneUrl('/admiro/account/profile') . '"', $html);
        $this->assertStringNotContainsString('data-ss-clock-week', $html);
        $this->assertMatchesRegularExpression('~<button class="ss-search__send" type="submit">\s*<span class="ss-icon ss-search__send-icon">~', $html);
        $this->assertStringContainsString('<form class="ss-search ss-search--compose"', $html);
        $this->assertStringContainsString('href="' . $this->zoneUrl('/admiro/organizations/create') . '"><span class="ss-icon ss-account-menu__icon">', $html);
        $this->assertStringContainsString('<button class="ss-search__send" type="submit">', $html);
        $this->assertStringNotContainsString('<kbd class="ss-search__kbd"', $html);
        $this->assertStringNotContainsString('<form class="ss-sidebar__sign-out"', $html);
        $this->assertStringContainsString('<footer class="ss-workspace-footer">', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__search">\s*<form class="ss-search ss-search--compose"~', $html);
        $this->assertStringNotContainsString('<header class="ss-topbar">' . "\n" . '<form', $html);
        $this->assertStringNotContainsString('ss-workspace-footer__version', $html);
        $this->assertStringContainsString('<nav class="ss-workspace-footer__sitemap" id="ss-sitemap"', $html);
        $this->assertStringNotContainsString('<div class="ss-theme-toggle"', $html);
        $this->assertStringNotContainsString('<button class="ss-sidebar__brand-toggle"', $html);
        $this->assertSame(1, substr_count($html, 'id="ss-global-search"'));
    }

    public function test_page_header_takes_the_icon_of_the_current_menu_or_sitemap_item(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $building = (string) file_get_contents($this->packagePath('simplysmart/admiro', 'public/assets/img/icons/building.svg'));

        $html = (string) $this->get('/admiro/organizations')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-context__heading">\s*<span class="ss-icon ss-context__icon">~', $html);
        $firstPath = trim(explode("\n", $building)[1] ?? '');
        $this->assertNotSame('', $firstPath);
        $this->assertStringContainsString($firstPath, substr($html, (int) strpos($html, '<span class="ss-icon ss-context__icon">'), 1000));
    }

    public function test_page_header_links_back_to_the_parent_page_in_the_hierarchy_above_the_title(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $html = (string) $this->from($this->zoneUrl('/admiro/organizations'))->get('/admiro/organizations/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-context__text">\s*<a class="ss-context__back ss-link" href="' . preg_quote($this->zoneUrl('/admiro/organizations'), '~') . '">.*?</a>\s*<h1 class="ss-context__title">~s', $html);
        $this->assertStringNotContainsString('<p class="ss-context__meta">', $html);

        $this->from($this->zoneUrl('/admiro/onboarding/languages'))->get('/admiro/onboarding')
            ->assertSee('<a class="ss-context__back ss-link" href="' . $this->zoneUrl('/admiro') . '">', false);
        $this->from($this->zoneUrl('/admiro/audit'))->get('/admiro/onboarding/settings')
            ->assertSee('<a class="ss-context__back ss-link" href="' . $this->zoneUrl('/admiro/onboarding') . '">', false);
        $this->from($this->zoneUrl('/admiro/audit'))->get('/admiro/dictionaries/countries')
            ->assertSee('<a class="ss-context__back ss-link" href="' . $this->zoneUrl('/admiro') . '">', false);
        $this->from($this->zoneUrl('/admiro/audit'))->get('/admiro')->assertOk()->assertDontSee('ss-context__back ss-link', false);
    }

    public function test_side_panel_ends_with_the_appearance_tools_and_a_gear_to_the_settings(): void
    {
        $html = (string) $this->signedIn($this->admin('guest@example.com'), 'admiro')->get('/admiro/favorites')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-side-panel__tools" role="group" aria-label="' . preg_quote(text('layout.appearance_tools'), '~') . '">.*?' . preg_quote(text('layout.settings'), '~') . '.*?<a class="ss-icon-button ss-side-panel__tool ss-side-panel__settings" href="' . preg_quote($this->zoneUrl('/admiro/account/profile'), '~') . '".*?' . preg_quote(text('layout.color_modes'), '~') . '.*?data-ss-theme-flip>.*?data-ss-theme="contrast">.*?data-ss-theme="contrast-yellow">.*?' . preg_quote(text('layout.resize_zoom'), '~') . '.*?data-ss-preference-value="normal">A<.*?data-ss-preference-value="large">AA<.*?data-ss-preference-step="-1">.*?data-ss-preference-step="1">~s', $html);
        $this->assertStringContainsString('class="ss-side-panel__columns ss-side-panel__columns--two"', $html);
        $this->assertStringNotContainsString('class="ss-account-menu ss-quick-settings"', $html);
        $this->assertStringContainsString(text('admiro::admin.favorites_empty'), $html);
        $this->get('/admiro/board')->assertOk()->assertSee(text('admiro::admin.board_empty'));
    }

    public function test_notifications_panel_switches_between_notifications_mailbox_and_the_latest_audit_entries(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/organizations')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="ss-tabs ss-tabs--slider ss-notifications__tabs" role="tablist" aria-label="' . text('layout.notifications') . '" hidden data-ss-tabs-list>', $html);
        $this->assertSame(4, substr_count($html, 'data-ss-tab>'), 'Notifications, system messages, mailbox and audit log.');
        $this->assertStringContainsString('id="ss-notifications-system" role="tabpanel"', $html);
        $this->assertStringContainsString('id="ss-notifications-mailbox" role="tabpanel"', $html);
        $this->assertMatchesRegularExpression('~id="ss-notifications-audit" role="tabpanel".*?<a class="ss-notifications__all ss-link" href="' . preg_quote($this->zoneUrl('/admiro/audit'), '~') . '">~s', $html);

        $guest = (string) $this->signedIn($this->admin('guest@example.com', 'guest'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="ss-notifications-audit"', $guest);
    }

    public function test_support_sees_only_what_its_permissions_allow(): void
    {
        $html = (string) $this->signedIn($this->admin('support@example.com', 'support'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . $this->zoneUrl('/admiro/zones') . '"', $html);
        $this->assertStringNotContainsString('href="' . $this->zoneUrl('/admiro/administrators') . '"', $html);
        $this->assertStringNotContainsString('href="' . $this->zoneUrl('/admiro/owners/create') . '"', $html);
    }

    public function test_panel_views_use_design_system_cards_and_tables(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');

        $this->get('/admiro/zones')->assertOk()->assertSee('<table class="ss-table">', false);
        $this->get('/admiro/organizations')->assertOk()->assertSee('class="ss-card ss-card--flush"', false);
        $this->get('/admiro/settings')->assertOk()->assertSee('class="ss-tabs"', false)->assertSee('role="switch"', false);
    }
}
