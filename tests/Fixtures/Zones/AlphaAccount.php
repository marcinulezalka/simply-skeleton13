<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

use Illuminate\Foundation\Auth\User;

/**
 * Konto strefy testowej alpha.
 */
class AlphaAccount extends User
{
    protected $table = 'alpha_accounts';
}
