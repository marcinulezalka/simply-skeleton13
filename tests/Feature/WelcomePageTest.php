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

namespace Tests\Feature;

use Mockery;
use Mockery\MockInterface;
use Simplysmart\Admiro\Contracts\ZoneRegistry;
use Simplysmart\Admiro\View\BrandNames;
use Simplysmart\Admiro\View\CoreVersion;
use Simplysmart\Admiro\View\ZoneLogos;
use Simplysmart\Admiro\Zones\ZoneDefinitionFactory;
use Tests\TestCase;

/**
 * Strona startowa `/` (stockowa trasa view('welcome')) pochodzi z Core: logo simplysmart, funkcje jako
 * ikona, tytuł i opis, wyróżnione nazwy simplysmart i admiro, stopka z logo Laravela i wersją admiro, teksty w języku aplikacji i style marki osadzone
 * inline, bez akcji i bez stockowej strony Laravela; pod marką subtelne logo zarejestrowanych stref, każde raz.
 */
class WelcomePageTest extends TestCase
{
    public function test_home_page_is_the_simplysmart_welcome_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('aria-label="simplysmart"', false);
        $response->assertSee(BrandNames::highlight(text('welcome.title'))->toHtml(), false);
        $response->assertSee(text('welcome.features.dictionaries.title'));
        $response->assertSee('.ss-welcome__card', false);
        $response->assertSee('class="ss-icon ss-icon--tile"', false);
        $response->assertSee('aria-label="Laravel"', false);
        $response->assertSee(text('welcome.admiro_version', ['version' => CoreVersion::current()]));
        $response->assertDontSee('laravel.com/docs', false);
        $response->assertDontSee('<form', false);
        $response->assertSee('<strong class="ss-brand-name">admiro</strong>', false);
        $response->assertDontSee('simplysmart/admiro', false);
    }

    public function test_brand_card_shows_the_logos_of_registered_zones(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<ul class="ss-welcome__zones">', false)
            ->assertSee('<li class="ss-welcome__zone-logo">', false)
            ->assertSee('aria-label="admiro"', false);
    }

    public function test_zone_logos_are_listed_once_each(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $logo = $this->packagePath('simplysmart/admiro', 'public/images/logo.svg');
        /** @var ZoneRegistry&MockInterface $zones */
        $zones = Mockery::mock(ZoneRegistry::class);
        $zones->shouldReceive('all')->andReturn([
            'shop' => $factory->make(['name' => 'shop', 'logo' => $logo], 'shop', __DIR__),
            'outlet' => $factory->make(['name' => 'outlet', 'logo' => $logo], 'outlet', __DIR__),
            'plain' => $factory->make(['name' => 'plain'], 'plain', __DIR__),
        ]);

        $this->assertSame([(string) file_get_contents($logo)], (new ZoneLogos($zones))->svgs());
    }

    public function test_brand_names_are_highlighted_after_escaping(): void
    {
        $html = BrandNames::highlight('Pakiet admiro <b>x</b> & simplysmart, ale nie admirowie')->toHtml();

        $this->assertSame('Pakiet <strong class="ss-brand-name">admiro</strong> &lt;b&gt;x&lt;/b&gt; &amp; <strong class="ss-brand-name">simplysmart</strong>, ale nie admirowie', $html);
    }
}
