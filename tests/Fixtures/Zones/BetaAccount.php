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

use Simplysmart\Admiro\Models\ZoneAccount;

/**
 * Konto strefy testowej beta.
 */
class BetaAccount extends ZoneAccount
{
    protected $table = 'beta_accounts';
}
