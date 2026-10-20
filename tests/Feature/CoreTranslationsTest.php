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

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Integracja szkieletu z polskimi tłumaczeniami frameworka dostarczanymi przez Core (mainboard).
 */
class CoreTranslationsTest extends TestCase
{
    /**
     * Komunikat walidacji po polsku pochodzi z lang/pl/ paczki mainboard.
     */
    public function test_polish_validation_message_comes_from_core(): void
    {
        App::setLocale('pl');

        $this->assertSame('Pole email jest wymagane.', __('validation.required', ['attribute' => 'email']));
    }

    /**
     * Angielskie teksty frameworka pozostają bez zmian.
     */
    public function test_english_messages_stay_from_framework(): void
    {
        App::setLocale('en');

        $this->assertSame('The email field is required.', __('validation.required', ['attribute' => 'email']));
    }

    /**
     * Plik lang/<locale>/ aplikacji nadpisuje tłumaczenie Core, a pozostałe klucze zostają z Core.
     */
    public function test_application_lang_file_overrides_core(): void
    {
        $files = new Filesystem();
        $langPath = sys_get_temp_dir() . '/core-translations-' . uniqid();
        $files->ensureDirectoryExists($langPath . '/pl');
        $files->put($langPath . '/pl/auth.php', "<?php return ['failed' => 'Nadpisane w aplikacji.'];");

        try {
            $this->app->useLangPath($langPath);
            $this->app->forgetInstance('translation.loader');
            $this->app->forgetInstance('translator');
            App::setLocale('pl');

            $this->assertSame('Nadpisane w aplikacji.', __('auth.failed'));
            $this->assertSame('Podane hasło jest nieprawidłowe.', __('auth.password'));
        } finally {
            $files->deleteDirectory($langPath);
        }
    }
}
