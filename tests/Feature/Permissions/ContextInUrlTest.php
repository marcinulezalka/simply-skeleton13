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

namespace Tests\Feature\Permissions;

use InvalidArgumentException;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\Models\ZoneAccount;
use Simplysmart\Admiro\Zones\ContextKeys;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaZoneServiceProvider;
use Tests\Fixtures\Zones\ContextAlphaZoneServiceProvider;
use Tests\Fixtures\Zones\OpenZoneServiceProvider;

/**
 * Kontekst konta w adresie (`context_in_url`, strefa testowa alpha w tym wariancie): klucz po slugu strefy wybiera
 * organizację i rolę bez ich nazw w adresie, jest inny dla każdego konta, cudzy klucz przekierowuje na własny pulpit,
 * klucz z poprzedniego `APP_KEY` — na ten sam adres z nowym kluczem, a przełącznik kontekstu to odnośniki z kluczami.
 */
class ContextInUrlTest extends ZoneRolesTestCase
{
    protected function zoneProviders(): array
    {
        return [ContextAlphaZoneServiceProvider::class, BetaZoneServiceProvider::class, OpenZoneServiceProvider::class];
    }

    public function test_each_role_has_its_own_key_and_the_key_picks_the_context(): void
    {
        $organization = $this->organization('Klinika Tajna');
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'guest', 'analyst');
        $analyst = $this->key($account, $organization->id, 'analyst');
        $guest = $this->key($account, $organization->id, 'guest');

        $this->assertNotSame($analyst, $guest);
        $this->assertMatchesRegularExpression('~^' . ContextKeys::PATTERN . '$~', $analyst);

        $this->signedIn($account, 'alpha')->get("/alpha/{$analyst}/reports")->assertOk();
        $this->get("/alpha/{$guest}/reports")->assertForbidden();
        $this->get("/alpha/{$analyst}/reports")->assertOk();
        $this->assertSame(url("/alpha/{$analyst}/dashboard"), route('alpha.dashboard'));
        $this->assertStringNotContainsString('Klinika', route('alpha.dashboard'));
        $this->assertStringNotContainsString('analyst', route('alpha.dashboard'));
    }

    public function test_key_belongs_to_one_account_and_a_foreign_one_leads_to_the_own_dashboard(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');
        $other = $this->member($this->account(AlphaAccount::class, ['email' => 'other@example.com']), $organization, 'analyst');

        $this->assertNotSame($this->key($account, $organization->id, 'analyst'), $this->key($other, $organization->id, 'analyst'));

        $this->signedIn($account, 'alpha')->get('/alpha/' . $this->key($other, $organization->id, 'analyst') . '/reports')
            ->assertRedirect(url('/alpha/' . $this->key($account, $organization->id, 'analyst') . '/dashboard'));
        $this->get('/alpha/0000000000/reports')->assertRedirect(url('/alpha/' . $this->key($account, $organization->id, 'analyst') . '/dashboard'));
    }

    public function test_key_from_the_previous_app_key_redirects_to_the_same_page_with_the_new_key(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');
        $old = $this->key($account, $organization->id, 'analyst');

        config(['app.previous_keys' => [config()->string('app.key')], 'app.key' => 'base64:' . base64_encode(random_bytes(32))]);
        $new = $this->key($account, $organization->id, 'analyst');

        $this->assertNotSame($old, $new);
        $this->signedIn($account, 'alpha')->get("/alpha/{$old}/reports")->assertRedirect(url("/alpha/{$new}/reports"));
    }

    public function test_context_switch_offers_links_with_keys_and_account_pages_have_no_key(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'guest', 'analyst');

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString('href="' . url('/alpha/' . $this->key($account, $organization->id, 'guest') . '/dashboard') . '"', $html);
        $this->assertStringContainsString('href="' . url('/alpha/' . $this->key($account, $organization->id, 'analyst') . '/dashboard') . '"', $html);
        $this->assertStringNotContainsString('name="context"', $html);
    }

    public function test_switching_the_role_redirects_to_the_dashboard_of_the_new_context(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'guest', 'analyst');

        $this->signedIn($account, 'alpha')->post('/alpha/account/role', ['role' => 'guest'])
            ->assertRedirect(url('/alpha/' . $this->key($account, $organization->id, 'guest') . '/dashboard'));
    }

    public function test_context_in_url_needs_role_contexts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zone [shop] context_in_url requires role_contexts.');

        $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop', 'model' => AlphaAccount::class, 'context_in_url' => true], 'shop', __DIR__);
    }

    private function key(ZoneAccount $account, int $organizationId, string $role): string
    {
        return $this->app->make(ContextKeys::class)->key($this->app->make(ZoneRegistry::class)->get('alpha'), $account, $organizationId, $role);
    }
}
