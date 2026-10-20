<?php

declare(strict_types=1);

namespace Tests\Feature\Zones;

use Simplysmart\Mainboard\Contracts\CurrentZone;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Izolacja stref: własne ciasteczko i tabela sesji, osobny guard, brak współdzielonego tokenu XSRF
 * i brak stanu strefy po zakończeniu żądania.
 */
class ZoneIsolationTest extends ZoneTestCase
{
    public function test_login_zone_uses_its_own_strict_secure_session_cookie(): void
    {
        $cookie = $this->get('/alpha')->assertOk()->getCookie('alpha_session', false);

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($cookie->isSecure());
        $this->assertSame('strict', $cookie->getSameSite());
    }

    public function test_public_zone_uses_its_own_lax_session_cookie(): void
    {
        $cookie = $this->get('/open')->assertOk()->getCookie('open_session', false);

        $this->assertNotNull($cookie);
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_zone_session_settings_apply_only_during_the_request(): void
    {
        $base = config('session');

        $this->get('/alpha/session')->assertOk()->assertJson([
            'cookie' => 'alpha_session',
            'table' => 'alpha_sessions',
            'lifetime' => 30,
            'expire_on_close' => true,
            'encrypt' => true,
        ]);

        $this->assertSame($base, config('session'));
    }

    public function test_zones_do_not_set_the_shared_xsrf_cookie(): void
    {
        $this->get('/alpha')->assertCookieMissing('XSRF-TOKEN');
    }

    public function test_zone_account_reaches_its_zone_routes(): void
    {
        $this->actingAs($this->alphaAccount(), 'alpha')
            ->get('/alpha/dashboard')
            ->assertOk()
            ->assertSee('alpha dashboard:7');
    }

    public function test_zone_account_is_a_guest_in_another_zone(): void
    {
        $this->actingAs($this->alphaAccount(), 'alpha')
            ->get('/beta/dashboard')
            ->assertNotFound();
    }

    public function test_guest_gets_not_found_while_the_zone_has_no_sign_in_route(): void
    {
        $this->get('/alpha/dashboard')->assertNotFound();
    }

    public function test_the_current_zone_does_not_outlive_the_request(): void
    {
        $this->get('/alpha')->assertOk();

        $this->assertFalse($this->app->make(CurrentZone::class)->has());
        $this->assertSame('web', $this->app->make('auth')->getDefaultDriver());
    }

    private function alphaAccount(): AlphaAccount
    {
        return (new AlphaAccount())->forceFill(['id' => 7]);
    }
}
