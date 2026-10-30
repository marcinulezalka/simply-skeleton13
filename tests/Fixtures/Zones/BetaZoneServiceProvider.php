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

namespace Tests\Fixtures\Zones;

/**
 * Strefa testowa beta z logowaniem, zawsze z kodem z e-maila (bez zaufanych urządzeń), z wyglądem zapisywanym w koncie
 * (domyślnie wyśrodkowana treść).
 */
class BetaZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'beta';
    }

    protected function zoneConfig(): array
    {
        return ['name' => 'beta', 'model' => BetaAccount::class, 'auth' => ['trusted_devices' => ['enabled' => false]], 'appearance' => ['account' => true, 'defaults' => ['layout-width' => 'centered', 'theme' => null]]];
    }
}
