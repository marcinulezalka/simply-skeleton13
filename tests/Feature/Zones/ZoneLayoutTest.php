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
use Simplysmart\Mainboard\Enums\FaviconType;
use Simplysmart\Mainboard\Enums\Palette;
use Simplysmart\Mainboard\Zones\ZoneDefinitionFactory;
use Simplysmart\Mainboard\Zones\ZoneNavigations;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\ShopNavigation;

/**
 * Layout strefy na layoucie aplikacji: menu i wyszukiwarka z ZoneNavigation zarejestrowanego dla strefy, awatar
 * w kolorze konta, druga linia, menu konta (profil, PIN) z wylogowaniem formularzem; strefa bez menu działa bez pozycji.
 */
class ZoneLayoutTest extends ZoneTestCase
{
    public function test_zone_menu_search_and_subtitle_come_from_the_registered_navigation(): void
    {
        $this->app->make(ZoneNavigations::class)->add('alpha', ShopNavigation::class);
        $account = $this->account(AlphaAccount::class);

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString('<a class="ss-sidebar__link" href="/zamowienia" title="Zamówienia"', $html);
        $this->assertStringContainsString('<form class="ss-search" role="search" method="GET" action="/szukaj">', $html);
        $this->assertStringContainsString('<span class="ss-sidebar__identity-subtitle">Sklep · Kierownik</span>', $html);
        $this->assertStringContainsString('class="ss-avatar ss-avatar--md ss-avatar--' . $account->avatarColor()->value . '"', $html);
    }

    public function test_zone_stylesheets_must_be_existing_css_files(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $css = base_path('packages/simplysmart/mainboard/public/assets/css/base.css');

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

    public function test_zone_favicon_needs_known_types_with_matching_existing_files(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $svg = base_path('packages/simplysmart/mainboard/public/assets/img/brand/logo.svg');
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
        $html = Blade::render('<x-mainboard::page title="Start" :favicons="$favicons">x</x-mainboard::page>', ['favicons' => [
            ['rel' => 'icon', 'href' => '/shop/favicon/ico?v=1', 'type' => null, 'sizes' => 'any'],
            ['rel' => 'icon', 'href' => '/shop/favicon/svg?v=2', 'type' => 'image/svg+xml', 'sizes' => null],
            ['rel' => 'apple-touch-icon', 'href' => '/shop/favicon/apple?v=3', 'type' => null, 'sizes' => null],
        ]]);
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $this->assertStringContainsString('<link rel="icon" href="/shop/favicon/ico?v=1" sizes="any">', $head);
        $this->assertStringContainsString('<link rel="icon" href="/shop/favicon/svg?v=2" type="image/svg+xml">', $head);
        $this->assertStringContainsString('<link rel="apple-touch-icon" href="/shop/favicon/apple?v=3">', $head);
        $this->assertStringNotContainsString('<link rel="icon"', Blade::render('<x-mainboard::page title="Start">x</x-mainboard::page>'));
    }

    public function test_zone_styles_are_embedded_after_the_core_styles(): void
    {
        $html = Blade::render('<x-mainboard::zone-styles :paths="$paths" />', ['paths' => [base_path('packages/simplysmart/mainboard/public/assets/css/base.css')]]);

        $this->assertStringContainsString('<style nonce=', $html);
        $this->assertStringContainsString('box-sizing: border-box;', $html);
        $this->assertSame('', trim(Blade::render('<x-mainboard::zone-styles :paths="[]" />')));
    }

    public function test_zone_without_navigation_still_has_the_account_menu_and_sign_out(): void
    {
        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString('data-ss-shell', $html);
        $this->assertStringNotContainsString('class="ss-sidebar__link"', $html);
        $this->assertMatchesRegularExpression('~href="' . preg_quote(url('/alpha/account/profile'), '~') . '"\s+aria-current="page"~', $html);
        $this->assertStringContainsString(url('/alpha/account/security'), $html);
        $this->assertStringContainsString('<form method="POST" action="' . url('/alpha/signin/out') . '">', $html);
        $this->assertStringContainsString(text('sign_in.signed_in_as', ['name' => 'Anna Nowak']), $html);
    }
}
