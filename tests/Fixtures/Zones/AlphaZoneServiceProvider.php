<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

/**
 * Strefa testowa alpha z logowaniem.
 */
class AlphaZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'alpha';
    }

    protected function zoneConfig(): array
    {
        return ['name' => 'alpha', 'model' => AlphaAccount::class];
    }
}
