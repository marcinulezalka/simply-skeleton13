<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn(): string => 'open public')->name('home');
Route::get('ip', fn(Request $request): ?string => $request->ip())->name('ip');
