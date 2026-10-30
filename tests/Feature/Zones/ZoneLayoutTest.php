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

use Illuminate\Support\Facades\Blade;
use InvalidArgumentException;
use Simplysmart\Admiro\Enums\AppBackdrop;
use Simplysmart\Admiro\Enums\AppearancePreference;
use Simplysmart\Admiro\Enums\AppLayout;
use Simplysmart\Admiro\Enums\FaviconType;
use Simplysmart\Admiro\Enums\Palette;
use Simplysmart\Admiro\Enums\SocialNetwork;
use Simplysmart\Admiro\Queries\InstalledPackages;
use Simplysmart\Admiro\View\AppearanceChoices;
use Simplysmart\Admiro\View\CoreAssets;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Simplysmart\Admiro\Zones\ZoneFontStyle;
use Simplysmart\Admiro\Zones\ZoneNavigations;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\ShopNavigation;
use Tests\Fixtures\Zones\WorkspaceShopNavigation;
use Tests\Fixtures\Zones\ColumnsShopNavigation;
use Tests\Fixtures\Zones\ZoneShopNavigation;

/**
 * Layout strefy na layoucie aplikacji: menu, ustawienia i wyszukiwarka z ZoneNavigation zarejestrowanego dla strefy
 * (strony konta to ustawienia: menu ustawień z powrotem do aplikacji), awatar w kolorze konta, druga linia, menu konta
 * (profil, bezpieczeństwo, wygląd) z wylogowaniem formularzem; strefa bez menu ma same ustawienia konta.
 */
class ZoneLayoutTest extends ZoneTestCase
{
    public function test_account_pages_show_the_settings_menu_with_the_zone_settings_first(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ShopNavigation::class);
        $account = $this->account(AlphaAccount::class);

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringNotContainsString('href="/zamowienia"', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link ss-sidebar__back" href="' . url('/alpha/dashboard') . '" title="' . text('layout.back_to_app') . '">', $html);
        $this->assertStringContainsString('<p class="ss-sidebar__group">Sklep</p>', $html);
        $this->assertStringContainsString('<a class="ss-sidebar__link" href="/ustawienia" title="Ustawienia sklepu"', $html);
        $this->assertStringContainsString('<p class="ss-sidebar__group">' . text('account.settings_group') . '</p>', $html);
        $this->assertStringContainsString('<form class="ss-search" role="search" method="GET" action="/szukaj">', $html);
        $this->assertStringContainsString('<span class="ss-sidebar__identity-subtitle">Sklep · Kierownik</span>', $html);
        $this->assertStringContainsString('class="ss-avatar ss-avatar--md ss-avatar--' . $account->avatarColor()->value . '"', $html);
    }

    public function test_zone_stylesheets_must_be_existing_css_files(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $css = $this->packagePath('simplysmart/admiro', 'public/assets/css/base.css');

        $this->assertSame([$css], $factory->make(['name' => 'shop', 'stylesheets' => [$css]], 'shop', __DIR__)->stylesheets);
        $this->assertSame([], $factory->make(['name' => 'shop'], 'shop', __DIR__)->stylesheets);

        $this->expectException(InvalidArgumentException::class);
        $factory->make(['name' => 'shop', 'stylesheets' => [base_path('composer.json')]], 'shop', __DIR__);
    }

    public function test_zone_palettes_are_unique_known_palettes_in_config_order(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);

        $this->assertSame([Palette::Violet, Palette::Teal], $factory->make(['name' => 'shop', 'palettes' => ['violet', 'teal']], 'shop', __DIR__)->palettes);
        $this->assertSame([], $factory->make(['name' => 'shop'], 'shop', __DIR__)->palettes);

        foreach ([['pink'], ['teal', 'teal']] as $palettes) {
            try {
                $factory->make(['name' => 'shop', 'palettes' => $palettes], 'shop', __DIR__);
                $this->fail('Palettes ' . implode(', ', $palettes) . ' should be rejected.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_zone_logo_defaults_to_the_sign_in_logo_and_must_be_an_svg(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $svg = $this->packagePath('simplysmart/admiro', 'public/assets/img/brand/logo.svg');

        $this->assertSame($svg, $factory->make(['name' => 'shop', 'logo' => $svg], 'shop', __DIR__)->logo);
        $this->assertSame($svg, $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'auth' => ['logo' => $svg]], 'shop', __DIR__)->logo);
        $this->assertNull($factory->make(['name' => 'shop'], 'shop', __DIR__)->logo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone [shop] logo must be the path of an existing SVG file.');
        $factory->make(['name' => 'shop', 'logo' => $this->packagePath('simplysmart/admiro', 'public/assets/css/base.css')], 'shop', __DIR__);
    }

    public function test_app_layout_is_sidebar_by_default_and_must_be_a_known_variant(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);

        $this->assertSame(AppLayout::Sidebar, $factory->make(['name' => 'shop'], 'shop', __DIR__)->appLayout);
        $this->assertSame(AppLayout::Workspace, $factory->make(['name' => 'shop', 'app_layout' => 'workspace'], 'shop', __DIR__)->appLayout);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone [shop] app_layout must be one of: sidebar, workspace, zone, columns.');
        $factory->make(['name' => 'shop', 'app_layout' => 'grid'], 'shop', __DIR__);
    }

