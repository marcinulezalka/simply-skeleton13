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
    public function test_sidebar_keeps_the_system_group_and_the_sitemap_lists_every_page(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro')->assertOk()->getContent();

        $this->assertStringContainsString('<a class="ss-sidebar__link" href="' . url('/admiro') . '"', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/onboarding') . '"', $html);
        $this->assertStringContainsString('<p class="ss-sidebar__group">' . text('admiro::navigation.groups.system') . '</p>', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link" href="#ss-sitemap" title="' . text('admiro::navigation.sitemap') . '"', $html);
        $this->assertStringNotContainsString('data-ss-sitemap-toggle', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/audit') . '"', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/settings') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-sidebar__link" href="' . url('/admiro/zones') . '"', $html);

        foreach (['zones', 'organizations', 'system', 'application', 'dictionaries'] as $group) {
            $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('admiro::navigation.groups.' . $group) . '</h2>', $html);
        }

        $this->assertStringContainsString('<h2 class="ss-workspace-footer__heading">' . text('account.settings_group') . '</h2>', $html);
        $this->assertStringContainsString('<a class="ss-link ss-workspace-footer__link" href="' . url('/admiro/dictionaries/countries') . '"', $html);
        $this->assertStringNotContainsString('<a class="ss-icon-button ss-sidebar__settings-toggle"', $html);
        $this->assertStringNotContainsString(text('roles.admiro.owner'), $html);
        $this->assertStringContainsString('class="ss-card ss-card--link"', $html);
    }

    public function test_workspace_layout_has_expanding_actions_a_drawer_and_a_sticky_footer_with_the_search(): void
    {
        $html = (string) $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')->get('/admiro/dictionaries/countries')->assertOk()->getContent();

        $this->assertStringContainsString('class="ss-shell ss-footer-stage ss-shell--workspace"', $html);
        $this->assertMatchesRegularExpression('~<a class="ss-link ss-workspace-footer__link" href="' . preg_quote(url('/admiro/dictionaries/countries'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringNotContainsString('<div class="ss-workspace-notch"', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-account-card__first-name">owner@example.com</span>\s*</p>\s*<form class="ss-account-card__sign-out"~', $html);
        $this->assertMatchesRegularExpression('~<ul class="ss-account-panel__list">\s*<li>\s*<button class="ss-icon-button ss-icon-button--expand ss-sidebar__collapse"~', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link ss-sidebar__back"', (string) $this->get('/admiro/settings')->getContent());
        $this->assertMatchesRegularExpression('~<nav class="ss-account-panel"[^>]*>\s*<ul class="ss-account-panel__list">~', $html);
        $this->assertMatchesRegularExpression('~<details class="ss-account-menu ss-notifications ss-notifications--drawer" data-ss-dropdown>\s*<summary class="ss-icon-button ss-icon-button--expand ss-notifications__toggle"\s*>~', $html);
        $this->assertStringContainsString('<span class="ss-icon-button__label">' . text('layout.notifications') . '</span>', $html);
        $this->assertStringContainsString('<details class="ss-calendar-panel" data-ss-dropdown>', $html);
        $this->assertStringContainsString('<summary class="ss-calendar-panel__toggle" data-ss-clock>', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-calendar-panel__chip">.*?<span data-ss-clock-weekday>[^<]+</span><span class="ss-calendar-panel__week">, <span data-ss-clock-week>[^<]+</span></span></span>\s*</span>\s*<span class="ss-calendar-panel__date" data-ss-clock-date>[^<]+</span>~s', $html);
        $this->assertStringNotContainsString('data-ss-clock-time', $html);
        $this->assertStringContainsString(text('layout.mailbox_connect'), $html);
        $this->assertMatchesRegularExpression('~<section class="ss-account-card" aria-label="[^"]+">~', $html);
        $this->assertStringNotContainsString('<span class="ss-account-card__presence"', $html);
        $this->assertMatchesRegularExpression('~<form class="ss-account-card__sign-out"[^>]*>.*?<button class="ss-icon-button ss-icon-button--expand" type="submit">.*?<span class="ss-icon-button__label">' . preg_quote(text('sign_in.sign_out'), '~') . '</span>~s', $html);
        $this->assertStringContainsString('<p class="ss-workspace-footer__wordmark" aria-hidden="true">', $html);
        $this->assertStringContainsString('<a class="ss-icon-button ss-icon-button--expand" href="' . url('/admiro/account/profile') . '">', $html);
        $this->assertStringContainsString('<span data-ss-clock-week>' . text('layout.calendar_week', ['week' => (string) now()->isoWeek()]) . '</span>', $html);
        $this->assertMatchesRegularExpression('~<button class="ss-search__send" type="submit">\s*<span class="ss-icon ss-search__send-icon">~', $html);
        $this->assertStringNotContainsString('<ul class="ss-account-panel__list ss-account-panel__social">', $html);
        $this->assertStringContainsString('<form class="ss-search ss-search--compose"', $html);
        $this->assertStringContainsString('href="' . url('/admiro/organizations/create') . '"><span class="ss-icon ss-account-menu__icon">', $html);
        $this->assertStringContainsString('<button class="ss-search__send" type="submit">', $html);
        $this->assertStringNotContainsString('<kbd class="ss-search__kbd"', $html);
        $this->assertStringNotContainsString('<form class="ss-sidebar__sign-out"', $html);
        $this->assertStringContainsString('ss-notifications--drawer', $html);
        $this->assertStringContainsString('<footer class="ss-workspace-footer">', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-workspace-footer__search">\s*<form class="ss-search ss-search--compose"~', $html);
        $this->assertStringNotContainsString('<header class="ss-topbar">' . "\n" . '<form', $html);
        $this->assertStringContainsString('<span class="ss-badge ss-workspace-footer__version">' . text('welcome.admiro_version', ['version' => \Simplysmart\Admiro\View\CoreVersion::current()]) . '</span>', $html);
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
