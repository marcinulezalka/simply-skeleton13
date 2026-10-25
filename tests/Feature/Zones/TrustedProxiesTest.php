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

namespace Tests\Feature\Zones;

use Illuminate\Http\Middleware\TrustProxies;
use Simplysmart\Admiro\Security\TrustedProxies;

/**
 * IP klienta z X-Forwarded-For tylko od proxy z admiro.security.trusted_proxies.
 */
class TrustedProxiesTest extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['admiro.security.trusted_proxies' => '10.0.0.1']);
        $this->app->make(TrustedProxies::class)->apply();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }

    public function test_forwarded_ip_is_used_behind_a_trusted_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/open/ip')
            ->assertContent('203.0.113.5');
    }

    public function test_forwarded_ip_is_ignored_from_an_untrusted_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeader('X-Forwarded-For', '203.0.113.5')
            ->get('/open/ip')
            ->assertContent('10.0.0.2');
    }
}
