<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MaklumatPekerjaController;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::resource('pekerja', MaklumatPekerjaController::class);
});

require __DIR__ . '/settings.php';