    public function test_zone_layout_keeps_only_profile_and_sign_out_in_the_footer_without_a_sitemap(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);

        $page = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/page')->assertOk()->getContent();
        $html = substr($page, (int) strpos($page, '<div class="ss-footer-host">'), (int) strpos($page, '</footer>') - (int) strpos($page, '<div class="ss-footer-host">'));

        $this->assertStringContainsString('ss-shell--zone', $html);
        $this->assertStringNotContainsString('data-ss-sitemap', $html);
        $this->assertStringNotContainsString(text('layout.sitemap_open'), $html);
        $this->assertStringNotContainsString(text('account.security_title'), $html);
        $this->assertMatchesRegularExpression('~<div class="ss-account-menu__panel ss-workspace-footer__account-panel">\s*<div class="ss-zone-account__menu">\s*<ul class="ss-account-menu__list">\s*<li><a class="ss-account-menu__link" href="' . preg_quote(url('/alpha/account/profile'), '~') . '"\s*>.*?' . preg_quote(text('account.profile_title'), '~') . '</a></li>\s*</ul>\s*<form class="ss-workspace-footer__sign-out" method="POST" action="' . preg_quote(route('alpha.auth.logout'), '~') . '">~s', $html);
        $this->assertMatchesRegularExpression('~<details class="ss-search__menu ss-search__menu--folder ss-search__actions".*?<p class="ss-search__folder-head">.*?<details class="ss-search__menu ss-search__menu--folder ss-search__find".*?' . preg_quote(text('layout.search_find_empty'), '~') . '.*?<input class="ss-search__input".*?<details class="ss-search__menu ss-search__scopes".*?<button class="ss-search__send"~s', $html);
        $this->assertMatchesRegularExpression('~<details class="ss-calendar-panel ss-side-panels" data-ss-dropdown data-ss-side-panels>.*?data-ss-side-panel="calendar">.*?ss-side-panel__tools--grid.*?data-ss-side-panel="notifications">~s', $html);
        $this->assertStringContainsString('<p class="ss-temp-mark" aria-hidden="true">' . text('layout.in_progress') . '</p>', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-search__menu-panel ss-search__folder">\s*<p class="ss-search__folder-head"><span class="ss-icon ss-search__folder-head-icon">.*?</span>\s*' . preg_quote(text('layout.search_find'), '~') . '</p>\s*<div class="ss-search__folder-body">\s*<div class="ss-search__folder-sheet">\s*<p class="ss-search__menu-empty">.*?</div>\s*</div>\s*<span class="ss-search__folder-tab" aria-hidden="true"></span>~s', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-side-panel__tools ss-side-panel__tools--grid"[^>]*>\s*<a class="ss-quick-tile" href="/ustawienia">.*?' . preg_quote(text('layout.settings'), '~') . '</span></a>\s*<details class="ss-quick-tile ss-quick-tile--menu" data-ss-theme-toggle hidden>.*?data-ss-theme="light".*?data-ss-theme="dark".*?data-ss-theme="contrast".*?data-ss-theme="contrast-yellow".*?<details class="ss-quick-tile ss-quick-tile--menu" data-ss-preference="text-size" hidden>.*?data-ss-preference-value="normal" data-ss-tile-close.*?data-ss-preference-value="large".*?<details class="ss-quick-tile ss-quick-tile--menu" data-ss-preference="text-size" hidden>.*?' . preg_quote(text('layout.zoom'), '~') . '.*?data-ss-preference-step="-1">.*?data-ss-preference-step="1">~s', $html);
        $this->assertMatchesRegularExpression('~<form class="ss-workspace-footer__lock" method="POST" action="' . preg_quote(route('alpha.account.lock'), '~') . '">.*?title="' . preg_quote(text('layout.lock_screen'), '~') . '"><span class="ss-icon ss-icon-button__icon">' . preg_quote(trim(CoreAssets::contents('img/icons/lock-screen.svg')), '~') . '~s', $html);
        $this->assertMatchesRegularExpression('~<a class="ss-context__back ss-link" href="[^"]*">.*?' . preg_quote(text_action('back'), '~') . '</a>~s', $html);
    }

    public function test_zone_layout_start_page_signs_out_above_the_title_with_a_single_context(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ZoneShopNavigation::class);

