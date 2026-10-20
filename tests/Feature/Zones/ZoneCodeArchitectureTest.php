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

use Illuminate\Support\Facades\File;
use Simplysmart\Mainboard\Zones\ZoneCacheKey;
use Tests\TestCase;

/**
 * Reguły izolacji w kodzie paczek: guard zawsze nazwany jawnie, brak globalnego Cache::flush(),
 * klucze cache z prefiksem strefy.
 */
class ZoneCodeArchitectureTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const array FORBIDDEN = [
        'Auth facade without a guard' => '/Auth::(user|id|check|guest)\(\s*\)/',
        'auth() helper without a guard' => '/(?<![\w>:$])auth\(\s*\)->/',
        'request user without a guard' => '/\$request->user\(\s*\)/',
        'global cache flush' => '/Cache::flush\(/',
    ];

    public function test_package_code_always_names_the_guard_and_never_flushes_the_cache(): void
    {
        $violations = [];

        foreach (File::allFiles(base_path('packages/simplysmart')) as $file) {
            if ($file->getExtension() !== 'php' || preg_match('#^[^/]+/src/#', $file->getRelativePathname()) !== 1) {
                continue;
            }

            foreach (self::FORBIDDEN as $rule => $pattern) {
                if (preg_match($pattern, $file->getContents()) === 1) {
                    $violations[] = "{$rule}: {$file->getRelativePathname()}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_zone_cache_keys_are_prefixed_with_the_zone(): void
    {
        $this->assertSame('zone:alpha:auth:code_requests', ZoneCacheKey::make('alpha', 'auth', 'code_requests'));
    }
}
