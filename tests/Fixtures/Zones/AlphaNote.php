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

use Simplysmart\Mainboard\Models\OrganizationDataModel;

/**
 * Rekord organizacji w strefie alpha (tabela `alpha_notes` tworzona w teście), wiązany w trasach po `token`
 * tylko w bieżącej organizacji.
 *
 * @property string $title
 */
class AlphaNote extends OrganizationDataModel
{
    public const string TABLE = 'alpha_notes';

    protected $table = self::TABLE;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'title' => 'string'];
    }
}
