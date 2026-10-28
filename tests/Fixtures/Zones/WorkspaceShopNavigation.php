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

use Simplysmart\Admiro\Contracts\ZoneAppLayout;
use Simplysmart\Admiro\Contracts\ZoneNavigation;
use Simplysmart\Admiro\Contracts\ZoneShortcuts;
use Simplysmart\Admiro\Enums\AppLayout;
use Simplysmart\Admiro\View\Navigation\NavigationGroup;
use Simplysmart\Admiro\View\Navigation\NavigationItem;
use Simplysmart\Admiro\View\Navigation\SearchBox;

/**
 * Menu strefy testowej (sklep), które w bieżącym żądaniu wybiera layout Workspace (Contracts\ZoneAppLayout) zamiast
 * `app_layout` strefy, ze skrótem w stopce.
 */
final class WorkspaceShopNavigation implements ZoneNavigation, ZoneAppLayout, ZoneShortcuts
{
    public function appLayout(): AppLayout
    {
        return AppLayout::Workspace;
    }

    public function shortcuts(): array
    {
        return [new NavigationItem('Zamówienia', '/zamowienia', 'invoice')];
    }

    public function groups(): array
    {
        return [new NavigationGroup(null, [new NavigationItem('Zamówienia', '/zamowienia', 'invoice')])];
    }

    public function settings(): array
    {
        return [];
    }

    public function search(): SearchBox
    {
        return new SearchBox('/szukaj', 'Numer zamówienia');
    }

    public function subtitle(): ?string
    {
        return null;
    }
}
