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

namespace Tests\Feature\Permissions;

use Simplysmart\Mainboard\Permissions\InvalidPermissionFile;
use Simplysmart\Mainboard\Permissions\PermissionFileParser;
use Simplysmart\Mainboard\Permissions\PermissionFiles;

/**
 * Walidacja plików ról przed zapisem: strefa, role obowiązkowe, nazwy, odwołania do uprawnień i ról, cykle
 * dziedziczenia; spłaszczanie `inherits`, `*` i `except`; każdy zarejestrowany plik jest poprawny.
 */
class PermissionFileParserTest extends ZoneRolesTestCase
{
    public function test_inheritance_wildcard_and_exceptions_are_flattened(): void
    {
        $set = $this->parser()->parse(self::FIXTURES . '/alpha.json');

        $this->assertSame(['notes.manage', 'reports.export', 'reports.view', 'users.manage'], $set->permissionsOf('manager'));
        $this->assertSame(['notes.manage', 'reports.export', 'reports.view'], $set->permissionsOf('owner'));
        $this->assertSame([], $set->permissionsOf('guest'));
    }

    public function test_every_registered_file_is_valid(): void
    {
        $files = $this->app->make(PermissionFiles::class)->all();

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertNotSame('', $this->parser()->parse($file)->zone);
        }
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidFiles(): array
    {
        $roles = ['guest' => ['permissions' => []], 'support' => ['permissions' => []], 'owner' => ['permissions' => '*']];

        return [
            'unknown zone' => [['zone' => 'nowhere', 'permissions' => [], 'roles' => $roles], 'registered zone'],
            'zone without sign-in' => [['zone' => 'open', 'permissions' => [], 'roles' => $roles], 'registered zone'],
            'missing required role' => [['zone' => 'beta', 'permissions' => [], 'roles' => ['guest' => [], 'owner' => []]], 'required role [support]'],
            'unknown permission' => [['zone' => 'beta', 'permissions' => [], 'roles' => [...$roles, 'clerk' => ['permissions' => ['orders.view']]]], 'not listed'],
            'unknown exception' => [['zone' => 'beta', 'permissions' => [], 'roles' => [...$roles, 'owner' => ['permissions' => '*', 'except' => ['orders.view']]]], 'not listed'],
            'invalid permission name' => [['zone' => 'beta', 'permissions' => ['Orders'], 'roles' => $roles], 'invalid name'],
            'invalid role name' => [['zone' => 'beta', 'permissions' => [], 'roles' => [...$roles, 'Clerks' => []]], 'kebab-case'],
            'duplicate permission' => [['zone' => 'beta', 'permissions' => ['orders.view', 'orders.view'], 'roles' => $roles], 'duplicates'],
            'unknown parent role' => [['zone' => 'beta', 'permissions' => [], 'roles' => [...$roles, 'clerk' => ['inherits' => ['boss']]]], 'unknown role [boss]'],
            'inheritance cycle' => [['zone' => 'beta', 'permissions' => [], 'roles' => [...$roles, 'a' => ['inherits' => ['b']], 'b' => ['inherits' => ['a']]]], 'cycle'],
        ];
    }

    /**
     * @param array<string, mixed> $content
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFiles')]
    public function test_invalid_file_is_rejected(array $content, string $reason): void
    {
        $path = $this->temporaryFile(json_encode($content, JSON_THROW_ON_ERROR));

        try {
            $this->parser()->parse($path);
            $this->fail('The file should be rejected.');
        } catch (InvalidPermissionFile $exception) {
            $this->assertStringContainsString($reason, $exception->getMessage());
        } finally {
            unlink($path);
        }
    }

    public function test_broken_json_is_rejected(): void
    {
        $path = $this->temporaryFile('{"zone": ');

        try {
            $this->expectException(InvalidPermissionFile::class);
            $this->parser()->parse($path);
        } finally {
            unlink($path);
        }
    }

    private function temporaryFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/roles-' . bin2hex(random_bytes(8)) . '.json';
        file_put_contents($path, $content);

        return $path;
    }

    private function parser(): PermissionFileParser
    {
        return $this->app->make(PermissionFileParser::class);
    }
}
