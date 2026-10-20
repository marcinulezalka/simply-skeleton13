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
 * Jeden włączony język: trasy stref bez prefiksu {locale}, język domyślny.
 */
class SingleLocaleRoutingTest extends ZoneTestCase
{
    protected function configureZones(): void
    {
        config(['mainboard.locale.enabled' => ['pl']]);
    }

    public function test_zone_routes_have_no_locale_prefix(): void
    {
        $this->get('/alpha/locale')->assertOk()->assertContent('pl');
        $this->get('/pl/alpha/locale')->assertNotFound();
    }
}
