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

namespace Tests\Feature\Admiro;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Simplysmart\Mainboard\Enums\BrandAsset;
use Simplysmart\Mainboard\Models\Organization;
use Simplysmart\Mainboard\Organizations\OrganizationBranding;
use Simplysmart\Mainboard\Organizations\OrganizationManagement;

/**
 * Marka organizacji w panelu (`organizations.manage`): wgranie, podgląd, podmiana (stary plik znika) i usunięcie
 * logo, odrzucenie złego formatu i za dużego pliku, brak dostępu bez uprawnienia.
 */
class AdmiroOrganizationBrandTest extends AdmiroTestCase
{
    private Organization $company;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->company = $this->app->make(OrganizationManagement::class)->create('Firma');
    }

    public function test_owner_uploads_previews_replaces_and_removes_the_logo(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $url = '/admiro/organizations/' . $this->company->token . '/brand/logo';

        $this->post($url, ['file' => UploadedFile::fake()->image('logo.png', 200, 50)])
            ->assertRedirect('/admiro/organizations/' . $this->company->token . '/edit')
            ->assertSessionHas('status', text('admiro::admin.organization_brand_saved'));
        $first = $this->storedPath();
        Storage::disk('local')->assertExists($first);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admiro/organizations/' . $this->company->token . '/edit')->assertOk()->assertSee(text('admiro::admin.organization_brand'))->assertSee($url, false);

        $this->post($url, ['file' => UploadedFile::fake()->image('logo.jpg', 300, 60)]);
        $second = $this->storedPath();
        $this->assertNotSame($first, $second);
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($second);

        $this->delete($url)->assertSessionHas('status', text('admiro::admin.organization_brand_removed'));
        Storage::disk('local')->assertMissing($second);
        $this->assertFalse($this->app->make(OrganizationBranding::class)->has($this->company->refresh(), BrandAsset::Logo));
        $this->get($url)->assertNotFound();
    }

    public function test_wrong_format_and_too_large_files_are_rejected(): void
    {
        $this->signedIn($this->admin('owner@example.com', 'owner'), 'admiro');
        $url = '/admiro/organizations/' . $this->company->token . '/brand/logo';

        $this->post($url, ['file' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')]);
        $this->assertNotSame('', $this->sessionError('file'));
        $this->post($url, ['file' => UploadedFile::fake()->image('logo.png')->size(BrandAsset::Logo->maxKilobytes() + 1)]);
        $this->assertNotSame('', $this->sessionError('file'));
        $this->post('/admiro/organizations/' . $this->company->token . '/brand/banner', ['file' => UploadedFile::fake()->image('logo.png')])->assertNotFound();

        $this->assertFalse($this->app->make(OrganizationBranding::class)->has($this->company->refresh(), BrandAsset::Logo));
    }

    public function test_support_cannot_change_the_brand(): void
    {
        $this->signedIn($this->admin('support@example.com', 'support'), 'admiro');

        $this->post('/admiro/organizations/' . $this->company->token . '/brand/logo', ['file' => UploadedFile::fake()->image('logo.png')])->assertForbidden();
        $this->get('/admiro/organizations/' . $this->company->token . '/brand/logo')->assertForbidden();
    }

    private function storedPath(): string
    {
        $brand = $this->company->refresh()->json_attributes['brand'] ?? null;
        $this->assertIsArray($brand);
        $logo = $brand['logo'] ?? null;
        $this->assertIsArray($logo);
        $path = $logo['path'] ?? null;
        $this->assertIsString($path);

        return $path;
    }
}
