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

namespace Tests\Feature\Localization;

use Simplysmart\Admiro\Contracts\InterfaceLocales;
use Simplysmart\Admiro\Models\Dictionaries\Language;
use Tests\Feature\Zones\ZoneTestCase;

/**
 * Języki interfejsu ze słownika w granicach APP_LOCALES: wyłączony język przekierowuje na domyślny,
 * domyślny pochodzi ze słownika, a bez języków interfejsu w słowniku obowiązuje config.
 */
class InterfaceLocalesTest extends ZoneTestCase
{
    protected function configureZones(): void
    {
        config(['admiro.locale.enabled' => ['pl', 'en']]);
    }

    public function test_without_dictionary_entries_the_config_decides(): void
    {
        $this->assertSame(['pl', 'en'], $this->locales()->enabled());
        $this->get('/en/alpha/locale')->assertOk()->assertContent('en');
    }

    public function test_language_switched_off_in_the_dictionary_redirects_to_the_default_one(): void
    {
        $this->language('pl', interface: true);
        $this->language('en', interface: false);

        $this->get('/en/alpha/items/abc?x=1')->assertRedirect(url('/pl/alpha/items/abc?x=1'));
        $this->post('/en/alpha/expired-form')->assertRedirect();
        $this->get('/pl/alpha/locale')->assertOk()->assertContent('pl');
    }

    public function test_default_language_comes_from_the_dictionary(): void
    {
        $this->language('pl', interface: false);
        $this->language('en', interface: true, default: true);

        $this->assertSame(['en'], $this->locales()->enabled());
        $this->assertSame('en', $this->locales()->default());
        $this->get('/pl/alpha/locale')->assertRedirect(url('/en/alpha/locale'));
    }

    public function test_dictionary_cannot_enable_a_language_outside_app_locales(): void
    {
        $this->language('pl', interface: true);
        $this->language('de', interface: true, default: true);

        $this->assertSame(['pl'], $this->locales()->enabled());
        $this->assertSame('pl', $this->locales()->default());
        $this->get('/de/alpha/locale')->assertNotFound();
    }

    private function language(string $code, bool $interface, bool $default = false): void
    {
        (new Language())->forceFill(['name' => $code, 'code' => $code, 'is_interface' => $interface, 'is_default' => $default])->save();
        $this->locales()->forget();
    }

    private function locales(): InterfaceLocales
    {
        return $this->app->make(InterfaceLocales::class);
    }
}
