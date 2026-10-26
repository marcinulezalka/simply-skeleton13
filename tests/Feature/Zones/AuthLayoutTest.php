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
use Simplysmart\Admiro\Auth\AuthBrand;
use Simplysmart\Admiro\Auth\ZoneAuthSettingsFactory;
use Simplysmart\Admiro\Enums\AuthLayout;
use Simplysmart\Admiro\View\CoreAssets;
use Simplysmart\Admiro\View\Toast;

/**
 * Układ stron logowania (`auth.layout`): domyślnie jedna karta, strefa może wybrać dwie karty obok siebie
 * w ramie (marka na karcie odwróconej i wyniesionej po lewej, nagłówek i formularz na karcie tinted po prawej)
 * albo ich odbicie lustrzane, z własnym logo SVG (`auth.logo`) w karcie marki; nieznany układ zatrzymuje
 * konfigurację; przyciski formularzy mają ikony.
 */
class AuthLayoutTest extends SignInTestCase
{
    public function test_zone_uses_the_centered_layout_unless_it_chooses_split(): void
    {
        $factory = $this->app->make(ZoneAuthSettingsFactory::class);

        $this->assertSame(AuthLayout::Centered, $factory->make([])->layout);
        $this->assertSame(AuthLayout::Split, $factory->make(['layout' => 'split'])->layout);
        $this->assertSame(AuthLayout::SplitReversed, $factory->make(['layout' => 'split-reversed'])->layout);
    }

    public function test_reversed_split_mirrors_the_cards_and_shows_the_zone_logo(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie" :layout="$layout" logo="<svg>sklep</svg>">x</x-admiro::auth.layout>', ['layout' => AuthLayout::SplitReversed]);

        $this->assertStringContainsString('class="ss-auth ss-auth--split ss-auth--reversed"', $html);
        $this->assertStringContainsString('<span class="ss-logo ss-auth__brand-logo"><svg>sklep</svg></span>', $html);
        $this->assertMatchesRegularExpression('~\.ss-auth--reversed \.ss-frame\.ss-frame--row \{\s*flex-direction: row-reverse;~', CoreAssets::contents('css/app.css'));
    }

    public function test_code_and_pin_fields_show_a_subtle_icon(): void
    {
        $this->requestCode();

        $this->get('/alpha/signin/code')
            ->assertOk()
            ->assertSee('<div class="ss-input-wrap ss-input-wrap--icon" data-ss-code-fallback>', false)
            ->assertSee('class="ss-icon ss-input-wrap__icon"', false);

        $html = Blade::render('<x-admiro::pin-field icon="lock" id="pin" name="pin" label="PIN" autocomplete="current-password" :min="4" :max="8" />');

        $this->assertStringContainsString('<div class="ss-input-wrap ss-input-wrap--icon ss-input-wrap--keypad">', $html);
        $this->assertStringContainsString('<span class="ss-icon ss-input-wrap__icon">', $html);
    }

    public function test_zone_orders_and_names_the_legal_links_without_cookie_settings(): void
    {
        $this->get('/admiro/signin')->assertOk()->assertDontSee('data-ss-cookie-settings', false);
        $this->get('/alpha/signin')->assertOk();
        $this->assertSame(['cookie_policy_first' => true, 'cookie_settings' => false], config()->array('admiro.zone.auth.legal'));
        $this->assertSame('Polityka Prywatności', $this->app->make(\Simplysmart\Admiro\Contracts\ZoneRegistry::class)->get('admiro')->signInText('legal_privacy'));
    }

    public function test_zone_can_turn_off_the_home_link_under_the_sign_in_form(): void
    {
        $this->get('/alpha/signin')->assertOk()->assertSee('<div class="ss-auth__home">', false);
        $this->get('/admiro/signin')->assertOk()->assertDontSee('<div class="ss-auth__home">', false);
        $this->assertSame(['home_link' => false], array_intersect_key(config()->array('admiro.zone.auth'), ['home_link' => true]));
    }

