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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn(): string => 'open public')->name('home');
Route::get('ip', fn(Request $request): ?string => $request->ip())->name('ip');
