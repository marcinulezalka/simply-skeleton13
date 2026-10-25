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
use Simplysmart\Admiro\Contracts\InterfaceLocales;
use Simplysmart\Admiro\Database\Seeders\OperatorOrganizationSeeder;
use Simplysmart\Admiro\Models\Dictionaries\Country;
use Simplysmart\Admiro\Models\Dictionaries\Currency;
use Simplysmart\Admiro\Models\Dictionaries\Language;
use Simplysmart\Admiro\Models\Organization;
use Simplysmart\Admiro\Permissions\SystemRoleGrant;
use Tests\Feature\Zones\ZoneTestCase;

/**
 * Baza testów panelu administratora: organizacja operatora, role strefy admiro z pliku Core, administratorzy
 * z rolami i małe słowniki lokalizacji (PL/DE, PLN/EUR, pl/en w APP_LOCALES).
 */
abstract class AdmiroTestCase extends ZoneTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(OperatorOrganizationSeeder::class);
        $this->command('admiro:permissions:sync', ['--zone' => 'admiro'])->assertSuccessful();
    }

    /**
     * Administrator z PIN-em i (opcjonalnie) rolą w organizacji operatora.
     */
    protected function admin(string $email, ?string $role = null): AdmiroAccount
    {
        $admin = $this->account(AdmiroAccount::class, ['email' => $email, 'name' => $email], '159357');
        $this->app->make(SystemRoleGrant::class)->grant($admin, Organization::query()->findOrFail(Organization::OPERATOR_ID), $role === null ? [] : [$role]);

        return $admin;
    }

    protected function seedDictionaries(): void
    {
        config(['admiro.locale.enabled' => ['pl', 'en']]);

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
}
