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

use Simplysmart\Admiro\Organizations\OrganizationData;
use Simplysmart\Admiro\Organizations\OrganizationManagement;
use Simplysmart\Admiro\Zones\ZoneContexts;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Przełącznik kontekstu (Zones\ZoneContexts): organizacje i departamenty, w których konto ma role (z ikoną budynku,
 * rola administracyjna z wypełnionym ludzikiem), bez członkostw bez ról, a jedno kliknięcie (account.context.switch)
 * ustawia organizację i rolę z jej uprawnieniami.
 */
class ContextSwitchTest extends ZoneRolesTestCase
{
    public function test_contexts_list_only_organizations_and_departments_where_the_account_has_roles(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $unit = $management->create(new OrganizationData('Sklep'));
        $department = $management->createDepartment(new OrganizationData('Magazyn'), $unit);
        $grouping = $management->createDepartment(new OrganizationData('Zaplecze'), $unit);
        $account = $this->member($this->account(AlphaAccount::class), $unit, 'guest', 'owner');
        $this->member($account, $department, 'analyst');
        $this->member($account, $grouping);

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/security')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__group"><span class="ss-context-switch__kind"\s*title="' . preg_quote(text('account.context_kinds.organization'), '~') . '"\s*>.*?</span>' . preg_quote('Sklep', '~') . '</p>~s', $html);
        $this->assertStringContainsString('<path d="M6 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16"/>', $html, 'The organization group carries the building icon.');
        $this->assertSame(1, substr_count($html, '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2z"/>'), 'Only the administrative role (owner) carries the filled user icon.');
        $this->assertStringNotContainsString('Zaplecze', $html);
        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__group"><span class="ss-context-switch__kind"\s*title="' . preg_quote(text('account.context_kinds.department'), '~') . '"\s*>.*?</span>' . preg_quote('Sklep · Magazyn', '~') . '</p>~s', $html);
        $this->assertStringContainsString('name="' . ZoneContexts::FIELD . '" value="' . ZoneContexts::value($department, 'analyst') . '"', $html);
        $this->assertSame(3, substr_count($html, 'name="' . ZoneContexts::FIELD . '"'));
        $this->assertStringContainsString('name="' . ZoneContexts::FIELD . '" value="' . ZoneContexts::value($unit, 'owner') . '"', $html);
    }

    public function test_one_click_sets_the_organization_and_the_role_with_its_permissions(): void
    {
        $first = $this->organization('Pierwsza');
        $second = $this->organization('Druga');
        $account = $this->member($this->account(AlphaAccount::class), $first, 'guest');
        $this->member($account, $second, 'guest', 'analyst');

        $this->signedIn($account, 'alpha')->post('/alpha/account/context', [ZoneContexts::FIELD => ZoneContexts::value($second, 'analyst')])->assertRedirect('/alpha/dashboard');
        $this->get('/alpha/reports')->assertOk();

        $this->post('/alpha/account/context', [ZoneContexts::FIELD => ZoneContexts::value($second, 'guest')])->assertRedirect('/alpha/dashboard');
        $this->get('/alpha/reports')->assertForbidden();
    }

    public function test_foreign_organization_or_role_is_not_found(): void
    {
        $own = $this->organization('Moja');
        $account = $this->member($this->account(AlphaAccount::class), $own, 'guest');
        $this->member($account, $this->organization('Druga'), 'guest');

        $this->signedIn($account, 'alpha')->post('/alpha/account/context', [ZoneContexts::FIELD => ZoneContexts::value($this->organization('Obca'), 'guest')])->assertNotFound();
        $this->post('/alpha/account/context', [ZoneContexts::FIELD => ZoneContexts::value($own, 'analyst')])->assertNotFound();
        $this->post('/alpha/account/context', [ZoneContexts::FIELD => 'bez-dwukropka'])->assertNotFound();
    }
}
