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
 * Szablon bazowy x-mainboard::page: meta title, description, keywords (lista lub tekst), tags i robots;
 * layout logowania korzysta z niego zamiast własnego <head>.
 */
class PageLayoutTest extends TestCase
{
    public function test_renders_meta_from_props(): void
    {
        $html = Blade::render('<x-mainboard::page title="Kraje" description="Słownik krajów" :keywords="[\'kraje\', \'ISO\']" :tags="[\'eu\', \'b2b\']">treść</x-mainboard::page>');

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
        $html = Blade::render('<x-mainboard::page title="Start">x</x-mainboard::page>');

        $this->assertStringNotContainsString('name="description"', $html);
        $this->assertStringNotContainsString('name="keywords"', $html);
        $this->assertStringNotContainsString('article:tag', $html);
    }

    public function test_sign_in_layout_builds_on_the_page_template(): void
    {
        $html = Blade::render('<x-mainboard::auth.layout title="Logowanie">formularz</x-mainboard::auth.layout>');

        $this->assertSame(1, substr_count($html, '<head>'));
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('<h1>Logowanie</h1>', $html);
    }
}
