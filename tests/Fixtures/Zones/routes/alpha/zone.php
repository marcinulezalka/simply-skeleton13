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

use Illuminate\Support\Facades\Route;
use Simplysmart\Mainboard\Contracts\CurrentOrganization;
use Simplysmart\Mainboard\Zones\ZonePermission;
use Tests\Fixtures\Zones\AlphaNote;

Route::get('organization', fn(CurrentOrganization $organization): string => 'organization:' . $organization->id())->name('organization');
Route::get('reports', fn(): string => 'reports')->middleware(ZonePermission::middleware('reports.view'))->name('reports');
Route::get('notes/{note}', fn(AlphaNote $note): string => $note->title)->name('notes.show');
