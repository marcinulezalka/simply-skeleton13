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

namespace Tests\Feature\Console;

use Illuminate\Testing\PendingCommand;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Skrypt crona z `admiro:cron:script`: harmonogram i worker kolejki z wartości `admiro.cron.*`, czas pracy
 * workera z interwału, bez workera przy kolejce `sync`, plik wykonywalny o poprawnej składni, bez sekretów.
 */
class CronScriptTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir() . '/cron-' . bin2hex(random_bytes(8)) . '.sh';
        config(['queue.default' => 'database', 'admiro.cron' => ['php' => 'php84', 'interval_minutes' => 1, 'queue_tries' => 3, 'queue_worker' => true]]);
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_script_runs_the_schedule_and_a_worker_that_stops_before_the_next_minute(): void
    {
        $script = $this->generate();

        $this->assertStringContainsString('cd ' . escapeshellarg(base_path()) . ' || exit 1', $script);
        $this->assertStringContainsString("\nphp84 artisan schedule:run\n", $script);
        $this->assertStringContainsString("\nphp84 artisan queue:work --stop-when-empty --max-time=50 --tries=3\n", $script);
        $this->assertSame(0755, fileperms($this->path) & 0777);
        $this->assertSame(0, (new Process(['bash', '-n', $this->path]))->run());
    }

    public function test_longer_interval_keeps_the_worker_waiting_until_the_next_run(): void
    {
        config(['admiro.cron.interval_minutes' => 5, 'admiro.cron.queue_tries' => 2]);

        $this->assertStringContainsString("\nphp84 artisan queue:work --max-time=290 --tries=2\n", $this->generate());
    }

    public function test_php_option_wins_over_the_configuration(): void
    {
        $script = $this->generate(['--php' => '/usr/bin/php8.4']);

        $this->assertStringContainsString("\n/usr/bin/php8.4 artisan schedule:run\n", $script);
        $this->assertStringNotContainsString('php84', $script);
    }

    public function test_no_worker_with_a_sync_queue_or_when_disabled(): void
    {
        config(['queue.default' => 'sync']);
        $this->assertStringNotContainsString('queue:work', $this->generate());

        config(['queue.default' => 'database', 'admiro.cron.queue_worker' => false]);
        $this->assertStringNotContainsString('queue:work', $this->generate());
    }

    public function test_script_holds_no_secrets(): void
    {
        config(['database.connections.mysql.password' => 'db-secret-value', 'app.key' => 'base64:app-secret-value']);

        $script = $this->generate();

        $this->assertStringNotContainsString('secret-value', $script);
    }

    public function test_unsafe_php_command_or_interval_is_refused(): void
    {
        $this->cronScript(['--php' => 'php84; rm -rf /', '--output' => $this->path])->assertFailed();

        config(['admiro.cron.interval_minutes' => 0]);
        $this->cronScript(['--output' => $this->path])->assertFailed();

        $this->assertFileDoesNotExist($this->path);
    }

    /**
     * @param array<string, string> $options
     */
    private function cronScript(array $options): PendingCommand
    {
        $pending = $this->artisan('admiro:cron:script', $options);

        return $pending instanceof PendingCommand ? $pending : throw new \LogicException('Artisan commands must be mocked in tests.');
    }

    /**
     * @param array<string, string> $options
     */
    private function generate(array $options = []): string
    {
        $this->cronScript([...$options, '--output' => $this->path])->assertSuccessful();

        return (string) file_get_contents($this->path);
    }
}
