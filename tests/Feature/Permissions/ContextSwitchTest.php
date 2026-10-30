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
 * Przełącznik kontekstu (Zones\ZoneContexts): każda organizacja i departament konta z jego rolami w nich, a jedno
 * kliknięcie (account.context.switch) ustawia organizację i rolę z jej uprawnieniami.
 */
class ContextSwitchTest extends ZoneRolesTestCase
{
    public function test_contexts_list_every_organization_and_department_with_the_roles_of_the_account(): void
    {
        $management = $this->app->make(OrganizationManagement::class);
        $unit = $management->create(new OrganizationData('Sklep'));
        $department = $management->createDepartment(new OrganizationData('Magazyn'), $unit);
        $account = $this->member($this->account(AlphaAccount::class), $unit, 'guest', 'analyst');
        $this->member($account, $department, 'analyst');

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/security')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__group"><span class="ss-context-switch__kind"\s*title="' . preg_quote(text('account.context_kinds.organization'), '~') . '"\s*>.*?</span>' . preg_quote('Sklep', '~') . '</p>~s', $html);
        $this->assertStringContainsString('d="M7.237 3.237A1.75', $html, 'The organization group carries the solid context icon.');
        $this->assertMatchesRegularExpression('~<p class="ss-context-switch__group"><span class="ss-context-switch__kind"\s*title="' . preg_quote(text('account.context_kinds.department'), '~') . '"\s*>.*?</span>' . preg_quote('Sklep · Magazyn', '~') . '</p>~s', $html);
        $this->assertStringContainsString('name="' . ZoneContexts::FIELD . '" value="' . ZoneContexts::value($department, 'analyst') . '"', $html);
        $this->assertSame(3, substr_count($html, 'name="' . ZoneContexts::FIELD . '"'));
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
