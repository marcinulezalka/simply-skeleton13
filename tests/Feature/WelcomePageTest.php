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

use Illuminate\Support\Facades\Config;
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
        $response->assertSee(BrandNames::withLogo(text('welcome.title'))->toHtml(), false);
        $response->assertSee('<h1 class="ss-welcome__title">System <span class="ss-logo-inline"><svg role="img" aria-label="simplysmart"', false);
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

    public function test_brand_and_zone_logos_link_to_their_websites_in_a_new_tab(): void
    {
        config(['admiro.welcome.website' => 'https://brand.example']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<a class="ss-welcome__brand-link" href="https://brand.example" target="_blank" rel="noopener noreferrer" aria-label="' . e(text('welcome.website', ['site' => 'brand.example'])) . '">', false)
            ->assertSee('<a class="ss-welcome__zone-link" href="' . Config::string('admiro.zone.website') . '" target="_blank" rel="noopener noreferrer"', false);
    }

    public function test_zone_logos_are_listed_once_each_with_the_first_given_website(): void
    {
        $factory = $this->app->make(ZoneDefinitionFactory::class);
        $logo = $this->packagePath('simplysmart/admiro', 'public/images/logo.svg');
        /** @var ZoneRegistry&MockInterface $zones */
        $zones = Mockery::mock(ZoneRegistry::class);
        $zones->shouldReceive('all')->andReturn([
            'shop' => $factory->make(['name' => 'shop', 'logo' => $logo], 'shop', __DIR__),
            'outlet' => $factory->make(['name' => 'outlet', 'logo' => $logo, 'website' => 'https://outlet.example'], 'outlet', __DIR__),
            'plain' => $factory->make(['name' => 'plain'], 'plain', __DIR__),
        ]);

        $this->assertSame([['svg' => (string) file_get_contents($logo), 'website' => 'https://outlet.example']], (new ZoneLogos($zones))->all());
    }

    public function test_brand_names_are_highlighted_after_escaping(): void
    {
        $html = BrandNames::highlight('Pakiet admiro <b>x</b> & simplysmart, ale nie admirowie')->toHtml();

        $this->assertSame('Pakiet <strong class="ss-brand-name">admiro</strong> &lt;b&gt;x&lt;/b&gt; &amp; <strong class="ss-brand-name">simplysmart</strong>, ale nie admirowie', $html);
    }

    public function test_logo_replaces_the_simplysmart_name_after_escaping(): void
    {
        $html = BrandNames::withLogo('Pakiet simplysmart <b>x</b> & admiro, ale nie simplysmartowy')->toHtml();

        $this->assertSame(1, substr_count($html, '<span class="ss-logo-inline"><svg'));
        $this->assertStringStartsWith('Pakiet <span class="ss-logo-inline"><svg role="img" aria-label="simplysmart"', $html);
        $this->assertStringEndsWith('</svg></span> &lt;b&gt;x&lt;/b&gt; &amp; <strong class="ss-brand-name">admiro</strong>, ale nie simplysmartowy', $html);
    }
}
