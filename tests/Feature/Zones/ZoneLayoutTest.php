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
        $this->assertStringContainsString(url('/alpha/account/pin'), $html);
        $this->assertStringContainsString('<form method="POST" action="' . url('/alpha/signin/out') . '">', $html);
        $this->assertStringContainsString(text('sign_in.signed_in_as', ['name' => 'Anna Nowak']), $html);
    }
}
