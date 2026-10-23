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

use PHPUnit\Framework\Attributes\DataProvider;
use Simplysmart\Mainboard\Enums\DesignSystemArea;
use Simplysmart\Mainboard\Enums\DesignSystemView;
use Simplysmart\Mainboard\Enums\Palette;
use Simplysmart\Mainboard\View\CoreAssets;
use Simplysmart\Mainboard\View\DesignTokens;
use Tests\TestCase;

/**
 * Strona design systemu Core (`mainboard.design-system`): tokeny odczytane wprost z tokens.css (jasny i ciemny,
 * z rozwiązanym var()), wszystkie ikony, nawigacja w dwóch grupach (widoki w rozwijanych obszarach DesignSystemArea),
 * gotowe widoki aplikacji z DesignSystemView,
 * brak indeksowania; wyłączona strona i nieznany widok odpowiadają 404.
 */
class DesignSystemPageTest extends TestCase
{
    public function test_page_shows_tokens_from_the_css_and_every_icon(): void
    {
        $response = $this->get(route('mainboard.design-system'));

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertSee('--ss-color-surface');
        $response->assertSee('.ss-ds-chip--bg-light { background: #f8fafc; }', false);
        $response->assertSee('.ss-ds-chip--bg-dark { background: #020617; }', false);

        foreach (CoreAssets::names('img/icons', 'svg') as $icon) {
            $response->assertSee('<code>' . $icon . '</code>', false);
        }
    }

    public function test_navigation_card_has_foundations_and_every_view(): void
    {
        $response = $this->get(route('mainboard.design-system'));

        $response->assertSee(text('design_system.nav_foundations'));
        $response->assertSee(text('design_system.nav_views'));

        foreach (DesignSystemArea::cases() as $area) {
            $response->assertSee('<summary class="ss-ds__nav-summary">' . $area->label() . '</summary>', false);
        }

        foreach (DesignSystemView::cases() as $view) {
            $response->assertSee(route('mainboard.design-system.view', $view), false);
        }
    }

    public function test_every_view_belongs_to_exactly_one_area(): void
    {
        $grouped = array_merge(...array_map(static fn(DesignSystemArea $area): array => $area->views(), DesignSystemArea::cases()));

        $this->assertEqualsCanonicalizing(DesignSystemView::cases(), $grouped);
        $this->assertCount(count(DesignSystemView::cases()), $grouped);
        $this->assertSame([
            DesignSystemView::SignIn, DesignSystemView::SignInSplit, DesignSystemView::SignInCode, DesignSystemView::SignInCodeInvalid,
            DesignSystemView::SignInPin, DesignSystemView::SignInPinLimit, DesignSystemView::SignInPinSetup, DesignSystemView::SignInExpired,
            DesignSystemView::ForgotPin,
        ], DesignSystemArea::Authorization->views());
        $this->assertCount(7, DesignSystemArea::Errors->views());
    }

    public function test_area_of_the_current_view_is_expanded(): void
    {
        $html = $this->get(route('mainboard.design-system.view', DesignSystemView::List))->getContent();

        $this->assertIsString($html);
        $this->assertMatchesRegularExpression('~<details class="ss-ds__nav-group"\s+open\s*>\s*<summary class="ss-ds__nav-summary">' . preg_quote(DesignSystemArea::Workspace->label(), '~') . '~', $html);
        $this->assertDoesNotMatchRegularExpression('~<details class="ss-ds__nav-group"\s+open\s*>\s*<summary class="ss-ds__nav-summary">' . preg_quote(DesignSystemArea::Authorization->label(), '~') . '~', $html);
    }

    /**
     * @return array<string, array{DesignSystemView}>
     */
    public static function views(): array
    {
        return array_combine(
            array_map(static fn(DesignSystemView $view): string => $view->value, DesignSystemView::cases()),
            array_map(static fn(DesignSystemView $view): array => [$view], DesignSystemView::cases()),
        );
    }

