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

/**
 * Kilka włączonych języków: prefiks {locale} zawsze obecny i ograniczony do listy, parametr
 * zdjęty z trasy przed kontrolerem, a route() dostaje język bieżącego żądania.
 */
class MultiLocaleRoutingTest extends ZoneTestCase
{
    protected function configureZones(): void
    {
        config(['admiro.locale.enabled' => ['pl', 'en']]);
    }

    public function test_locale_comes_from_the_prefix(): void
    {
        $this->get('/en/alpha/locale')->assertOk()->assertContent('en');
        $this->get('/pl/alpha/locale')->assertOk()->assertContent('pl');
    }

    public function test_prefix_is_required_and_limited_to_enabled_locales(): void
    {
        $this->get('/alpha/locale')->assertNotFound();
        $this->get('/de/alpha/locale')->assertNotFound();
    }

    public function test_route_parameters_reach_the_action_without_the_locale(): void
    {
        $this->get('/en/alpha/items/abc')->assertOk()->assertContent('abc');
    }

    public function test_generated_urls_keep_the_current_locale(): void
    {
        $this->get('/en/alpha/home-url')->assertOk()->assertContent(url('/en/alpha'));
    }
}
