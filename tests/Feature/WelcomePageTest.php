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

use Tests\TestCase;

/**
 * Strona startowa `/` (stockowa trasa view('welcome')) pochodzi z Core: logo simplysmart, teksty
 * w języku aplikacji i style marki osadzone inline, bez akcji i bez stockowej strony Laravela.
 */
class WelcomePageTest extends TestCase
{
    public function test_home_page_is_the_simplysmart_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="simplysmart"', false);
        $response->assertSee(text('welcome.title'));
        $response->assertSee(text('welcome.features.dictionaries'));
        $response->assertSee('.ss-welcome__card', false);
        $response->assertDontSee('laravel.com/docs', false);
        $response->assertDontSee('<form', false);
    }
}
