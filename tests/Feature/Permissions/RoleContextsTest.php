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

use Simplysmart\Admiro\Contracts\CurrentOrganization;
use Simplysmart\Admiro\Contracts\CurrentRole;
use Simplysmart\Admiro\Permissions\RoleSelector;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;
use Simplysmart\Admiro\Zones\ZoneContexts;

/**
 * Role jako konteksty (`role_contexts`, strefa testowa alpha): konto z kilkoma rolami w organizacji działa w jednej
 * aktywnej roli (domyślnie pierwszej, zapamiętanej w sesji), jego uprawnienia są zawężone do niej, przełącza się
 * formularzem z layoutu (przekierowanie na pulpit), a rola, której nie ma, to 404.
 */
class RoleContextsTest extends ZoneRolesTestCase
{
    public function test_active_role_narrows_the_permissions_and_the_switch_redirects_to_the_dashboard(): void
    {
        $account = $this->member($this->account(AlphaAccount::class), $this->organization(), 'guest', 'analyst');

        $this->signedIn($account, 'alpha')->get('/alpha/reports')->assertForbidden();

        $this->post('/alpha/account/role', ['role' => 'analyst'])->assertRedirect('/alpha/dashboard');
        $this->get('/alpha/reports')->assertOk();

        $this->post('/alpha/account/role', ['role' => 'guest'])->assertRedirect('/alpha/dashboard');
        $this->get('/alpha/reports')->assertForbidden();
    }

    public function test_role_the_account_does_not_have_cannot_be_chosen(): void
    {
        $account = $this->member($this->account(AlphaAccount::class), $this->organization(), 'guest', 'analyst');

        $this->signedIn($account, 'alpha')->post('/alpha/account/role', ['role' => 'hero'])->assertNotFound();
        $this->get('/alpha/reports')->assertForbidden();
    }

    public function test_role_granted_during_the_session_does_not_change_the_context(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');

        $this->signedIn($account, 'alpha')->get('/alpha/reports')->assertOk();
        $this->member($account, $organization, 'guest');

        $this->get('/alpha/reports')->assertOk();
    }

    public function test_layout_offers_every_role_including_a_single_current_one(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'analyst');

        $this->signedIn($account, 'alpha')->get('/alpha/account/profile')->assertOk()
            ->assertSee('name="' . ZoneContexts::FIELD . '" value="' . ZoneContexts::value($organization, 'analyst') . '"', false);

        $this->member($account, $organization, 'guest');
        $html = (string) $this->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__group"><span class="ss-context-switch__kind"\s*title="' . preg_quote(text('account.context_kinds.organization'), '~') . '"\s*>.*?</span>' . preg_quote($organization->name, '~') . '</p>~s', $html);
        $this->assertStringContainsString('<input type="hidden" name="' . ZoneContexts::FIELD . '" value="' . ZoneContexts::value($organization, 'guest') . '">', $html);
        $this->assertMatchesRegularExpression('~name="' . ZoneContexts::FIELD . '" value="' . preg_quote(ZoneContexts::value($organization, 'analyst'), '~') . '">\s*<button class="ss-account-menu__link" type="submit"\s+aria-current="true"\s*>~', $html);
    }

    public function test_active_role_applies_only_to_its_account_and_organization(): void
    {
        $organization = $this->organization();
        $account = $this->member($this->account(AlphaAccount::class), $organization, 'guest', 'analyst');
        $other = $this->member($this->account(AlphaAccount::class, ['email' => 'other@example.com']), $organization, 'guest', 'analyst');
        $role = $this->app->make(CurrentRole::class);
        $role->set($account, $organization->id, 'guest');

        $this->app->make(CurrentOrganization::class)->within($organization->id, function () use ($account, $other, $role, $organization): void {
            $this->assertFalse($account->forgetZonePermissions()->hasPermissionTo('reports.view', 'alpha'));
            $this->assertTrue($other->forgetZonePermissions()->hasPermissionTo('reports.view', 'alpha'));
            $this->assertNull($role->of($account, $organization->id + 1));
        });

        $role->clear();
        $this->assertNull($role->of($account, $organization->id));
    }

    public function test_role_contexts_need_a_zone_with_teams(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);

        $this->assertTrue($factory->make(['name' => 'shop', 'model' => AlphaAccount::class, 'teams' => true, 'role_contexts' => true], 'shop', __DIR__)->roleContexts);
        $this->assertFalse($factory->make(['name' => 'shop', 'model' => BetaAccount::class, 'role_contexts' => true], 'shop', __DIR__)->roleContexts);
        $this->assertSame([], $this->app->make(RoleSelector::class)->choices($factory->make(['name' => 'shop', 'model' => BetaAccount::class], 'shop', __DIR__), $this->account(BetaAccount::class), 1));
    }
}
