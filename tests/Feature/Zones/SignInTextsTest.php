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

use Illuminate\Contracts\Translation\Translator;
use Simplysmart\Mainboard\View\AccentText;
use Simplysmart\Mainboard\Zones\ZoneDefinitionFactory;
use Tests\TestCase;

/**
 * Teksty ekranów logowania strefy: tekst osobny dla strefy wygrywa nad tekstem paczki, a ten nad Core; strefa bez
 * tekstów ma teksty Core; nagłówek z wyróżnionym fragmentem w gwiazdkach jest escapowany.
 */
class SignInTextsTest extends TestCase
{
    public function test_zone_texts_win_over_package_and_core_texts(): void
    {
        $translator = $this->app->make(Translator::class);
        $translator->addLines(['sign_in.shop.title' => 'Sklep *otwarty*', 'sign_in.lead' => 'Wstęp paczki'], 'pl', 'shop');
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $shop = $factory->make(['name' => 'shop', 'texts' => 'shop'], 'shop', __DIR__);
        $outlet = $factory->make(['name' => 'outlet', 'texts' => 'shop'], 'outlet', __DIR__);
        $plain = $factory->make(['name' => 'plain'], 'plain', __DIR__);

        $this->assertSame('Sklep *otwarty*', $shop->signInText('title'));
        $this->assertSame(text('sign_in.title'), $outlet->signInText('title'));
        $this->assertSame('Wstęp paczki', $outlet->signInText('lead'));
        $this->assertSame(text('sign_in.email'), $shop->signInText('email'));
        $this->assertSame('', $plain->signInText('lead'));
        $this->assertSame(text('sign_in.pin_setup_intro', ['min' => 4, 'max' => 6]), $plain->signInText('pin_setup_intro', ['min' => 4, 'max' => 6]));
    }

    public function test_accent_text_marks_the_starred_part_and_escapes_the_rest(): void
    {
        $this->assertSame('Opieka <em class="ss-accent-text">zaczyna się</em> &lt;b&gt;', AccentText::html('Opieka *zaczyna się* <b>')->toHtml());
        $this->assertSame('Opieka zaczyna się', AccentText::plain('Opieka *zaczyna się*'));
    }
}