    #[DataProvider('views')]
    public function test_every_ready_view_renders_without_indexing(DesignSystemView $view): void
    {
        $this->get(route('mainboard.design-system.view', $view))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_record_view_marks_the_current_step_in_text_too(): void
    {
        $this->get(route('mainboard.design-system.view', DesignSystemView::Record))
            ->assertSee('aria-current="step"', false)
            ->assertSee('(' . text('design_system.demo.step_current') . ')');
    }

    public function test_unknown_address_shows_the_core_error_page(): void
    {
        $this->get('/no-such-page-anywhere')
            ->assertNotFound()
            ->assertSee(text('errors.404.title'))
            ->assertSee(text('errors.code', ['code' => 404]))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_server_error_pages_offer_a_retry(): void
    {
        foreach ([DesignSystemView::Error500, DesignSystemView::Error503] as $view) {
            $this->get(route('mainboard.design-system.view', $view))->assertOk()->assertSee(text('errors.retry'));
        }

        $this->get(route('mainboard.design-system.view', DesignSystemView::Error404))->assertDontSee(text('errors.retry'));
    }

    public function test_sign_in_previews_render_the_real_screens_with_their_states(): void
    {
        $states = [
            DesignSystemView::SignInCodeInvalid->value => text('sign_in.failure'),
            DesignSystemView::SignInPinLimit->value => text('sign_in.too_many_attempts', ['seconds' => 60]),
            DesignSystemView::SignInPinSetup->value => text('sign_in.weak_pin'),
            DesignSystemView::SignInExpired->value => text('sign_in.expired'),
        ];

        foreach ($states as $view => $message) {
            $this->get(route('mainboard.design-system.view', $view))->assertOk()->assertSee('role="alert">' . e($message), false);
        }

        $this->get(route('mainboard.design-system.view', DesignSystemView::SignInCode))->assertSee(text('sign_in.code_title'));
    }

    public function test_unknown_view_is_not_found(): void
    {
        $this->get('/design-system/views/unknown')->assertNotFound();
    }

    public function test_disabled_page_and_views_are_not_found(): void
    {
        config(['mainboard.design_system.enabled' => false]);

        $this->get(route('mainboard.design-system'))->assertNotFound();
        $this->get(route('mainboard.design-system.view', DesignSystemView::Dashboard))->assertNotFound();
    }

    public function test_tokens_resolve_light_dark_values_also_inside_shadows(): void
    {
        $tokens = DesignTokens::fromCss(<<<'CSS'
            :root {
                --ss-gray-900: #111827;
                --ss-gray-50: #f9fafb;
                --ss-color-text: light-dark(var(--ss-gray-900), var(--ss-gray-50));
                --ss-shadow-frame: 0 1px 2px 0 light-dark(rgb(0 0 0 / 0.04), rgb(0 0 0 / 0.3));
            }
            CSS);

        $text = $tokens->group('color')[0];
        $shadow = $tokens->group('shadow')[0];

        $this->assertSame(['#111827', '#f9fafb'], [$text->light, $text->dark]);
        $this->assertSame(['0 1px 2px 0 rgb(0 0 0 / 0.04)', '0 1px 2px 0 rgb(0 0 0 / 0.3)'], [$shadow->light, $shadow->dark]);
    }

    public function test_core_tokens_switch_theme_with_light_dark_and_data_theme(): void
    {
        $css = CoreAssets::contents('css/tokens.css');

        $this->assertStringNotContainsString('prefers-color-scheme', $css);
        $this->assertStringContainsString(':root[data-theme="dark"] {', $css);
        $this->assertStringContainsString(':root[data-theme="light"] {', $css);
        $this->assertStringContainsString('--ss-color-bg: light-dark(', $css);
    }

    public function test_every_palette_overlays_the_accent_in_both_themes_and_beats_zone_tokens(): void
    {
        $css = CoreAssets::contents('css/tokens.css');

        $this->assertStringContainsString('--ss-color-accent: var(--ss-color-accent-base);', $css);

        foreach (Palette::cases() as $palette) {
            $this->assertStringContainsString('--ss-palette-' . $palette->value . '-accent: light-dark(', $css);
            $this->assertStringContainsString(':root[data-palette="' . $palette->value . '"] {', $css);
            $this->assertStringContainsString('--ss-color-accent: var(--ss-palette-' . $palette->value . '-accent);', $css);
            $this->assertStringContainsString('.ss-palette-swatch--' . $palette->value . ' {', CoreAssets::contents('css/app.css'));
        }
    }

    public function test_page_shows_palettes_with_a_live_switch(): void
    {
        $response = $this->get(route('mainboard.design-system'));

        $response->assertSee('<meta name="ss-palettes" content="' . implode(' ', array_column(Palette::cases(), 'value')) . '" data-scope="design-system">', false);
        $response->assertSee('id="palettes"', false);
        $response->assertSee('href="#palettes"', false);
        $response->assertSee('<code>--ss-palette-teal-accent</code>', false);
        $response->assertSee('.ss-ds-chip--teal-accent-light { background: #0f766e; }', false);
        $response->assertSee('data-ss-palette-switch hidden', false);
    }

    public function test_tokens_resolve_references_per_theme_and_skip_comments(): void
    {
        $tokens = DesignTokens::fromCss(<<<'CSS'
            /* --ss-color-ghost: #123456; */
            :root {
                --ss-white: #ffffff;
                --ss-black: #000000;
                --ss-color-surface: var(--ss-white);
                --ss-color-text: var(--ss-color-surface);
                --ss-radius-card: 1rem;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --ss-color-surface: var(--ss-black);
                }
            }
            CSS);

        $colors = $tokens->group('color');

        $this->assertSame(['surface', 'text'], array_map(static fn($token): string => $token->key, $colors));
        $this->assertSame(['#ffffff', '#000000'], [$colors[0]->light, $colors[0]->dark]);
        $this->assertSame(['#ffffff', '#000000'], [$colors[1]->light, $colors[1]->dark]);
        $this->assertTrue($colors[0]->isThemed());
        $this->assertSame('1rem', $tokens->group('radius')[0]->light);
        $this->assertFalse($tokens->group('radius')[0]->isThemed());
    }
}
