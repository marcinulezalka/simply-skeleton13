<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('dashboard', fn(): string => 'alpha dashboard:' . Auth::guard('alpha')->id())->name('dashboard');
