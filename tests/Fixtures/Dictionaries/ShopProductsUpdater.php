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

namespace Tests\Fixtures\Dictionaries;

use RuntimeException;
use Simplysmart\Admiro\Contracts\DictionaryUpdater;

/**
 * Słownik testowy (produkty sklepu) w aktualizacjach słowników Core: liczy wywołania update(), a z $fail zgłasza błąd
 * źródła.
 */
final class ShopProductsUpdater implements DictionaryUpdater
{
    public static int $runs = 0;

    public static bool $fail = false;

    public function key(): string
    {
        return 'shop-products';
    }

    public function label(): string
    {
        return 'Produkty sklepu';
    }

    public function update(): string
    {
        if (self::$fail) {
            throw new RuntimeException('Źródło produktów nie odpowiada.');
        }

        self::$runs++;

        return 'Produktów: 12.';
    }
}
