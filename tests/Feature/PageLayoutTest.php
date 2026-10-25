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
use Tests\TestCase;

/**
 * Szablon bazowy x-admiro::page: meta title, description, keywords (lista lub tekst), tags i robots;
 * layout logowania korzysta z niego zamiast własnego <head>.
 */
class PageLayoutTest extends TestCase
{
    public function test_renders_meta_from_props(): void
    {
        $html = Blade::render('<x-admiro::page title="Kraje" description="Słownik krajów" :keywords="[\'kraje\', \'ISO\']" :tags="[\'eu\', \'b2b\']">treść</x-admiro::page>');

        $this->assertStringContainsString('<title>Kraje</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Słownik krajów">', $html);
        $this->assertStringContainsString('<meta name="keywords" content="kraje, ISO">', $html);
        $this->assertStringContainsString('<meta property="article:tag" content="eu">', $html);
        $this->assertStringContainsString('<meta property="article:tag" content="b2b">', $html);
        $this->assertStringContainsString('treść', $html);
        $this->assertStringNotContainsString('name="robots"', $html);
    }

    public function test_skips_empty_meta(): void
    {
        $html = Blade::render('<x-admiro::page title="Start">x</x-admiro::page>');

        $this->assertStringNotContainsString('name="description"', $html);
        $this->assertStringNotContainsString('name="keywords"', $html);
        $this->assertStringNotContainsString('article:tag', $html);
    }

    public function test_sign_in_layout_builds_on_the_page_template(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Logowanie">formularz</x-admiro::auth.layout>');

        $this->assertSame(1, substr_count($html, '</head>'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('<h1 class="ss-auth__title">Logowanie</h1>', $html);
    }

    public function test_sign_in_layout_embeds_core_styles_lead_and_actions(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="Kod" lead="Wysłaliśmy kod.">formularz<x-slot:actions><a href="#">Zmień adres</a></x-slot:actions></x-admiro::auth.layout>');

        $this->assertStringContainsString('--ss-color-surface', $html);
        $this->assertStringContainsString('class="ss-logo ss-auth__logo"', $html);
        $this->assertStringContainsString('<p class="ss-auth__lead">Wysłaliśmy kod.</p>', $html);
        $this->assertStringContainsString('<div class="ss-auth__actions"><a href="#">Zmień adres</a></div>', $html);
    }

    public function test_sign_in_layout_skips_empty_lead_and_actions(): void
    {
        $html = Blade::render('<x-admiro::auth.layout title="PIN">formularz</x-admiro::auth.layout>');

        $this->assertStringNotContainsString('class="ss-auth__lead"', $html);
        $this->assertStringNotContainsString('class="ss-auth__actions"', $html);
    }
}
