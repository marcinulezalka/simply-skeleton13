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

use Simplysmart\Admiro\AdmiroServiceProvider;
use Tests\TestCase;

/**
 * Integracja szkieletu z lokalnymi pakietami: auto-discovery ich Service Providerów.
 */
class PackageIntegrationTest extends TestCase
{
    /**
     * Sprawdza, że provider pakietu simplysmart/admiro jest wykryty i załadowany.
     */
    public function test_admiro_service_provider_is_loaded(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(AdmiroServiceProvider::class));
    }
}
