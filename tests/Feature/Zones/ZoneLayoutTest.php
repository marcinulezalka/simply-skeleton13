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
use Simplysmart\Admiro\Enums\AppLayout;
use Simplysmart\Admiro\Enums\FaviconType;
use Simplysmart\Admiro\Enums\Palette;
use Simplysmart\Admiro\Enums\SocialNetwork;
use Simplysmart\Admiro\Queries\InstalledPackages;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Simplysmart\Admiro\Zones\ZoneNavigations;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\ShopNavigation;

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
        $this->expectExceptionMessage('Zone [shop] app_layout must be one of: sidebar, workspace.');
        $factory->make(['name' => 'shop', 'app_layout' => 'grid'], 'shop', __DIR__);
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
}
