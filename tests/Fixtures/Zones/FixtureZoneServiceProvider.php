<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

use Simplysmart\Mainboard\Zones\ZoneServiceProvider;

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
