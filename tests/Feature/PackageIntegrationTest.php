<?php

declare(strict_types=1);

namespace Tests\Feature;

use Simplysmart\Mainboard\MainboardServiceProvider;
use Tests\TestCase;

/**
 * Integracja szkieletu z lokalnymi pakietami: auto-discovery ich Service Providerów.
 */
class PackageIntegrationTest extends TestCase
{
    /**
     * Sprawdza, że provider pakietu simplysmart/mainboard jest wykryty i załadowany.
     */
    public function test_mainboard_service_provider_is_loaded(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(MainboardServiceProvider::class));
    }
}
