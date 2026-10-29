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
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . url('/admiro/onboarding') . '"', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . url('/admiro/audit') . '"', $html);

        foreach (['zones', 'organizations', 'system', 'application', 'dictionaries'] as $group) {
            $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('admiro::navigation.groups.' . $group) . '</h2>', $html);
        }

        $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('account.settings_group') . '</h2>', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . url('/admiro/dictionaries/countries') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-icon-button ss-sidebar__settings-toggle"', $html);
        $this->assertStringNotContainsString(text('roles.admiro.owner'), $html);
        $this->assertStringContainsString('class="ss-card ss-card--feature ss-onboarding-card"', $html);
    }

    public function test_workspace_footer_has_the_account_menu_tools_and_the_search(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/dictionaries/countries')->assertOk()->getContent();

        $this->assertStringContainsString('class="ss-shell ss-footer-stage ss-shell--workspace"', $html);
        $this->assertMatchesRegularExpression('~<a class="ss-link ss-workspace-footer__link" href="' . preg_quote(url('/admiro/dictionaries/countries'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringNotContainsString('<div class="ss-workspace-notch"', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__bar">\s*<details class="ss-account-menu ss-workspace-footer__account" data-ss-dropdown>\s*<summary class="ss-workspace-footer__account-toggle">.*?<span class="ss-workspace-footer__account-name">owner@example.com</span>~s', $html);
        $this->assertStringNotContainsString('<aside class="ss-sidebar"', (string) $this->get('/admiro/settings')->getContent());
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__tools">\s*<details class="ss-account-menu ss-quick-settings" data-ss-dropdown>.*?</details>\s*<details class="ss-account-menu ss-notifications ss-notifications--right" data-ss-dropdown>\s*<summary class="ss-icon-button ss-workspace-footer__tool ss-notifications__toggle"\s*title="' . preg_quote(text('layout.notifications'), '~') . '"\s*>.*?</details>\s*<details class="ss-calendar-panel"~s', $html);
        $this->assertMatchesRegularExpression('~<details class="ss-account-menu ss-workspace-footer__account" data-ss-dropdown>.*?' . preg_quote(url('/admiro/account/security'), '~') . '.*?' . preg_quote(url('/admiro/settings'), '~') . '.*?href="#ss-sitemap">.*?' . preg_quote(text('layout.sitemap_open'), '~') . '.*?<form class="ss-workspace-footer__sign-out"~s', $html);
        $this->assertStringContainsString('<details class="ss-calendar-panel" data-ss-dropdown>', $html);
        $this->assertStringContainsString('<summary class="ss-calendar-panel__toggle" data-ss-clock>', $html);
        $this->assertStringNotContainsString('data-ss-clock-week', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-calendar-panel__time" data-ss-clock-time>\d{2}:\d{2}</span><span class="ss-calendar-panel__day" data-ss-clock-day>\d{2}\.\d{2}\.\d{4}</span>~', $html);
        $this->assertStringContainsString(text('layout.mailbox_connect'), $html);
        $this->assertStringNotContainsString('ss-account-card', $html);
        $this->assertStringNotContainsString('ss-workspace-footer__wordmark', $html);
        $this->assertStringContainsString('<a class="ss-account-menu__link" href="' . url('/admiro/account/profile') . '"', $html);
        $this->assertStringNotContainsString('data-ss-clock-week', $html);
        $this->assertMatchesRegularExpression('~<button class="ss-search__send" type="submit">\s*<span class="ss-icon ss-search__send-icon">~', $html);
        $this->assertStringContainsString('<form class="ss-search ss-search--compose"', $html);
        $this->assertStringContainsString('href="' . url('/admiro/organizations/create') . '"><span class="ss-icon ss-account-menu__icon">', $html);
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

        $html = (string) $this->from('/admiro/organizations')->get('/admiro/organizations/create')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-context__text">\s*<a class="ss-context__back ss-link" href="' . preg_quote(url('/admiro/organizations'), '~') . '">.*?</a>\s*<h1 class="ss-context__title">~s', $html);
        $this->assertStringNotContainsString('<p class="ss-context__meta">', $html);

        $this->from('/admiro/onboarding/languages')->get('/admiro/onboarding')
            ->assertSee('<a class="ss-context__back ss-link" href="' . url('/admiro') . '">', false);
        $this->from('/admiro/audit')->get('/admiro/onboarding/settings')
            ->assertSee('<a class="ss-context__back ss-link" href="' . url('/admiro/onboarding') . '">', false);
        $this->from('/admiro/audit')->get('/admiro/dictionaries/countries')
            ->assertSee('<a class="ss-context__back ss-link" href="' . url('/admiro') . '">', false);
        $this->from('/admiro/audit')->get('/admiro')->assertOk()->assertDontSee('ss-context__back ss-link', false);
    }

    public function test_quick_settings_tiles_hold_the_dashboard_favorites_board_sitemap_and_dark_mode(): void
    {
        $html = (string) $this->signedIn($this->admin('guest@example.com'), 'admiro')->get('/admiro/favorites')->assertOk()->getContent();
        $tile = static fn(string $href, string $label, string $current = ''): string => '~<a class="ss-quick-settings__tile" href="' . preg_quote($href, '~') . '"\s*' . $current . '>.*?<span class="ss-quick-settings__label">' . preg_quote($label, '~') . '</span>~s';

        $this->assertMatchesRegularExpression('~<summary class="ss-icon-button ss-workspace-footer__tool ss-quick-settings__toggle" title="' . preg_quote(text('layout.quick_settings'), '~') . '">~', $html);
        $this->assertMatchesRegularExpression($tile(url('/admiro'), text('admiro::navigation.status')), $html);
        $this->assertMatchesRegularExpression($tile(url('/admiro/favorites'), text('admiro::navigation.favorites'), 'aria-current="page"\s*'), $html);
        $this->assertMatchesRegularExpression($tile('#ss-sitemap', text('layout.sitemap_open')), $html);
        $this->assertSame(1, substr_count($html, 'class="ss-quick-settings__tile" href="' . url('/admiro') . '"'), 'The dashboard shortcut replaces the default dashboard tile.');
        $this->assertStringContainsString('<li data-ss-theme-flip-control hidden>', $html);
        $this->assertStringContainsString('<button class="ss-quick-settings__tile" type="button" aria-pressed="false" data-ss-theme-flip>', $html);
        $this->assertStringContainsString(text('admiro::admin.favorites_empty'), $html);
        $this->get('/admiro/board')->assertOk()->assertSee(text('admiro::admin.board_empty'));
    }

    public function test_notifications_panel_switches_between_notifications_mailbox_and_the_latest_audit_entries(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/organizations')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="ss-tabs ss-tabs--slider ss-notifications__tabs" role="tablist" aria-label="' . text('layout.notifications') . '" hidden data-ss-tabs-list>', $html);
        $this->assertSame(3, substr_count($html, 'data-ss-tab>'));
        $this->assertStringContainsString('id="ss-notifications-mailbox" role="tabpanel"', $html);
        $this->assertMatchesRegularExpression('~id="ss-notifications-audit" role="tabpanel".*?<a class="ss-notifications__all ss-link" href="' . preg_quote(url('/admiro/audit'), '~') . '">~s', $html);

        $guest = (string) $this->signedIn($this->admin('guest@example.com', 'guest'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="ss-notifications-audit"', $guest);
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
