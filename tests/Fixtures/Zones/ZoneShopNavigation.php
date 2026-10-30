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
use Simplysmart\Admiro\Contracts\ZonePageProgress;
use Simplysmart\Admiro\Enums\AppLayout;
use Simplysmart\Admiro\View\Navigation\NavigationGroup;
use Simplysmart\Admiro\View\Navigation\NavigationItem;
use Simplysmart\Admiro\View\Navigation\SearchBox;
use Simplysmart\Admiro\View\Navigation\SearchScope;

/**
 * Menu strefy testowej (sklep) w bazowym layoucie stref (Enums\AppLayout::Zone): wyszukiwarka z akcją dodawania,
 * pustą błyskawicą i zakresami, a każda strona jest jeszcze w przygotowaniu (Contracts\ZonePageProgress).
 */
final class ZoneShopNavigation implements ZoneNavigation, ZoneAppLayout, ZonePageProgress
{
    public function appLayout(): AppLayout
    {
        return AppLayout::Zone;
    }

    public function settingsArea(): bool
    {
        return true;
    }

    public function pageInProgress(): bool
    {
        return true;
    }

    public function groups(): array
    {
        return [new NavigationGroup(null, [new NavigationItem('Zamówienia', '/zamowienia', 'invoice')])];
    }

    public function settings(): array
    {
        return [new NavigationGroup('Sklep', [new NavigationItem('Ustawienia sklepu', '/ustawienia', 'settings')])];
    }

    public function search(): SearchBox
    {
        return new SearchBox(
            '/szukaj',
            'Numer zamówienia',
            scopes: [new SearchScope('orders', 'Zamówienia', icon: 'invoice')],
            actions: [new NavigationItem('Nowe zamówienie', '/zamowienia/nowe', 'plus')],
            findActions: [],
        );
    }

    public function subtitle(): ?string
    {
        return null;
    }
}
