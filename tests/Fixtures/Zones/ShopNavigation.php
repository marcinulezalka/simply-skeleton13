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

namespace Tests\Fixtures\Zones;

use Simplysmart\Mainboard\Contracts\ZoneNavigation;
use Simplysmart\Mainboard\View\Navigation\NavigationGroup;
use Simplysmart\Mainboard\View\Navigation\NavigationItem;
use Simplysmart\Mainboard\View\Navigation\SearchBox;

/**
 * Menu strefy testowej (neutralny przykład sklepu).
 */
final class ShopNavigation implements ZoneNavigation
{
    public function groups(): array
    {
        return [new NavigationGroup(null, [new NavigationItem('Zamówienia', '/zamowienia', 'invoice')])];
    }

    public function search(): SearchBox
    {
        return new SearchBox('/szukaj', 'Numer zamówienia');
    }

    public function subtitle(): string
    {
        return 'Sklep · Kierownik';
    }
}
