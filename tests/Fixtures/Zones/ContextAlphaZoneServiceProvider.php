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
 * Strefa testowa alpha z kontekstem konta w adresie (`context_in_url`): te same konta, organizacje i trasy co alpha,
 * strony dla zalogowanych pod `/alpha/<klucz>/...`.
 */
final class ContextAlphaZoneServiceProvider extends AlphaZoneServiceProvider
{
    protected function zoneConfig(): array
    {
        return [...parent::zoneConfig(), 'context_in_url' => true];
    }
}
