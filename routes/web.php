<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\It\DashboardController as ItDashboardController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Route;

/*
| Tidak ada registrasi publik. Seluruh akun dibuat oleh IT Admin.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Ganti password tetap dapat diakses walau must_change_password = true.
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');

    Route::middleware('password.changed')->group(function () {
        Route::get('/', HomeController::class)->name('home');

        Route::get('/profil', [ProfileController::class, 'show'])->name('profile.show');
        Route::put('/profil', [ProfileController::class, 'update'])->name('profile.update');

        // ROLE: USER
        Route::middleware('role:USER')->prefix('user')->name('user.')->group(function () {
            Route::get('/dashboard', UserDashboardController::class)->name('dashboard');
        });

        // ROLE: IT & IT ADMIN
        Route::middleware('role:IT,IT_ADMIN')->prefix('it')->name('it.')->group(function () {
            Route::get('/dashboard', ItDashboardController::class)->name('dashboard');
        });

        // ROLE: IT ADMIN
        Route::middleware('role:IT_ADMIN')->prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

            Route::resource('units', UnitController::class)->except(['show', 'destroy']);

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
    });
});
