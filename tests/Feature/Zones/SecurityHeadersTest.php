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

/**
 * Nagłówki bezpieczeństwa we wszystkich strefach i dodatkowe nagłówki stref z logowaniem.
 */
class SecurityHeadersTest extends ZoneTestCase
{
    public function test_every_zone_sends_the_base_security_headers(): void
    {
        $this->get('/open')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', config('mainboard.security.permissions_policy'))
            ->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_csp_carries_the_nonce_given_to_vite(): void
    {
        $policy = (string) $this->get('/open')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9]+'/", $policy);
        $this->assertStringNotContainsString('{nonce}', $policy);
    }

    public function test_csp_can_be_switched_off(): void
    {
        config(['mainboard.security.csp.enabled' => false]);

        $this->get('/open')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_hsts_is_sent_only_over_https(): void
    {
        $this->get('http://localhost/open')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/open')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_login_zone_pages_are_not_cached_or_indexed(): void
    {
        $response = $this->get('/alpha');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', config('mainboard.zones.robots'));
    }
}
