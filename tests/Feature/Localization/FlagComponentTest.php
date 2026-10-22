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

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Komponent x-mainboard::flag: flaga z kodu flag_icon jako dekoracyjny inline SVG, neutralna flaga „xx”
 * dla nieznanego kodu i nic bez kodu.
 */
class FlagComponentTest extends TestCase
{
    public function test_renders_the_flat_flag_as_a_decorative_svg(): void
    {
        $html = Blade::render('<x-mainboard::flag icon="pl" />');

        $this->assertStringContainsString('id="flag-icons-pl"', $html);
        $this->assertStringContainsString('class="ss-flag"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_unknown_icon_falls_back_to_the_flat_placeholder(): void
    {
        $this->assertStringContainsString('id="flag-icons-xx"', Blade::render('<x-mainboard::flag icon="zz" />'));
    }

    public function test_renders_nothing_without_an_icon(): void
    {
        $this->assertSame('', trim(Blade::render('<x-mainboard::flag :icon="null" />')));
    }
}
