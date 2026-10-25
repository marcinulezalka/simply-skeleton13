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

namespace Tests\Fixtures\Database;

use Simplysmart\Admiro\Models\Concerns\HasI18n;
use Simplysmart\Admiro\Models\DataModel;

/**
 * Model testowy na tabeli `demo_items` (kolumny standardowe z tłumaczeniami i jedna kolumna domenowa).
 *
 * @property string $code
 */
class DemoItem extends DataModel
{
    use HasI18n;

    public const string TABLE = 'demo_items';

    protected $table = self::TABLE;

    public function translationFallback(): string
    {
        return $this->code;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['code' => 'string'];
    }
}
