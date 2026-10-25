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

use Illuminate\Support\Facades\Mail;
use Simplysmart\Admiro\Mail\LoginCodeMail;
use Simplysmart\Admiro\Security\Honeypot\FormHoneypot;
use Simplysmart\Admiro\Security\Honeypot\IpBans;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Pułapki na boty: fałszywe logowanie pod adresami sprawdzanymi przez boty (bez sesji), ban po POST od razu,
 * po GET dopiero po progu, biała lista, blokada zbanowanego adresu na każdej trasie, pułapka w formularzu
 * logowania (ukryte pole, minimalny czas) i zdjęcie bana komendą.
 */
class HoneypotTest extends ZoneTestCase
{
    private const string BOT = '203.0.113.7';

    /**
     * @var array<string, string>
     */
    private const array ENV = ['HONEYPOT_ROUTES' => 'true', 'HONEYPOT_FORMS' => 'true', 'HONEYPOT_WHITELIST' => '198.51.100.0/24'];

    protected function setUp(): void
    {
        foreach (self::ENV as $name => $value) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        parent::setUp();

        Mail::fake();
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::ENV) as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        parent::tearDown();
    }

    public function test_trap_shows_a_fake_sign_in_without_a_session(): void
    {
        $response = $this->fromBot()->get('/wp-login.php');

        $response->assertOk()->assertSee('name="email"', false);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertFalse($this->bans()->isBanned(self::BOT));
    }

    public function test_submitting_the_trap_bans_at_once(): void
    {
        $this->fromBot()->post('/admin/login', ['email' => 'root@example.com', 'password' => 'secret'])
            ->assertOk()
            ->assertSee(text('sign_in.failure'));

        $this->assertTrue($this->bans()->isBanned(self::BOT));
        $this->fromBot()->get('/alpha')->assertForbidden();
    }

    public function test_visits_ban_only_after_the_threshold(): void
    {
        foreach (range(1, 4) as $visit) {
            $this->fromBot()->get('/.env');
        }

        $this->assertFalse($this->bans()->isBanned(self::BOT));

        $this->fromBot()->get('/.env');

        $this->assertTrue($this->bans()->isBanned(self::BOT));
    }

    public function test_whitelisted_address_is_never_banned(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->post('/wp-login.php');

        $this->assertFalse($this->bans()->isBanned('198.51.100.20'));
    }

    public function test_cache_keys_hold_only_a_hash_of_the_address(): void
    {
        $this->assertStringNotContainsString(self::BOT, IpBans::key('ban', self::BOT));
        $this->assertStringStartsWith('zone:shared:honeypot:ban:', IpBans::key('ban', self::BOT));
    }

    public function test_unban_command_lifts_the_ban(): void
    {
        $this->fromBot()->post('/wp-login.php');

        $this->command('admiro:honeypot:unban', ['ip' => self::BOT])->assertSuccessful();

        $this->assertFalse($this->bans()->isBanned(self::BOT));
    }

    public function test_sign_in_form_carries_the_trap_fields(): void
    {
        $this->get('/alpha/signin')
            ->assertSee('name="' . FormHoneypot::TRAP_FIELD . '"', false)
            ->assertSee('name="' . FormHoneypot::TIMESTAMP_FIELD . '"', false);
    }

    public function test_filled_trap_field_looks_like_a_normal_answer_but_sends_nothing(): void
    {
        $this->account(AlphaAccount::class);
        $token = $this->app->make(FormHoneypot::class)->token();
        $this->travel(5)->seconds();

        $this->fromBot()->post('/alpha/signin', ['email' => 'anna@example.com', FormHoneypot::TRAP_FIELD => 'https://spam.example', FormHoneypot::TIMESTAMP_FIELD => $token])
            ->assertRedirect('/alpha/signin/code');

        Mail::assertNothingSent();
        $this->assertTrue($this->bans()->isBanned(self::BOT));
    }

    public function test_too_fast_or_missing_timestamp_sends_nothing(): void
    {
        $this->account(AlphaAccount::class);

        $this->post('/alpha/signin', ['email' => 'anna@example.com'])->assertRedirect('/alpha/signin/code');
        $this->post('/alpha/signin', ['email' => 'anna@example.com', FormHoneypot::TIMESTAMP_FIELD => $this->app->make(FormHoneypot::class)->token()]);

        Mail::assertNothingSent();
    }

    public function test_human_pace_sends_the_code(): void
    {
        $this->account(AlphaAccount::class);
        $token = $this->app->make(FormHoneypot::class)->token();
        $this->travel(5)->seconds();

        $this->post('/alpha/signin', ['email' => 'anna@example.com', FormHoneypot::TIMESTAMP_FIELD => $token])->assertRedirect('/alpha/signin/code');

        Mail::assertSent(LoginCodeMail::class, 1);
    }

    private function fromBot(): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::BOT]);
    }

    private function bans(): IpBans
    {
        return $this->app->make(IpBans::class);
    }
}
