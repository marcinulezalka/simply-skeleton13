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

namespace Tests;

use Composer\InstalledVersions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;

abstract class TestCase extends BaseTestCase
{
    /**
     * Komenda Artisana z asercjami (PendingCommand).
     *
     * @param array<string, mixed> $parameters
     */
    protected function command(string $command, array $parameters = []): PendingCommand
    {
        $pending = $this->artisan($command, $parameters);

        return $pending instanceof PendingCommand ? $pending : throw new \LogicException('Artisan commands must be mocked in tests.');
    }

    /**
     * Ścieżka pliku w zainstalowanej paczce, niezależnie od układu: `packages/` (symlink z repozytorium typu path)
     * albo `vendor/` (instalacja z git, np. kopia wdrożeniowa bez `packages/`).
     */
    protected function packagePath(string $package, string $path = ''): string
    {
        $root = InstalledVersions::getInstallPath($package);
        $root = $root === null ? false : realpath($root);

        if ($root === false) {
            throw new \LogicException("Package [{$package}] is not installed.");
        }

        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }
}
