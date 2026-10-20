<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

/**
 * Strefa testowa open: publiczna, bez logowania.
 */
class OpenZoneServiceProvider extends FixtureZoneServiceProvider
{
    protected function zoneName(): string
    {
        return 'open';
    }

    protected function zoneConfig(): array
    {
        return ['name' => 'open'];
    }
}