    public function test_code_step_has_a_cell_per_digit_bolds_the_address_and_offers_icon_buttons(): void
    {
        $this->requestCode();

        $html = $this->get('/alpha/signin/code')->assertOk()->getContent();

        $this->assertIsString($html);
        $this->assertSame(6, substr_count($html, 'data-ss-code-cell>'));
        $this->assertStringContainsString('<input class="ss-input ss-code__cell" id="code-1" type="text" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" aria-label="' . text('layout.code_digit', ['position' => 1, 'length' => 6]) . '"', $html);
        $this->assertMatchesRegularExpression('~<input class="ss-input ss-input--code" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" minlength="6" maxlength="6" pattern="\[0-9\]\{6\}" required="required" autofocus="autofocus" data-ss-code-input>~', $html);
        $this->assertStringContainsString('<strong>' . self::EMAIL . '</strong>', $html);
        $this->assertMatchesRegularExpression('~<a class="ss-button" href="[^"]+/alpha/signin"><span class="ss-icon ss-button__icon">~', $html);
        $this->assertStringContainsString("input.type = 'hidden';", $html);
    }

    public function test_page_title_is_the_heading_unless_the_zone_gives_its_own(): void
    {
        $this->get('/alpha/signin')
            ->assertOk()
            ->assertSee('<title>' . text('sign_in.title') . '</title>', false);
    }

    public function test_zone_can_size_the_sign_in_title_controls_and_icon_stroke_with_tokens(): void
    {
        $tokens = CoreAssets::contents('css/tokens.css');
        $css = CoreAssets::contents('css/app.css');

        foreach (['--ss-auth-title-size: 1.5rem;', '--ss-auth-control-height: 2.5rem;', '--ss-icon-stroke: 1.75;', '--ss-radius-input: var(--ss-radius-control);', '--ss-auth-header-space: 0rem;', '--ss-auth-button-height: var(--ss-auth-control-height);', '--ss-color-accent-text: var(--ss-color-accent);', '--ss-auth-legal-border: none;', '--ss-link-decoration: underline;'] as $token) {
            $this->assertStringContainsString($token, $tokens);
        }

        $this->assertStringContainsString('font-size: var(--ss-auth-title-size);', $css);
        $this->assertStringContainsString('margin-bottom: var(--ss-auth-header-space);', $css);
        $this->assertStringContainsString('border-top: var(--ss-auth-legal-border);', $css);
        $this->assertMatchesRegularExpression('~\.ss-link \{[^}]*text-decoration: var\(--ss-link-decoration\);~', $css);
        $this->assertMatchesRegularExpression('~\.ss-accent-text \{\s*color: var\(--ss-color-accent-text\);~', $css);
        $this->assertMatchesRegularExpression('~\.ss-auth \.ss-input \{\s*min-height: var\(--ss-auth-control-height\);~', $css);
        $this->assertMatchesRegularExpression('~\.ss-auth \.ss-button:not\(\.ss-button--sm, \.ss-button--lg\) \{\s*--ss-button-height: var\(--ss-auth-button-height\);~', $css);
        $this->assertMatchesRegularExpression('~\.ss-icon svg\[stroke\] \{\s*stroke-width: var\(--ss-icon-stroke\);~', $css);
        $this->assertStringContainsString('border-radius: var(--ss-radius-input);', $css);
    }

    public function test_zone_logo_must_be_an_existing_svg_file(): void
    {
        $factory = $this->app->make(ZoneAuthSettingsFactory::class);
        $svg = $this->packagePath('simplysmart/admiro', 'public/assets/img/brand/logo.svg');

        $this->assertSame($svg, $factory->make(['logo' => $svg])->logo);
        $this->assertNull($factory->make([])->logo);

        $this->expectException(InvalidArgumentException::class);
        $factory->make(['logo' => '/no/such/logo.svg']);
    }

