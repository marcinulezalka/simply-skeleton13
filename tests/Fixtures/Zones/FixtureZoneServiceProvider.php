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

use Simplysmart\Admiro\Zones\ZoneServiceProvider;

/**
 * Strefa testowa: nazwa strefy jest też prefiksem nazw tras, a trasy leżą w routes/<strefa>/.
 */
abstract class FixtureZoneServiceProvider extends ZoneServiceProvider
{
    abstract protected function zoneName(): string;

    protected function routeNamePrefix(): string
    {
        return $this->zoneName();
    }

    protected function routesPath(): string
    {
        return __DIR__ . '/routes/' . $this->zoneName();
    }
}
