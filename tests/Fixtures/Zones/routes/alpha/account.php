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

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', fn(): string => 'alpha dashboard:' . Auth::guard('alpha')->id())->name('dashboard');
Route::get('page', fn(): string => Blade::render('<x-admiro::zone.layout title="Zamówienia" meta="12 zamówień">x</x-admiro::zone.layout>'))->name('page');
Route::get('columns', fn(): string => Blade::render('<x-admiro::zone.layout title="Sale"><x-slot:left>Menu sal</x-slot:left><x-slot:sidebar-wiki><x-admiro::wiki-tip term="organizacja" /></x-slot:sidebar-wiki> x</x-admiro::zone.layout>'))->name('columns');
Route::get('start', fn(): string => Blade::render('<x-admiro::zone.layout title="Start" home>x</x-admiro::zone.layout>'))->name('start');
