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

namespace Tests\Feature\Admiro;

use Simplysmart\Admiro\Models\AdmiroAccount;
use Simplysmart\Mainboard\Audit\Events\AdminActionPerformed;
use Simplysmart\Mainboard\Auth\AccountActor;
use Simplysmart\Mainboard\Contracts\InterfaceLocales;
use Simplysmart\Mainboard\Database\Seeders\OperatorOrganizationSeeder;
use Simplysmart\Mainboard\Models\AuditEvent;
use Simplysmart\Mainboard\Models\Dictionaries\Country;
use Simplysmart\Mainboard\Models\Dictionaries\Currency;
use Simplysmart\Mainboard\Models\Dictionaries\Language;
use Simplysmart\Mainboard\Models\Organization;
use Simplysmart\Mainboard\Permissions\SystemRoleGrant;
use Tests\Feature\Zones\ZoneTestCase;

/**
 * Dziennik zdarzeń (`audit.view`, filtry) i ustawienia systemu (`settings.view` podgląd, `settings.manage`
 * zmiana kraju, waluty, języka domyślnego i języków interfejsu w granicach APP_LOCALES, w dzienniku).
 */
class AdmiroAuditAndSettingsTest extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mainboard.locale.enabled' => ['pl', 'en']]);
        $this->seed(OperatorOrganizationSeeder::class);
        $this->command('mainboard:permissions:sync', ['--zone' => 'admiro'])->assertSuccessful();

        foreach (['PL' => ['POL', '616'], 'DE' => ['DEU', '276']] as $alpha2 => [$alpha3, $numeric]) {
            (new Country())->forceFill(['name' => $alpha2, 'alpha_code_2' => $alpha2, 'alpha_code_3' => $alpha3, 'numeric_code' => $numeric, 'is_active' => true, 'is_default' => $alpha2 === 'PL'])->save();
        }

        foreach (['PLN' => '985', 'EUR' => '978'] as $alpha3 => $numeric) {
            (new Currency())->forceFill(['name' => $alpha3, 'alpha_code_3' => $alpha3, 'numeric_code' => $numeric, 'is_active' => true, 'is_default' => $alpha3 === 'PLN'])->save();
        }

        foreach (['pl', 'en'] as $code) {
            (new Language())->forceFill(['name' => $code, 'code' => $code, 'is_interface' => $code === 'pl', 'is_default' => $code === 'pl', 'is_active' => true])->save();
        }

        $this->app->make(InterfaceLocales::class)->forget();
    }

    public function test_audit_log_lists_and_filters_events(): void
    {
        event(new AdminActionPerformed('organization created', new AccountActor('admiro', 1), organizationId: 5));
        event(new AdminActionPerformed('settings updated', new AccountActor('admiro', 1), 'alpha'));

        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro')
            ->get('/admiro/audit')->assertOk()->assertSee('organization created')->assertSee('settings updated');
        $this->get('/admiro/audit?zone=alpha')->assertSee('settings updated')->assertDontSee('<td>organization created</td>', false);
        $this->signedIn($this->admin('guest@example.com'), 'admiro')->get('/admiro/audit')->assertForbidden();
    }

    public function test_support_views_settings_but_cannot_change_them(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro')->get('/admiro/settings')->assertOk()->assertSee('disabled', false);

        $this->put('/admiro/settings', $this->settings())->assertForbidden();
    }

    public function test_owner_changes_the_defaults_and_interface_languages(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')
            ->put('/admiro/settings', $this->settings())
            ->assertRedirect('/admiro/settings');

        $this->assertTrue(Country::query()->where('alpha_code_2', 'DE')->sole()->is_default);
        $this->assertFalse(Country::query()->where('alpha_code_2', 'PL')->sole()->is_default);
        $this->assertTrue(Currency::query()->where('alpha_code_3', 'EUR')->sole()->is_default);
        $this->assertSame(['pl', 'en'], $this->app->make(InterfaceLocales::class)->enabled());
        $this->assertSame('en', $this->app->make(InterfaceLocales::class)->default());
        $this->assertSame('settings updated', AuditEvent::query()->sole()->event);
    }

    public function test_default_language_must_be_an_interface_language(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro')
            ->put('/admiro/settings', [...$this->settings(), 'interface' => ['pl']]);

        $this->assertSessionError('settings', text('admiro::admin.settings_rejected'));
        $this->assertSame('pl', $this->app->make(InterfaceLocales::class)->default());
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return ['country' => 'DE', 'currency' => 'EUR', 'language' => 'en', 'interface' => ['pl', 'en']];
    }

    private function admin(string $email, ?string $role = null): AdmiroAccount
    {
        $admin = $this->account(AdmiroAccount::class, ['email' => $email, 'name' => $email], '159357');
        $this->app->make(SystemRoleGrant::class)->grant($admin, Organization::query()->findOrFail(Organization::OPERATOR_ID), $role === null ? [] : [$role]);

        return $admin;
    }
}
