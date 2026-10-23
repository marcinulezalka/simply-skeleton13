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

use Simplysmart\Mainboard\View\BrandNames;
use Simplysmart\Mainboard\View\CoreVersion;
use Tests\TestCase;

/**
 * Strona startowa `/` (stockowa trasa view('welcome')) pochodzi z Core: logo simplysmart, funkcje jako
 * ikona, tytuł i opis, wyróżnione nazwy simplysmart i mainboard, stopka z logo Laravela i wersją mainboard, teksty w języku aplikacji i style marki osadzone
 * inline, bez akcji i bez stockowej strony Laravela.
 */
class WelcomePageTest extends TestCase
{
    public function test_home_page_is_the_simplysmart_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="simplysmart"', false);
        $response->assertSee(BrandNames::highlight(text('welcome.title'))->toHtml(), false);
        $response->assertSee(text('welcome.features.dictionaries.title'));
        $response->assertSee('.ss-welcome__card', false);
        $response->assertSee('class="ss-icon ss-icon--tile"', false);
        $response->assertSee('aria-label="Laravel"', false);
        $response->assertSee(text('welcome.mainboard_version', ['version' => CoreVersion::current()]));
        $response->assertDontSee('laravel.com/docs', false);
        $response->assertDontSee('<form', false);
        $response->assertSee('<strong class="ss-brand-name">mainboard</strong>', false);
        $response->assertDontSee('simplysmart/mainboard', false);
    }

    public function test_brand_names_are_highlighted_after_escaping(): void
    {
        $html = BrandNames::highlight('Pakiet mainboard <b>x</b> & simplysmart, ale nie mainboards')->toHtml();

        $this->assertSame('Pakiet <strong class="ss-brand-name">mainboard</strong> &lt;b&gt;x&lt;/b&gt; &amp; <strong class="ss-brand-name">simplysmart</strong>, ale nie mainboards', $html);
    }
}
