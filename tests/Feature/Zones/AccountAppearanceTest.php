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

use InvalidArgumentException;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\Feature\Permissions\ZoneRolesTestCase;
use Tests\Fixtures\Zones\AlphaAccount;
use Tests\Fixtures\Zones\BetaAccount;

/**
 * Wygląd zapisany w koncie (strefa z `appearance.account`, tu beta z domyślnie wyśrodkowaną treścią): domyślny wygląd
 * aplikacji rysowany na <html>, wybory konta go nadpisują, przywrócenie wraca do domyślnego; strefa bez tej opcji
 * zostawia wybory przeglądarce. Strefa bez ról trzyma jeden wygląd konta (klucz `*`); osobny wygląd każdej roli
 * sprawdzają testy stref z `role_contexts`.
 */
class AccountAppearanceTest extends ZoneRolesTestCase
{
    private const string PAGE = '/beta/account/appearance';

    public function test_page_draws_the_application_default_and_saves_to_the_account(): void
    {
        $this->signedIn($this->account(BetaAccount::class), 'beta');

        $this->get(self::PAGE)->assertOk()
            ->assertSee('<meta name="ss-appearance" content="' . url(self::PAGE) . '">', false)
            ->assertSee('data-layout-width="centered">', false)
            ->assertSee(text('account.appearance_storage_account'))
            ->assertSee(text('account.appearance_reset'));
    }

    public function test_account_choices_override_the_application_default(): void
    {
        $account = $this->account(BetaAccount::class);
        $this->signedIn($account, 'beta');

        $this->putJson(self::PAGE, ['theme' => 'dark', 'layout-width' => 'full'])->assertNoContent();
        $this->putJson(self::PAGE, ['text-size' => 'large'])->assertNoContent();

        $this->assertSame(['*' => ['theme' => 'dark', 'layout-width' => 'full', 'text-size' => 'large']], $account->fresh()?->json_attributes['appearance'] ?? null);
        $html = (string) $this->get(self::PAGE)->getContent();
        $this->assertMatchesRegularExpression('~<html lang="[^"]+" data-theme="dark" data-text-size="large">~', $html);
    }

    public function test_reset_restores_the_application_default(): void
    {
        $account = $this->account(BetaAccount::class, ['json_attributes' => ['locale' => 'pl', 'appearance' => ['*' => ['layout-width' => 'full']]]]);
        $this->signedIn($account, 'beta');

        $this->delete(self::PAGE)->assertRedirect(self::PAGE)->assertSessionHas('status', text('account.appearance_reset_done'));

        $attributes = (array) $account->fresh()?->json_attributes;
        $this->assertArrayNotHasKey('appearance', $attributes);
        $this->assertSame('pl', $attributes['locale'] ?? null);
        $this->get(self::PAGE)->assertSee('data-layout-width="centered">', false);
    }

    public function test_value_not_offered_in_the_zone_is_rejected(): void
    {
        $account = $this->account(BetaAccount::class);
        $this->signedIn($account, 'beta');

        $this->putJson(self::PAGE, ['palette' => 'teal'])->assertUnprocessable()->assertJsonValidationErrors('palette');

        $this->assertArrayNotHasKey('appearance', (array) $account->fresh()?->json_attributes);
    }

    public function test_guest_cannot_save_the_look(): void
    {
        $this->put(self::PAGE, ['theme' => 'dark'])->assertRedirect('/beta/signin');
    }

    public function test_zone_without_account_appearance_leaves_choices_to_the_browser(): void
    {
        $this->signedIn($this->account(AlphaAccount::class), 'alpha');

        $this->get('/alpha/account/appearance')->assertOk()
            ->assertDontSee('<meta name="ss-appearance"', false)
            ->assertSee(text('account.appearance_storage_browser'))
            ->assertDontSee(text('account.appearance_reset'));
    }

    public function test_default_outside_the_zone_choices_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(ZoneDefinitionFactory::class)->make(['name' => 'shop', 'model' => BetaAccount::class, 'appearance' => ['defaults' => ['palette' => 'teal']]], 'shop', __DIR__);
    }
}
