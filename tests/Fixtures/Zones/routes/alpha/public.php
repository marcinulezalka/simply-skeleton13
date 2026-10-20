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

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::get('/', fn(): string => 'alpha public')->name('home');
Route::get('locale', fn(): string => App::getLocale())->name('locale');
Route::get('home-url', fn(): string => route('alpha.home'))->name('home-url');
Route::get('items/{item}', fn(string $item): string => $item)->name('items.show');
Route::get('session', fn(): array => config()->array('session'))->name('session');
Route::post('expired-form', fn() => throw new TokenMismatchException())->name('expired-form');
