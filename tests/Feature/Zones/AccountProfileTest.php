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

use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\Feature\Permissions\ZoneRolesTestCase;
use Tests\Fixtures\Zones\AlphaAccount;

/**
 * Profil konta: imię i nazwisko zmienia właściciel (adres e-mail tylko do odczytu), wybór języka tylko przy kilku
 * włączonych językach, organizacje konta z rolami i przełącznikiem w strefie z `teams`.
 */
class AccountProfileTest extends ZoneRolesTestCase
{
    public function test_owner_changes_the_name_but_not_the_e_mail(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/account/profile')->assertOk()
            ->assertSee('name="name"', false)
            ->assertSee('value="anna@example.com"', false)
            ->assertSee(text('account.email_hint'))
            ->assertDontSee('name="locale"', false);

        $this->put('/alpha/account/profile', ['name' => '  Anna Kowalska ', 'avatar_color' => 'teal', 'email' => 'evil@example.com'])
            ->assertRedirect('/alpha/account/profile')
            ->assertSessionHas('status', text('account.profile_saved'));

        $fresh = $account->fresh();
        $this->assertSame('Anna Kowalska', $fresh?->displayName());
        $this->assertSame('anna@example.com', $fresh->emailAddress());
        $this->assertArrayNotHasKey('locale', (array) $fresh->json_attributes);
    }

    public function test_name_is_required(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/account/profile');
        $this->put('/alpha/account/profile', ['name' => ' ', 'avatar_color' => 'teal'])->assertRedirect('/alpha/account/profile');

        $this->assertNotSame('', $this->sessionError('name'));
        $this->assertSame('Anna Nowak', $account->fresh()?->displayName());
    }

    public function test_language_outside_the_enabled_ones_is_rejected(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->signedIn($account, 'alpha');

        $this->get('/alpha/account/profile');
        $this->put('/alpha/account/profile', ['name' => 'Anna Nowak', 'avatar_color' => 'teal', 'locale' => 'en']);

        $this->assertNotSame('', $this->sessionError('locale'));
    }

    public function test_profile_lists_organizations_with_roles_and_a_switch(): void
    {
        $account = $this->account(AlphaAccount::class);
        $this->member($account, $this->organization('Pierwsza'), 'manager');
        $second = $this->organization('Druga');
        $this->member($account, $second, 'analyst', 'support');

        $html = (string) $this->signedIn($account, 'alpha')->get('/alpha/account/profile')->assertOk()->getContent();

        $this->assertStringContainsString(text('account.organizations_title'), $html);
        $this->assertStringContainsString('<span>Pierwsza</span>', $html);
        $this->assertStringContainsString(text('roles.alpha.manager'), $html);
        $this->assertStringContainsString(text('account.organization_current'), $html);
        $this->assertStringContainsString('value="' . $second->token . '"', $html);
    }

    public function test_role_labels_come_from_the_zone_package_texts(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $shop = $factory->make(['name' => 'shop', 'texts' => 'shop'], 'shop', __DIR__);

        $this->assertSame('shop::roles.shop.owner', $shop->textKey('roles.shop.owner'));
        $this->assertSame('roles.shop.owner', $factory->make(['name' => 'shop'], 'shop', __DIR__)->textKey('roles.shop.owner'));
        $this->assertSame(text('roles.admiro.owner'), $factory->make(['name' => 'admiro'], 'admiro', __DIR__)->roleLabel('owner'));
    }

    public function test_appearance_settings_offer_the_theme_and_only_the_zone_palettes(): void
    {
        $html = (string) $this->signedIn($this->account(AlphaAccount::class), 'alpha')->get('/alpha/account/appearance')->assertOk()->getContent();

        $this->assertStringContainsString('<h2 class="ss-card__title" id="appearance-theme-title">' . text('account.theme_title') . '</h2>', $html);
        $this->assertStringContainsString('data-ss-theme-toggle hidden', $html);
        $this->assertStringNotContainsString('id="appearance-palette-title"', $html);
        $this->assertMatchesRegularExpression('~href="' . preg_quote(url('/alpha/account/appearance'), '~') . '" title="[^"]+"\s+aria-current="page"~', $html);
    }
}
