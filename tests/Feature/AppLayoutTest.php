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

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Simplysmart\Mainboard\Enums\InputMask;
use Simplysmart\Mainboard\Enums\ToastType;
use Simplysmart\Mainboard\View\Navigation\NavigationGroup;
use Simplysmart\Mainboard\View\Navigation\NavigationItem;
use Simplysmart\Mainboard\View\Navigation\SearchBox;
use Simplysmart\Mainboard\View\Notifications\NotificationItem;
use Simplysmart\Mainboard\View\Notifications\NotificationPanel;
use Simplysmart\Mainboard\View\Initials;
use Simplysmart\Mainboard\View\Toast;
use Tests\TestCase;

/**
 * Layout aplikacji z menu bocznym (x-mainboard::app.layout): menu z bieżącą pozycją i licznikiem, przycisk
 * zwijania powiązany z menu, globalna wyszukiwarka (formularz GET, skrót „/”), menu konta pod awatarem, skrypt z nonce
 * CSP, brak indeksowania; przełącznik x-mainboard::switch.
 */
class AppLayoutTest extends TestCase
{
    public function test_renders_sidebar_menu_search_and_shell_script(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Zamówienia" :navigation="$navigation" :search="$search" :account="$account">treść</x-mainboard::app.layout>', [
            'navigation' => [
                new NavigationGroup(null, [
                    new NavigationItem('Pulpit', '/pulpit', 'home'),
                    new NavigationItem('Zamówienia', '/zamowienia', 'calendar', current: true, badge: '3'),
                ]),
                new NavigationGroup('Sklep', [new NavigationItem('Zespół', '/zespol', 'users')]),
            ],
            'search' => new SearchBox('/szukaj', 'Numer zamówienia'),
            'account' => ['name' => 'Anna Nowak', 'subtitle' => 'Sklep · Kierownik'],
        ]);

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('aria-controls="ss-sidebar" aria-expanded="true" data-ss-sidebar-toggle', $html);
        $this->assertMatchesRegularExpression('~href="/zamowienia" title="Zamówienia"\s+aria-current="page"~', $html);
        $this->assertStringContainsString('<span class="ss-sidebar__badge">3</span>', $html);
        $this->assertStringContainsString('<p class="ss-sidebar__group">Sklep</p>', $html);
        $this->assertStringContainsString('<form class="ss-search" role="search" method="GET" action="/szukaj">', $html);
        $this->assertStringContainsString('name="q" type="search"', $html);
        $this->assertStringContainsString(text('layout.search_shortcut'), $html);
        $this->assertStringContainsString("const STORAGE_KEY = 'ss-sidebar-collapsed';", $html);
        $this->assertStringContainsString('<h1 class="ss-context__title">Zamówienia</h1>', $html);
        $this->assertStringContainsString('treść', $html);
    }

    public function test_account_items_open_from_the_avatar_menu(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Profil" :account="$account">x</x-mainboard::app.layout>', [
            'account' => ['name' => 'Anna Nowak', 'subtitle' => 'Sklep · Kierownik', 'items' => [
                new NavigationItem('Profil', '/profil', 'users', current: true),
                new NavigationItem('Wyloguj', '/wyloguj', 'log-out'),
            ]],
        ]);

        $this->assertStringContainsString('<details class="ss-account-menu ss-sidebar__account" data-ss-dropdown>', $html);
        $this->assertStringContainsString('<span class="ss-sidebar__identity-name">Anna Nowak</span>', $html);
        $this->assertStringContainsString('<span class="ss-sidebar__identity-subtitle">Sklep · Kierownik</span>', $html);
        $this->assertMatchesRegularExpression('~href="/profil"\s+aria-current="page"~', $html);
        $this->assertStringContainsString('href="/wyloguj"', $html);
    }

    public function test_bell_shows_the_unread_count_and_the_panel(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Pulpit" :notifications="$panel">x</x-mainboard::app.layout>', [
            'panel' => new NotificationPanel([
                new NotificationItem('Nowe zamówienie', '08:41', 'Zamówienie 1001', '/zamowienia/1001', true),
                new NotificationItem('Eksport gotowy', 'wczoraj', unread: false),
            ], '/powiadomienia'),
        ]);

        $this->assertStringContainsString('<details class="ss-account-menu ss-notifications" data-ss-dropdown>', $html);
        $this->assertStringContainsString('<span class="ss-notifications__count" aria-hidden="true">1</span>', $html);
        $this->assertStringContainsString(text('layout.notifications_unread', ['count' => 1]), $html);
        $this->assertStringContainsString('class="ss-notification ss-notification--unread"  href="/zamowienia/1001"', $html);
        $this->assertStringContainsString('<a class="ss-notifications__all ss-link" href="/powiadomienia">', $html);
    }

    public function test_toasts_from_the_session_are_shown_with_their_type_and_role(): void
    {
        session()->flash(Toast::SESSION_KEY, [
            (new Toast(ToastType::Success, 'Zapisano.', actionLabel: 'Zobacz', actionUrl: '/zamowienia'))->toArray(),
            (new Toast(ToastType::Danger, 'Nie zapisano.', 'Błąd zapisu'))->toArray(),
            ['type' => 'unknown', 'message' => 'pominięty'],
        ]);

        $html = Blade::render('<x-mainboard::app.layout title="Zamówienia">x</x-mainboard::app.layout>');

        $this->assertMatchesRegularExpression('~class="ss-toast ss-toast--success" role="status" data-ss-toast\s+data-ss-toast-auto~', $html);
        $this->assertMatchesRegularExpression('~class="ss-toast ss-toast--danger" role="alert" data-ss-toast\s+>~', $html);
        $this->assertStringContainsString('<span class="ss-sr-only">' . ToastType::Danger->label() . ': </span>Błąd zapisu', $html);
        $this->assertStringContainsString('<a class="ss-toast__action ss-link" href="/zamowienia">Zobacz</a>', $html);
        $this->assertStringNotContainsString('pominięty', $html);
        $this->assertStringContainsString('DISMISS_AFTER', $html);
    }

    public function test_only_errors_stay_and_interrupt(): void
    {
        foreach (ToastType::cases() as $type) {
            $this->assertSame($type !== ToastType::Danger, $type->dismissesItself());
            $this->assertSame($type === ToastType::Danger ? 'alert' : 'status', $type->role());
        }
    }

    public function test_switch_is_a_labelled_checkbox_with_its_hint(): void
    {
        $html = Blade::render('<x-mainboard::switch id="reminders" label="Przypomnienia" hint="Dzień przed terminem." :checked="true" name="reminders" />');

        $this->assertStringContainsString('<label class="ss-switch" for="reminders">', $html);
        $this->assertMatchesRegularExpression('~<input type="checkbox" role="switch" id="reminders" name="reminders" checked\s+aria-describedby="reminders-hint"\s*>~', $html);
        $this->assertStringContainsString('<span class="ss-switch__hint" id="reminders-hint">Dzień przed terminem.</span>', $html);
    }

    public function test_sidebar_starts_with_identity_and_actions_and_ends_with_a_subtle_logo(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Pulpit" :account="$account" :notifications="$panel">x</x-mainboard::app.layout>', [
            'account' => ['name' => 'dr Anna Nowak', 'subtitle' => 'Sklep'],
            'panel' => new NotificationPanel([]),
        ]);
        $sidebar = substr($html, (int) strpos($html, '<aside class="ss-sidebar"'), (int) strpos($html, '</aside>') - (int) strpos($html, '<aside class="ss-sidebar"'));

        $this->assertLessThan(strpos($sidebar, 'ss-sidebar__actions'), strpos($sidebar, 'ss-sidebar__identity'));
        $this->assertLessThan(strpos($sidebar, 'ss-notifications__toggle'), strpos($sidebar, 'ss-sidebar__collapse'));
        $this->assertLessThan(strpos($sidebar, 'ss-sidebar__brand'), strpos($sidebar, 'ss-sidebar__nav'));
        $this->assertMatchesRegularExpression('~class="ss-avatar ss-avatar--md ss-avatar--accent"\s+aria-hidden="true"\s*>\s*AN\s*</span>~', $sidebar);
        $this->assertStringContainsString('class="ss-icon-button ss-topbar__menu" type="button" aria-controls="ss-sidebar" aria-expanded="false"', $html);
        $this->assertStringContainsString('const SLOTS', $html);
    }

    public function test_context_switch_sits_between_collapse_and_bell_and_marks_the_current_context(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Pulpit" :contexts="$contexts" :notifications="$panel">x</x-mainboard::app.layout>', [
            'contexts' => [new NavigationGroup('Sklep', [
                new NavigationItem('Sklep Centrum', '/centrum', 'building', current: true),
                new NavigationItem('Sklep Północ', '/polnoc', 'building'),
            ])],
            'panel' => new NotificationPanel([]),
        ]);

        $collapse = strpos($html, 'class="ss-icon-button ss-sidebar__collapse"');
        $switch = strpos($html, 'class="ss-account-menu ss-context-switch"');
        $bell = strpos($html, 'class="ss-icon-button ss-notifications__toggle"');

        $this->assertNotFalse($collapse);
        $this->assertLessThan($switch, $collapse);
        $this->assertLessThan($bell, $switch);
        $this->assertStringContainsString('<p class="ss-context-switch__group">Sklep</p>', $html);
        $this->assertMatchesRegularExpression('~href="/centrum"\s+aria-current="true"~', $html);
        $this->assertStringContainsString('Sklep Centrum<span class="ss-sr-only"> (' . text('layout.current') . ')</span>', $html);
        $this->assertDoesNotMatchRegularExpression('~href="/polnoc"\s+aria-current~', $html);
    }

    public function test_initials_skip_lowercase_titles(): void
    {
        $this->assertSame('AW', Initials::of('dr Anna Wiśniewska'));
        $this->assertSame('ŁŻ', Initials::of('Łukasz Żak'));
        $this->assertSame('KS', Initials::of('Kowalski Sp. z o.o.'));
        $this->assertSame('JA', Initials::of('jan'));
    }

    public function test_input_shows_an_icon_mask_format_and_error(): void
    {
        $html = Blade::render('<x-mainboard::input id="tax" label="NIP" :mask="$mask" name="tax_id" error="Niepoprawny NIP." />', ['mask' => InputMask::TaxId]);

        $this->assertStringContainsString('<div class="ss-input-wrap ss-input-wrap--icon">', $html);
        $this->assertStringContainsString('class="ss-icon ss-input-wrap__icon"', $html);
        $this->assertStringContainsString('inputmode="numeric" placeholder="' . InputMask::TaxId->example() . '" maxlength="13" data-ss-mask="999-999-99-99" aria-invalid="true" aria-describedby="tax-hint tax-error"', $html);
        $this->assertStringContainsString('<p class="ss-field__hint" id="tax-hint">' . text('layout.mask_format', ['format' => '999-999-99-99']) . '</p>', $html);
        $this->assertStringContainsString('<p class="ss-field__error" id="tax-error" role="alert">Niepoprawny NIP.</p>', $html);
    }

    public function test_input_without_icon_or_mask_is_a_plain_field(): void
    {
        $html = Blade::render('<x-mainboard::input id="name" label="Nazwa" name="name" value="Sklep" />');

        $this->assertStringContainsString('<div class="ss-input-wrap">', $html);
        $this->assertStringNotContainsString('data-ss-mask', $html);
        $this->assertStringContainsString('name="name" value="Sklep"', $html);
    }

    public function test_layout_without_search_and_account_has_no_search_form(): void
    {
        $html = Blade::render('<x-mainboard::app.layout title="Pulpit">x</x-mainboard::app.layout>');

        $this->assertStringNotContainsString('role="search"', $html);
        $this->assertStringNotContainsString('class="ss-avatar"', $html);
    }
}