    public function test_unknown_layout_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(ZoneAuthSettingsFactory::class)->make(['layout' => 'sideways']);
    }

    public function test_split_layout_puts_the_brand_card_next_to_the_form_card(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie" :layout="$layout">formularz</x-admiro::auth.layout>', ['layout' => AuthLayout::Split]);

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
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie" :layout="$layout" :brand="$brand">x</x-admiro::auth.layout>', [
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
        $light = $this->packagePath('simplysmart/admiro', 'public/assets/img/brand/logo.svg');
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

    public function test_session_messages_show_as_global_toasts_instead_of_a_notice_in_the_card(): void
    {
        session()->put(Toast::STATUS_KEY, text('sign_in.signed_out'));
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie">x</x-admiro::auth.layout>');

        $this->assertMatchesRegularExpression('~<div class="ss-toast ss-toast--success" role="status" data-ss-toast\s+data-ss-toast-auto\s*>~', $html);
        $this->assertStringContainsString('<p class="ss-toast__message">' . e(text('sign_in.signed_out')) . '</p>', $html);
        $this->assertStringContainsString('const DISMISS_AFTER = 6000;', $html);
        $this->assertStringNotContainsString('class="ss-notice"', $html);
    }

    public function test_sign_in_card_links_back_to_the_welcome_page(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie">x</x-admiro::auth.layout>');

        $this->assertMatchesRegularExpression('~<div class="ss-auth__home">\s*<a class="ss-button" href="' . preg_quote(url('/'), '~') . '"><span class="ss-icon ss-button__icon">.*?</span>\s*' . preg_quote(text('layout.home'), '~') . '</a>~s', $html);
    }

    public function test_pin_field_opens_an_on_screen_numeric_keypad_in_a_modal(): void
    {
        $html = Blade::render('<x-admiro::pin-field id="pin" name="pin" label="PIN" autocomplete="current-password" :min="4" :max="8" error="Zły PIN" autofocus />');

        $this->assertMatchesRegularExpression('~<input class="ss-input ss-input--code" id="pin" name="pin" type="password" inputmode="numeric" autocomplete="current-password" minlength="4" maxlength="8" required="required" aria-invalid="true" aria-describedby="pin-error" autofocus="autofocus"\s*>~', $html);
        $this->assertStringContainsString('aria-controls="pin-keypad" title="' . text('layout.keypad.open') . '" data-ss-keypad-open hidden>', $html);
        $this->assertStringContainsString('<dialog class="ss-keypad" id="pin-keypad" aria-label="PIN" data-ss-keypad data-ss-keypad-for="pin">', $html);
        $this->assertStringNotContainsString('ss-keypad__title', $html);
        $this->assertSame(10, substr_count($html, 'data-ss-keypad-digit='));
        $this->assertStringContainsString('data-ss-keypad-backspace', $html);
        $this->assertStringContainsString('data-ss-keypad-clear', $html);
        $this->assertMatchesRegularExpression('~data-ss-keypad-for="pin">\s*<p class="ss-keypad__display"~', $html);
        $this->assertMatchesRegularExpression('~data-ss-keypad-digit="0">0</button>\s*<button class="ss-keypad__key ss-keypad__key--muted" type="button" data-ss-keypad-backspace>~', $html);
        $this->assertMatchesRegularExpression('~<div class="ss-keypad__footer">\s*<button class="ss-button" type="button" data-ss-keypad-close><span class="ss-icon ss-button__icon">.*?</span>\s*' . preg_quote(text('layout.keypad.back'), '~') . '</button>\s*<button class="ss-button ss-button--primary ss-keypad__done" type="button" data-ss-keypad-done><span class="ss-icon ss-button__icon">.*?</span>\s*' . preg_quote(text('layout.keypad.done'), '~') . '</button>~s', $html);
        $this->assertStringContainsString('<p class="ss-field__error" id="pin-error" role="alert">Zły PIN</p>', $html);

        $plain = Blade::render('<x-admiro::pin-field id="code" name="code" label="Kod" type="text" autocomplete="one-time-code" :min="6" :max="6" :keypad="false" />');

        $this->assertStringNotContainsString('<dialog', $plain);
        $this->assertStringNotContainsString('aria-invalid', $plain);
    }

    public function test_pin_steps_offer_the_keypad_and_load_its_script(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="PIN">x</x-admiro::auth.layout>');

        $this->assertStringContainsString('const init = (dialog) => {', $html);
        $this->assertStringContainsString("dialog.querySelector('[data-ss-keypad-backspace]')", $html);
        $this->assertStringContainsString('form.requestSubmit();', $html);
    }

    public function test_sign_in_page_has_neither_the_theme_toggle_nor_the_palette_switch(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie" :layout="$layout">x</x-admiro::auth.layout>', ['layout' => AuthLayout::Split]);

        $this->assertStringNotContainsString('<div class="ss-auth__theme">', $html);
        $this->assertStringNotContainsString('data-ss-theme-toggle hidden', $html);
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
