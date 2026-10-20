<?php

declare(strict_types=1);

namespace Tests\Fixtures\Zones;

use Illuminate\Foundation\Auth\User;

/**
 * Konto strefy testowej beta.
 */
class BetaAccount extends User
{
    protected $table = 'beta_accounts';
}