        $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/start')->assertOk()
            ->assertSee('<form class="ss-context__back-form" method="POST" action="' . route('alpha.auth.logout') . '">', false)
            ->assertDontSee('<a class="ss-context__back ss-link"', false);
    }

    public function test_flip_card_has_a_front_a_hidden_back_with_a_return_and_a_corner_toggle_shown_by_the_script(): void
    {
        $html = Blade::render('<x-admiro::flip-card label="Odwróć">Przód<x-slot:back>Tył</x-slot:back></x-admiro::flip-card>');

        $this->assertMatchesRegularExpression('~<div class="ss-flip" data-ss-flip>\s*<div class="ss-flip__inner">\s*<div class="ss-flip__face ss-flip__face--front" data-ss-flip-front>Przód</div>\s*<div class="ss-flip__face ss-flip__face--back" data-ss-flip-back inert aria-hidden="true">\s*<div class="ss-flip__side" data-ss-flip-side="back">Tył</div>\s*<button class="ss-flip__return ss-link" type="button" data-ss-flip-return>~', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-flip__toggles">\s*<button class="ss-icon-button ss-flip__toggle" type="button" aria-expanded="false" title="Odwróć" data-ss-flip-toggle="back" hidden><span class="ss-icon ss-icon-button__icon">' . preg_quote(trim(CoreAssets::contents('img/icons/rotate-left.svg')), '~') . '~', $html);
        $this->assertStringNotContainsString('data-ss-flip-side="alternate"', $html);
    }

    public function test_flip_card_alternate_back_is_hidden_until_its_flag_toggle_beside_the_rotate_toggle(): void
    {
        $html = Blade::render('<x-admiro::flip-card label="Odwróć" alternate-label="Kontakt">Przód<x-slot:back>Tył</x-slot:back><x-slot:alternate>Dane</x-slot:alternate></x-admiro::flip-card>');

        $this->assertMatchesRegularExpression('~<div class="ss-flip__side" data-ss-flip-side="back">Tył</div>\s*<div class="ss-flip__side" data-ss-flip-side="alternate" hidden>Dane</div>~', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-flip__toggles">\s*<button class="ss-icon-button ss-flip__toggle" type="button" aria-expanded="false" title="Kontakt" data-ss-flip-toggle="alternate" hidden><span class="ss-icon ss-icon-button__icon">' . preg_quote(trim(CoreAssets::contents('img/icons/flag.svg')), '~') . '.*?data-ss-flip-toggle="back"~s', $html);
    }

    public function test_organization_item_shows_the_kind_icon_name_and_nip_and_marks_the_current_unit(): void
    {
        $organization = (new \Simplysmart\Admiro\Models\Organization())->forceFill(['name' => 'Przychodnia Zdrowie', 'short_name' => 'Zdrowie', 'nip' => '5250001122']);

        $html = Blade::render('<x-admiro::organization.item :organization="$organization" kind="Placówka" icon="building" current />', ['organization' => $organization]);

        $this->assertMatchesRegularExpression('~<div class="ss-org-item ss-org-item--current"\s*aria-current="true"\s*>\s*<span class="ss-org-item__tile"><span class="ss-icon ss-org-item__icon">~', $html);
        $this->assertStringContainsString('<span class="ss-org-item__name">Zdrowie</span>', $html);
        $this->assertStringContainsString('<span class="ss-org-item__sub">Placówka · ' . text_data($organization, 'nip') . ' 5250001122</span>', $html);
        $this->assertStringNotContainsString('ul.', $html);
    }

    public function test_zone_navigation_can_choose_the_workspace_layout_for_the_request(): void
    {
        $this->assertSame(AppLayout::Sidebar, $this->app->make(\Simplysmart\Admiro\Contracts\ZoneRegistry::class)->get('alpha')->appLayout);
        $this->app->make(ZoneNavigations::class)->add('alpha', WorkspaceShopNavigation::class);

        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/page')->assertOk()->getContent();

        $this->assertStringContainsString('ss-shell--workspace', $html);
        $this->assertStringContainsString('<details class="ss-calendar-panel" data-ss-dropdown>', $html);
        $this->assertStringNotContainsString('class="ss-account-menu ss-quick-settings"', $html);
        $this->assertStringNotContainsString('<a class="ss-sidebar__link"', $html);
    }

    public function test_backdrop_is_the_brand_by_default_and_the_zone_logo_needs_a_logo(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $logo = dirname(__DIR__, 3) . '/packages/simplysmart/admiro/public/assets/img/brand/logo.svg';

        $this->assertSame(AppBackdrop::Brand, $factory->make(['name' => 'shop'], 'shop', __DIR__)->backdrop);
        $this->assertSame(AppBackdrop::ZoneLogo, $factory->make(['name' => 'shop', 'logo' => $logo, 'backdrop' => 'zone-logo'], 'shop', __DIR__)->backdrop);

        foreach ([['backdrop' => 'photo'], ['backdrop' => 'zone-logo']] as $config) {
            try {
                $factory->make(['name' => 'shop', ...$config], 'shop', __DIR__);
                $this->fail('Invalid backdrop accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringStartsWith('Zone [shop] backdrop', $exception->getMessage());
            }
        }
    }

    public function test_font_styles_keep_the_config_order_and_reject_invalid_keys_or_labels(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $styles = $factory->make(['name' => 'shop', 'font_styles' => ['serif' => 'shop::fonts.serif', 'narrow-2' => 'shop::fonts.narrow']], 'shop', __DIR__)->fontStyles;

        $this->assertSame(['serif', 'narrow-2'], array_map(static fn(ZoneFontStyle $style): string => $style->key, $styles));
        $this->assertSame('shop::fonts.narrow', $styles[1]->labelKey);
        $this->assertSame([], $factory->make(['name' => 'shop'], 'shop', __DIR__)->fontStyles);

        foreach ([['Serif' => 'shop::fonts.serif'], ['serif' => ''], ['serif'], ['serif' => ['label']]] as $fontStyles) {
            try {
                $factory->make(['name' => 'shop', 'font_styles' => $fontStyles], 'shop', __DIR__);
                $this->fail('Invalid font style accepted.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringStartsWith('Zone [shop] font_styles', $exception->getMessage());
            }
        }
    }

    public function test_appearance_settings_offer_the_zone_fonts_with_the_base_font_first(): void
    {
        $zone = $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop', 'font_styles' => ['serif' => 'layout.palette']], 'shop', __DIR__);
        $html = Blade::render('<x-admiro::preference-toggle :preference="$preference" :options="$options" labels />', [
            'preference' => AppearancePreference::FontStyle,
            'options' => $this->app->make(AppearanceChoices::class)->fontStyles($zone),
        ]);

        $this->assertStringContainsString('data-ss-preference="font" hidden>', $html);
        $this->assertLessThan(strpos($html, 'data-ss-preference-value="serif"'), strpos($html, 'data-ss-preference-value=""'));
        $this->assertStringContainsString('data-ss-font-sample="serif">Aa</span>', $html);
        $this->assertStringContainsString('<span>' . text('layout.palette') . '</span>', $html);
        $this->assertSame([], $this->app->make(AppearanceChoices::class)->fontStyles($this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop'], 'shop', __DIR__)));
    }

    public function test_backdrop_shows_the_zone_logo_at_the_bottom_instead_of_the_brand(): void
    {
        $zoneLogo = Blade::render('<x-admiro::app.backdrop :logo="$logo" />', ['logo' => '<svg viewBox="0 0 10 2"><path fill="currentColor" d="M0 0h10v2H0z"/></svg>']);
        $brand = Blade::render('<x-admiro::app.backdrop />');

        $this->assertStringContainsString('<div class="ss-backdrop ss-backdrop--bottom" aria-hidden="true">', $zoneLogo);
        $this->assertStringContainsString('<span class="ss-backdrop__logo"><svg viewBox="0 0 10 2">', $zoneLogo);
        $this->assertStringContainsString('<div class="ss-backdrop" aria-hidden="true">', $brand);
        $this->assertStringContainsString('<span class="ss-logo ss-backdrop__logo">', $brand);
    }

    public function test_settings_pages_in_the_workspace_form_one_area_with_categories_on_the_left(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', WorkspaceShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $settings = (string) $this->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="ss-settings-area">', $settings);
        $this->assertStringContainsString('<p class="ss-settings-area__group-title">Sklep</p>', $settings);
        $this->assertStringContainsString('<a class="ss-settings-area__link" href="/ustawienia" >', $settings);
        $this->assertMatchesRegularExpression('~<a class="ss-settings-area__link" href="' . preg_quote(url('/alpha/account/profile'), '~') . '"\s+aria-current="page"\s*>~', $settings);
        $this->assertStringNotContainsString('<div class="ss-settings-area">', (string) $this->get('/alpha/page')->getContent());
    }

    public function test_columns_layout_puts_the_left_menu_the_content_and_the_wiki_sidebar_between_the_zone_header_and_footer(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ColumnsShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $html = (string) $this->get('/alpha/columns')->assertOk()->getContent();

        $this->assertStringContainsString('ss-shell--zone', $html);
        $this->assertStringContainsString('<footer class="ss-workspace-footer ss-zone-footer">', $html);
        $this->assertStringContainsString("'ss-sidebar-wiki-pinned'", $html);
        $this->assertMatchesRegularExpression('~<div class="ss-columns ss-columns--with-left">\s*<div class="ss-columns__left" role="region" aria-label="' . preg_quote(text('layout.columns_left'), '~') . '" tabindex="0">\s*Menu sal\s*</div>\s*<div class="ss-columns__content">\s*x\s*</div>\s*<aside class="ss-sidebar-wiki" data-ss-sidebar-wiki~', $html);
        $this->assertMatchesRegularExpression('~data-ss-sidebar-wiki-pin aria-pressed="false" title="' . preg_quote(text('layout.sidebar_wiki_pin'), '~') . '" hidden>.*?<div class="ss-sidebar-wiki__body" tabindex="0">\s*<aside class="ss-wiki-tip" id="wiki-organizacja"~s', $html);

        $page = (string) $this->get('/alpha/page')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-columns">\s*<div class="ss-columns__content">\s*x\s*</div>~', $page);
        $this->assertStringContainsString('<p class="ss-sidebar-wiki__empty">' . text('layout.sidebar_wiki_empty') . '</p>', $page);
    }

    public function test_columns_layout_shows_the_settings_categories_in_the_left_menu_on_settings_pages(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ColumnsShopNavigation::class);
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $settings = (string) $this->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<div class="ss-columns__left"[^>]*>\s*<nav class="ss-settings-area__nav".*?<p class="ss-settings-area__group-title">Sklep</p>.*?</nav>\s*</div>\s*<div class="ss-columns__content">~s', $settings);
        $this->assertStringNotContainsString('<div class="ss-settings-area">', $settings);
    }

    public function test_compose_search_with_quick_actions_shows_the_plus_instead_of_the_magnifier(): void
    {
        $plain = new \Simplysmart\Admiro\View\Navigation\SearchBox('/szukaj', 'Szukaj');
        $withActions = new \Simplysmart\Admiro\View\Navigation\SearchBox('/szukaj', 'Szukaj', actions: [new \Simplysmart\Admiro\View\Navigation\NavigationItem('Dodaj', '/dodaj', 'plus')]);

        $this->assertStringContainsString('ss-search__icon', Blade::render('<x-admiro::app.search :search="$s" compose />', ['s' => $plain]));
        $html = Blade::render('<x-admiro::app.search :search="$s" compose />', ['s' => $withActions]);
        $this->assertStringNotContainsString('ss-search__icon', $html);
        $this->assertStringContainsString('<details class="ss-search__menu ss-search__actions" data-ss-dropdown>', $html);
    }

    public function test_page_header_can_have_an_eyebrow_and_the_context_switch_a_title(): void
    {
        $this->assertMatchesRegularExpression('~<p class="ss-context__eyebrow">Twój dzień</p>\s*<h1 class="ss-context__title">Czwartek</h1>~', Blade::render('<x-admiro::app.context title="Czwartek" eyebrow="Twój dzień" />'));

        $html = Blade::render('<x-admiro::app.context-switch :contexts="$contexts" />', ['contexts' => [new \Simplysmart\Admiro\View\Navigation\NavigationGroup('Organizacja · Firma', [new \Simplysmart\Admiro\View\Navigation\NavigationForm('Właściciel', '/c', 'context', 'a:owner', 'user', true)])]]);
        $this->assertSame(2, substr_count($html, 'd="m38 30l8-8m-4 14l4-4'), 'The context icon is on the toggle and in the title.');
        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__title">.*?</span>\s*' . preg_quote(text('layout.switch_context'), '~') . '</p>~s', $html);
    }

    public function test_page_header_link_can_carry_its_own_label_and_icon(): void
    {
        $html = Blade::render('<x-admiro::app.context title="Dashboard" back="#ss-context-switch" back-label="Przełącz kontekst" back-icon="switch" />');

        $this->assertMatchesRegularExpression('~<a class="ss-context__back ss-link" href="#ss-context-switch">.*?</span>\s*Przełącz kontekst</a>~s', $html);
        $this->assertStringContainsString(text_action('back'), Blade::render('<x-admiro::app.context title="Strona" back="/" />'));
    }

    public function test_organization_business_card_shows_the_registry_numbers_and_details_and_links_when_given_an_address(): void
    {
        $organization = (new \Simplysmart\Admiro\Models\Organization())->forceFill(['name' => 'Przychodnia Zdrowie', 'short_name' => 'Zdrowie', 'nip' => '5250001122', 'regon' => '012345678', 'phone' => '+48 22 100 20 30', 'mobile_phone' => '+48 600 100 200', 'email' => 'kontakt@example.com', 'billing_email' => 'faktury@example.com', 'street' => 'ul. Zdrowa 5', 'postal_code' => '00-950', 'city' => 'Warszawa']);

        $html = Blade::render('<x-admiro::organization.card :organization="$organization" href="/ustawienia" kicker="Placówka" />', ['organization' => $organization]);

        $this->assertMatchesRegularExpression('~<a class="ss-business-card ss-business-card--link"\s+href="/ustawienia"\s*>~', $html);
        $this->assertMatchesRegularExpression('~<span class="ss-business-card__top">\s*<span class="ss-business-card__kicker">Placówka</span>.*?</span>\s*<span class="ss-business-card__name">Zdrowie</span>\s*</span>\s*</span>\s*<span class="ss-business-card__registry">~s', $html);
        $this->assertStringContainsString('<span class="ss-business-card__lines"><span class="ss-business-card__line">ul. Zdrowa 5</span><span class="ss-business-card__line">00-950 Warszawa</span></span>', $html);
        $this->assertStringContainsString('<span class="ss-business-card__registry"><span class="ss-business-card__number">NIP 5250001122</span><span class="ss-business-card__number">REGON 012345678</span></span>', $html);
        $this->assertStringContainsString('+48 22 100 20 30', $html);
        $this->assertStringContainsString('+48 600 100 200', $html);
        $this->assertMatchesRegularExpression('~title="' . preg_quote(text_data($organization, 'billing_email'), '~') . '"\s*><span class="ss-icon ss-business-card__detail-icon">' . preg_quote(trim(CoreAssets::contents('img/icons/receipt.svg')), '~') . '\s*</span>\s*faktury@example.com</span>~', $html);
        $this->assertStringNotContainsString('ss-business-card__monogram', $html);
        $this->assertStringContainsString('<span class="ss-icon ss-business-card__arrow">' . CoreAssets::contents('img/icons/external-link.svg'), $html);
        $this->assertStringNotContainsString('Przychodnia Zdrowie', $html);
        $this->assertStringContainsString('<span class="ss-business-card__registry"><span class="ss-business-card__number">NIP —</span></span>', Blade::render('<x-admiro::organization.card :organization="$organization" />', ['organization' => (new \Simplysmart\Admiro\Models\Organization())->forceFill(['name' => 'Bez danych'])]));
        $this->assertMatchesRegularExpression('~<div class="ss-business-card"\s*>~', Blade::render('<x-admiro::organization.card :organization="$organization" />', ['organization' => $organization]));
        $this->assertStringNotContainsString('ss-business-card__details', Blade::render('<x-admiro::organization.card :organization="$organization" :contact="false" />', ['organization' => $organization]));
        $withActions = Blade::render('<x-admiro::organization.card :organization="$organization" href="/zadania" kicker="Placówka"><x-slot:actions><a href="/nowa">Nowa</a></x-slot:actions></x-admiro::organization.card>', ['organization' => $organization]);
        $this->assertMatchesRegularExpression('~<div class="ss-business-card ss-business-card--link"\s*>.*?<a class="ss-business-card__link" href="/zadania"><span class="ss-icon ss-business-card__arrow">.*?<span class="ss-sr-only">Zdrowie</span></a>.*?<span class="ss-business-card__actions"><a href="/nowa">Nowa</a></span>\s*</div>~s', $withActions);
        $this->assertStringContainsString('<span class="ss-business-card__details extra">', Blade::render('<x-admiro::organization.contact :organization="$organization" class="extra" />', ['organization' => $organization]));
    }

    public function test_social_links_skip_empty_addresses_and_reject_unknown_networks(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $zone = $factory->make(['name' => 'shop', 'social_links' => ['github' => 'https://github.com/shop', 'linkedin' => 'https://linkedin.com/company/shop', 'youtube' => '']], 'shop', __DIR__);

        $this->assertSame([[SocialNetwork::LinkedIn, 'https://linkedin.com/company/shop'], [SocialNetwork::GitHub, 'https://github.com/shop']], array_map(static fn(array $link): array => [$link['network'], $link['url']], $zone->socialLinks));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone [shop] social_links has unknown networks: myspace.');
        $factory->make(['name' => 'shop', 'social_links' => ['myspace' => 'https://myspace.com/shop']], 'shop', __DIR__);
    }

    public function test_zone_website_must_be_an_http_url(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);

        $this->assertSame('https://shop.example', $factory->make(['name' => 'shop', 'website' => 'https://shop.example'], 'shop', __DIR__)->website);
        $this->assertNull($factory->make(['name' => 'shop'], 'shop', __DIR__)->website);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone [shop] website must be an http(s) URL.');
        $factory->make(['name' => 'shop', 'website' => 'javascript://alert(1)'], 'shop', __DIR__);
    }

    public function test_zone_favicon_needs_known_types_with_matching_existing_files(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $svg = $this->packagePath('simplysmart/admiro', 'public/assets/img/brand/logo.svg');
        $ico = public_path('favicon.ico');
        $favicon = $factory->make(['name' => 'shop', 'favicon' => ['svg' => $svg, 'ico' => $ico]], 'shop', __DIR__)->favicon;

        $this->assertSame([FaviconType::Ico, FaviconType::Svg], $favicon?->types());
        $this->assertSame($svg, $favicon->path(FaviconType::Svg));
        $this->assertNull($favicon->path(FaviconType::Apple));
        $this->assertSame(12, strlen($favicon->version(FaviconType::Ico)));
        $this->assertNull($factory->make(['name' => 'shop'], 'shop', __DIR__)->favicon);

        foreach ([['png' => $svg], ['ico' => $svg], ['svg' => base_path('missing.svg')], [$svg]] as $files) {
            try {
                $factory->make(['name' => 'shop', 'favicon' => $files], 'shop', __DIR__);
                $this->fail('Favicon ' . json_encode($files) . ' should be rejected.');
            } catch (InvalidArgumentException $exception) {
                $this->assertStringStartsWith('Zone [shop]', $exception->getMessage());
            }
        }
    }

    public function test_page_renders_favicon_links_in_the_head(): void
    {
        $html = Blade::render('<x-admiro::page title="Start" :favicons="$favicons">x</x-admiro::page>', ['favicons' => [
            ['rel' => 'icon', 'href' => '/shop/favicon/ico?v=1', 'type' => null, 'sizes' => 'any'],
            ['rel' => 'icon', 'href' => '/shop/favicon/svg?v=2', 'type' => 'image/svg+xml', 'sizes' => null],
            ['rel' => 'apple-touch-icon', 'href' => '/shop/favicon/apple?v=3', 'type' => null, 'sizes' => null],
        ]]);
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertStringContainsString('<link rel="icon" href="/shop/favicon/ico?v=1" sizes="any">', $head);
        $this->assertStringContainsString('<link rel="icon" href="/shop/favicon/svg?v=2" type="image/svg+xml">', $head);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="/shop/favicon/apple?v=3">', $head);
        $this->assertStringNotContainsString('<link rel="icon"', Blade::render('<x-admiro::page title="Start">x</x-admiro::page>'));
    }

    public function test_zone_fonts_need_valid_faces_with_existing_woff2_files(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $file = sys_get_temp_dir() . '/zone-font-test.woff2';
        file_put_contents($file, 'wOF2');
        $face = ['family' => 'Brand Sans', 'weight' => 700, 'file' => $file, 'unicode_range' => 'U+0000-00FF, U+0131'];

        try {
            $fonts = $factory->make(['name' => 'shop', 'fonts' => ['brand-latin' => $face]], 'shop', __DIR__)->fonts;

            $this->assertSame(['brand-latin'], $fonts?->keys());
            $this->assertSame($file, $fonts->path('brand-latin'));
            $this->assertNull($fonts->path('missing'));
            $this->assertSame(
                '@font-face { font-family: "Brand Sans"; font-style: normal; font-weight: 700; font-display: swap; src: url("/shop/font/brand-latin?v=' . $fonts->version('brand-latin') . '") format("woff2"); unicode-range: U+0000-00FF, U+0131; }',
                $fonts->fontFaceCss(static fn(string $key, string $version): string => "/shop/font/{$key}?v={$version}"),
            );
            $this->assertNull($factory->make(['name' => 'shop'], 'shop', __DIR__)->fonts);

            foreach ([['Brand' => $face], ['brand' => [...$face, 'family' => 'Brand"; }']], ['brand' => [...$face, 'style' => 'oblique']], ['brand' => [...$face, 'weight' => 950]], ['brand' => [...$face, 'unicode_range' => 'latin']], ['brand' => [...$face, 'file' => base_path('composer.json')]], [$face]] as $fontsConfig) {
                try {
                    $factory->make(['name' => 'shop', 'fonts' => $fontsConfig], 'shop', __DIR__);
                    $this->fail('Fonts ' . json_encode($fontsConfig) . ' should be rejected.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertStringStartsWith('Zone [shop]', $exception->getMessage());
                }
            }
        } finally {
            unlink($file);
        }
    }

    public function test_page_renders_font_faces_in_the_head_with_the_csp_nonce(): void
    {
        $html = Blade::render('<x-admiro::page title="Start" :font-faces="$fontFaces">x</x-admiro::page>', ['fontFaces' => '@font-face { font-family: "Brand Sans"; }']);
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertMatchesRegularExpression('/<style nonce="[^"]*">@font-face \{ font-family: "Brand Sans"; \}<\/style>/', $head);
        $this->assertStringNotContainsString('@font-face', Blade::render('<x-admiro::page title="Start">x</x-admiro::page>'));
    }

    public function test_zone_legal_links_need_a_route_and_a_label_and_only_apply_to_zones_with_sign_in(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $links = [['route' => 'legal.privacy', 'label' => 'shop::legal.privacy']];

        $this->assertSame($links, $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_links' => $links], 'shop', __DIR__)->legalLinks);
        $this->assertSame([], $factory->make(['name' => 'shop', 'legal_links' => $links], 'shop', __DIR__)->legalLinks);

        $this->expectException(InvalidArgumentException::class);
        $factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'legal_links' => [['route' => 'legal.privacy']]], 'shop', __DIR__);
    }

    public function test_legal_links_sit_in_the_form_card_without_the_page_footer_or_the_theme_toggle(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Start" :legal-links="$links">x</x-admiro::auth.layout>', ['links' => [['url' => '/shop/privacy', 'label' => 'Privacy']]]);

        $this->assertStringContainsString('<nav class="ss-auth__legal" aria-label="' . e(text('layout.legal')) . '">', $html);
        $this->assertStringContainsString('<a class="ss-link" href="/shop/privacy">Privacy</a>', $html);
        $this->assertStringNotContainsString('data-ss-theme-toggle hidden', $html);
        $this->assertLessThan(strpos($html, '<nav class="ss-auth__legal"'), strpos($html, '<div class="ss-auth__header">'));
        $this->assertStringNotContainsString('<footer class="ss-footer"', $html);
        $this->assertStringNotContainsString('type="button" aria-expanded="false" aria-controls="ss-auth-footer"', $html);
        $this->assertStringNotContainsString('<div class="ss-auth__stage', $html);
    }

    public function test_installed_packages_list_only_direct_dependencies_of_the_application_and_its_own_packages(): void
    {
        $directory = sys_get_temp_dir() . '/installed-packages-test';
        @mkdir($directory);
        file_put_contents($directory . '/composer.json', (string) json_encode(['name' => 'own/app', 'require' => ['php' => '^8.4', 'vendor/framework' => '^1.0', 'own/core' => '@dev']]));
        file_put_contents($directory . '/installed.json', (string) json_encode([
            'packages' => [
                ['name' => 'vendor/framework', 'version' => '1.0.0', 'license' => ['MIT'], 'homepage' => 'https://example.com/framework', 'require' => ['vendor/framework-part' => '^1.0']],
                ['name' => 'vendor/framework-part', 'version' => '1.0.0', 'license' => ['MIT']],
                ['name' => 'own/core', 'version' => 'dev-main', 'require' => ['vendor/pdf' => '^3.0', 'ext-intl' => '*']],
                ['name' => 'own/zone', 'version' => 'dev-main', 'require' => ['own/core' => '@dev', 'vendor/flags' => '^2.0']],
                ['name' => 'vendor/pdf', 'version' => '3.1.0', 'license' => ['LGPL-2.1'], 'source' => ['url' => 'https://example.com/pdf.git']],
                ['name' => 'vendor/flags', 'version' => '2.0.0', 'license' => ['MIT']],
            ],
        ]));

        try {
            $packages = new InstalledPackages($directory . '/installed.json', $directory . '/composer.json')->all();
        } finally {
            unlink($directory . '/composer.json');
            unlink($directory . '/installed.json');
            rmdir($directory);
        }

        $this->assertSame(['vendor/flags', 'vendor/framework', 'vendor/pdf'], array_column($packages, 'name'));
        $this->assertSame([null, 'https://example.com/framework', 'https://example.com/pdf'], array_column($packages, 'homepage'));
        $this->assertSame(['LGPL-2.1'], $packages[2]['licenses']);
        $this->assertSame([], new InstalledPackages('/missing/installed.json', '/missing/composer.json')->all());
    }

    public function test_zone_styles_are_embedded_after_the_core_styles(): void
    {
        $html = Blade::render('<x-admiro::zone-styles :paths="$paths" />', ['paths' => [$this->packagePath('simplysmart/admiro', 'public/assets/css/base.css')]]);

        $this->assertStringContainsString('<style nonce=', $html);
        $this->assertStringContainsString('box-sizing: border-box;', $html);
        $this->assertSame('', trim(Blade::render('<x-admiro::zone-styles :paths="[]" />')));
    }

    public function test_zone_without_navigation_still_has_the_account_menu_and_sign_out(): void
    {
        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString('data-ss-shell', $html);
        $this->assertStringContainsString('<p class="ss-sidebar__title">' . text('layout.settings') . '</p>', $html);
        $this->assertStringContainsString(url('/alpha/account/appearance'), $html);
        $this->assertMatchesRegularExpression('~href="' . preg_quote(url('/alpha/account/profile'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringContainsString(url('/alpha/account/security'), $html);
        $this->assertStringContainsString('<form class="ss-sidebar__sign-out" method="POST" action="' . url('/alpha/signin/out') . '">', $html);
        $this->assertStringContainsString(text('sign_in.signed_in_as', ['name' => 'Anna Nowak']), $html);
    }

    public function test_page_meta_is_shown_under_the_title(): void
    {
        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/page')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<h1 class="ss-context__title">Zamówienia</h1>\s*<p class="ss-context__meta">12 zamówień</p>~', $html);
    }
}
