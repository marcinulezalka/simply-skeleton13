<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;

Route::get('/', fn(): string => 'alpha public')->name('home');
Route::get('locale', fn(): string => App::getLocale())->name('locale');
Route::get('home-url', fn(): string => route('alpha.home'))->name('home-url');
Route::get('items/{item}', fn(string $item): string => $item)->name('items.show');
Route::get('session', fn(): array => config()->array('session'))->name('session');
