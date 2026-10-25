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

use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Translation\PotentiallyTranslatedString;
use Simplysmart\Admiro\Rules\StrongPin;
use Tests\TestCase;

/**
 * Reguła StrongPin: powtórzenia i kolejne cyfry (także przez zero) są odrzucane.
 */
class StrongPinTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function pins(): array
    {
        return [
            'repeated' => ['111111', false],
            'ascending' => ['123456', false],
            'descending' => ['654321', false],
            'ascending through zero' => ['890123', false],
            'descending through zero' => ['210987', false],
            'mixed' => ['159357', true],
        ];
    }

    #[DataProvider('pins')]
    public function test_pin_strength(string $pin, bool $accepted): void
    {
        $failed = false;

        (new StrongPin())->validate('pin', $pin, function (string $message) use (&$failed): PotentiallyTranslatedString {
            $failed = true;

            return new PotentiallyTranslatedString($message, $this->app->make('translator'));
        });

        $this->assertSame($accepted, ! $failed);
    }
}
