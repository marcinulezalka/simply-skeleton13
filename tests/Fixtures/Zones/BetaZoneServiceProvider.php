<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

/**
 * Strefa testowa beta z logowaniem.
 */
class BetaZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'beta';
    }

    protected function zoneConfig(): array
    {
        return ['name' => 'beta', 'model' => BetaAccount::class];
    }
}
