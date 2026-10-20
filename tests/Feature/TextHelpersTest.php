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
 * Helpery text_* z Core (mainboard): kaskada paczka strefy → mainboard → skeleton → klucz, bez fallbacku.
 *
 * Paczkę strefy udaje namespace "zone" w katalogu tymczasowym, a skeleton tymczasowy katalog lang/.
 */
class TextHelpersTest extends TestCase
{
    private Filesystem $files;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem();
        $this->root = sys_get_temp_dir() . '/text-helpers-' . uniqid();

        $this->writeLang('zone/pl/actions.php', ['save' => 'Zapisz w strefie']);
        $this->writeLang('zone/pl/emails.php', ['welcome' => ['subject' => 'Witaj, :name']]);
        $this->writeLang('zone/pl/files.php', ['headers' => ['email' => 'Adres e-mail']]);
        $this->writeLang('zone/pl/data.php', ['user' => ['first_name' => 'Imię klienta'], 'created_at' => 'Utworzono w strefie']);
        $this->writeLang('app/pl/labels.php', ['title' => 'Tytuł aplikacji']);
        $this->writeLang('app/pl/actions.php', ['save' => 'Zachowaj']);
        $this->writeLang('app/pl/data.php', ['updated_at' => 'Zmieniono']);
        $this->writeLang('app/en/labels.php', ['only_en' => 'Only in English']);

        $this->app->useLangPath($this->root . '/app');
        $this->app->forgetInstance('translation.loader');
        $this->app->forgetInstance('translator');
        $this->app->make('translator')->addNamespace('zone', $this->root . '/zone');

        App::setLocale('pl');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_package_translation_wins(): void
    {
        $this->assertSame('Zapisz w strefie', text_action('zone::save'));
    }

    public function test_missing_in_package_falls_to_core(): void
    {
        $this->assertSame('Anuluj', text_action('zone::cancel'));
    }

    public function test_missing_in_core_falls_to_skeleton(): void
    {
        $this->assertSame('Tytuł aplikacji', text('zone::labels.title'));
    }

    public function test_core_wins_over_skeleton(): void
    {
        $this->assertSame('Zapisz', text_action('save'));
    }

    public function test_missing_everywhere_returns_original_key(): void
    {
        $this->assertSame('zone::missing.key', text('zone::missing.key'));
        $this->assertSame('actions.missing', text_action('missing'));
    }

    public function test_does_not_fall_back_to_other_language(): void
    {
        $this->app->make('translator')->setFallback('en');

        $this->assertSame('labels.only_en', text('labels.only_en'));
        $this->assertSame('Only in English', text('labels.only_en', locale: 'en'));
    }

    public function test_group_of_lines_is_treated_as_missing(): void
    {
        $this->assertSame('actions', text('actions'));
    }

    public function test_replacements_are_applied(): void
    {
        $this->assertSame('Pole email jest wymagane.', text('validation.required', ['attribute' => 'email']));
        $this->assertSame('Witaj, Ala', text_email('zone::welcome.subject', ['name' => 'Ala']));
    }

    public function test_file_group_prefixes_key_after_namespace(): void
    {
        $this->assertSame('Adres e-mail', text_file('zone::headers.email'));
        $this->assertSame('zone::files.headers.phone', text_file('zone::headers.phone'));
    }

    public function test_data_uses_model_specific_then_shared_column(): void
    {
        $this->assertSame('Imię klienta', text_data('zone::User', 'first_name'));
        $this->assertSame('Utworzono w strefie', text_data('zone::User', 'created_at'));
        $this->assertSame('Zmieniono', text_data('User', 'updated_at'));
    }

    public function test_data_snakes_class_name_and_returns_specific_key_when_missing(): void
    {
        $this->assertSame('data.order_item.quantity', text_data('App\\Models\\OrderItem', 'quantity'));
        $this->assertSame('zone::data.user.last_name', text_data('zone::User', 'last_name'));
    }

    /**
     * @param  array<string, mixed>  $lines
     */
    private function writeLang(string $path, array $lines): void
    {
        $file = $this->root . '/' . $path;
        $this->files->ensureDirectoryExists(dirname($file));
        $this->files->put($file, '<?php return ' . var_export($lines, true) . ';');
    }
}
