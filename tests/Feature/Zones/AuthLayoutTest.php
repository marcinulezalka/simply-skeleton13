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
use Simplysmart\Mainboard\Auth\AuthBrand;
use Simplysmart\Mainboard\Auth\ZoneAuthSettingsFactory;
use Simplysmart\Mainboard\Enums\AuthLayout;
use Simplysmart\Mainboard\View\CoreAssets;

/**
 * Układ stron logowania (`auth.layout`): domyślnie jedna karta, strefa może wybrać dwie karty obok siebie
 * w ramie (marka na karcie odwróconej i wyniesionej po lewej, nagłówek i formularz na karcie tinted po prawej),
 * nieznany układ zatrzymuje konfigurację; przyciski formularzy mają ikony.
 */
class AuthLayoutTest extends SignInTestCase
{
    public function test_zone_uses_the_centered_layout_unless_it_chooses_split(): void
    {
        $factory = $this->app->make(ZoneAuthSettingsFactory::class);

        $this->assertSame(AuthLayout::Centered, $factory->make([])->layout);
        $this->assertSame(AuthLayout::Split, $factory->make(['layout' => 'split'])->layout);
    }

    public function test_unknown_layout_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(ZoneAuthSettingsFactory::class)->make(['layout' => 'sideways']);
    }

    public function test_split_layout_puts_the_brand_card_next_to_the_form_card(): void
    {
        $html = Blade::render('<x-mainboard::auth.layout title="Logowanie" :layout="$layout">formularz</x-mainboard::auth.layout>', ['layout' => AuthLayout::Split]);

        $this->assertStringContainsString('class="ss-auth ss-auth--split"', $html);
        $this->assertStringNotContainsString('class="ss-auth ss-auth--split ss-auth--image"', $html);
        $this->assertStringContainsString('<main class="ss-auth__cards ss-frame ss-frame--row">', $html);
        $this->assertStringContainsString('<aside class="ss-card ss-card--inverse ss-card--elevated ss-auth__brand">', $html);
        $this->assertStringContainsString('class="ss-card ss-card--tinted ss-auth__card"', $html);
        $this->assertStringNotContainsString('class="ss-logo ss-auth__logo"', $html);
        $this->assertStringContainsString('<h1 class="ss-auth__title">Logowanie</h1>', $html);
    }

    public function test_split_layout_shows_the_zone_banner_instead_of_the_logo(): void
    {
        $html = Blade::render('<x-mainboard::auth.layout title="Logowanie" :layout="$layout" :brand="$brand">x</x-mainboard::auth.layout>', [
            'layout' => AuthLayout::Split,
            'brand' => ['light' => '/banner-light.jpg', 'dark' => '/banner-dark.jpg', 'alt' => 'Sklep'],
        ]);

        $this->assertStringContainsString('class="ss-auth ss-auth--split ss-auth--image"', $html);
        $this->assertStringContainsString('<aside class="ss-card ss-card--inverse ss-card--elevated ss-auth__brand ss-auth__brand--image">', $html);
        $this->assertStringContainsString('<img class="ss-auth__brand-image ss-theme-light" src="/banner-light.jpg" alt="Sklep" loading="lazy">', $html);
        $this->assertStringContainsString('<img class="ss-auth__brand-image ss-theme-dark" src="/banner-dark.jpg" alt="Sklep" loading="lazy">', $html);
        $this->assertStringNotContainsString('class="ss-logo ss-auth__brand-logo"', $html);
    }

    public function test_banner_needs_an_existing_image_and_dark_falls_back_to_light(): void
    {
        $light = base_path('packages/simplysmart/mainboard/public/assets/img/brand/logo.svg');
        $brand = $this->app->make(ZoneAuthSettingsFactory::class)->make(['brand' => ['light' => $light, 'alt' => 'x']])->brand;

        $this->assertNotNull($brand);
        $this->assertSame($light, $brand->path(AuthBrand::DARK));
        $this->assertNull($brand->path('other'));
        $this->assertNotSame('', $brand->version(AuthBrand::LIGHT));
        $this->assertNull($this->app->make(ZoneAuthSettingsFactory::class)->make([])->brand);

        $this->expectException(InvalidArgumentException::class);
        new AuthBrand('/no/such/banner.jpg');
    }

    public function test_zone_sign_in_page_uses_the_zone_layout(): void
    {
        $this->get('/alpha/signin')
            ->assertOk()
            ->assertSee('class="ss-auth"', false)
            ->assertSee('class="ss-logo ss-auth__logo"', false)
            ->assertSee('class="ss-card ss-auth__card"', false)
            ->assertSee('class="ss-icon ss-button__icon"', false)
            ->assertDontSee('ss-auth__brand"', false);
    }

    public function test_sign_in_page_has_the_theme_toggle_but_no_palette_switch(): void
    {
        $html = Blade::render('<x-mainboard::auth.layout title="Logowanie" :layout="$layout">x</x-mainboard::auth.layout>', ['layout' => AuthLayout::Split]);

        $this->assertStringContainsString('<div class="ss-auth__theme">', $html);
        $this->assertStringContainsString('data-ss-theme-toggle', $html);
        $this->assertStringNotContainsString('data-ss-palette-switch hidden', $html);
    }

    public function test_image_card_drops_its_border_and_the_form_card_covers_its_rounding(): void
    {
        $css = CoreAssets::contents('css/app.css');

        $this->assertMatchesRegularExpression('~\.ss-auth__brand--image \{[^}]*border: 0;[^}]*background: transparent;~', $css);
        $this->assertStringContainsString('margin-top: calc(-1 * var(--ss-radius-nested));', $css);
        $this->assertStringContainsString('margin-left: calc(-1 * var(--ss-radius-nested));', $css);
        $this->assertMatchesRegularExpression('~\.ss-auth \{[^}]*linear-gradient\(160deg, var\(--ss-color-auth-from\), var\(--ss-color-auth-to\)\)~', $css);
    }
}
