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
use Simplysmart\Mainboard\Auth\ZoneAuthSettingsFactory;
use Simplysmart\Mainboard\Enums\AuthLayout;

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
        $this->assertStringContainsString('<main class="ss-auth__cards ss-frame ss-frame--row">', $html);
        $this->assertStringContainsString('<aside class="ss-card ss-card--inverse ss-card--elevated ss-auth__brand">', $html);
        $this->assertStringContainsString('class="ss-card ss-card--tinted ss-auth__card"', $html);
        $this->assertStringNotContainsString('class="ss-logo ss-auth__logo"', $html);
        $this->assertStringContainsString('<h1 class="ss-auth__title">Logowanie</h1>', $html);
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
}
