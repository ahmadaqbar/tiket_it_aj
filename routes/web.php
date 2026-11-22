<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SlaPolicyController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\It\DashboardController as ItDashboardController;
use App\Http\Controllers\It\TicketController as ItTicketController;
use App\Http\Controllers\TicketActionController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\TicketController as UserTicketController;
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

        // Lampiran: otorisasi di Policy (pemilik tiket / IT), bukan di route.
        Route::get('/lampiran/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

        // Balasan tiket (pelapor & IT). Visibilitas & hak akses diperiksa di ReplyRequest + service.
        Route::post('/tiket/{ticket}/balasan', [TicketActionController::class, 'reply'])->middleware('throttle:tickets')->name('tickets.reply');

        // ROLE: USER
        Route::middleware('role:USER')->prefix('user')->name('user.')->group(function () {
            Route::get('/dashboard', UserDashboardController::class)->name('dashboard');

            Route::get('/tiket', [UserTicketController::class, 'index'])->name('tickets.index');
            Route::get('/tiket/riwayat', [UserTicketController::class, 'history'])->name('tickets.history');
            Route::get('/tiket/buat', [UserTicketController::class, 'create'])->name('tickets.create');
            Route::post('/tiket', [UserTicketController::class, 'store'])->middleware('throttle:tickets')->name('tickets.store');
            Route::get('/tiket/{ticket}', [UserTicketController::class, 'show'])->name('tickets.show');
            Route::post('/tiket/{ticket}/konfirmasi', [TicketActionController::class, 'confirm'])->name('tickets.confirm');
            Route::post('/tiket/{ticket}/buka-kembali', [TicketActionController::class, 'reopen'])->name('tickets.reopen');
        });

        // ROLE: IT & IT ADMIN
        Route::middleware('role:IT,IT_ADMIN')->prefix('it')->name('it.')->group(function () {
            Route::get('/dashboard', ItDashboardController::class)->name('dashboard');

            Route::get('/tiket', [ItTicketController::class, 'index'])->name('tickets.index');
            Route::get('/tiket/export', [ItTicketController::class, 'export'])->middleware('throttle:6,1')->name('tickets.export');
            Route::get('/tiket/{ticket}', [ItTicketController::class, 'show'])->name('tickets.show');
            Route::put('/tiket/{ticket}/status', [TicketActionController::class, 'status'])->name('tickets.status');
            Route::put('/tiket/{ticket}/pic', [TicketActionController::class, 'assign'])->name('tickets.assign');
            Route::post('/tiket/{ticket}/ambil', [TicketActionController::class, 'take'])->name('tickets.take');
            Route::put('/tiket/{ticket}/klasifikasi', [TicketActionController::class, 'reclassify'])->name('tickets.reclassify');
        });

        // ROLE: IT ADMIN
        Route::middleware('role:IT_ADMIN')->prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

            Route::resource('units', UnitController::class)->except(['show', 'destroy']);

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('categories/{category}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.subcategories.store');
            Route::put('subcategories/{subcategory}', [CategoryController::class, 'updateSubcategory'])->name('subcategories.update');
            Route::delete('subcategories/{subcategory}', [CategoryController::class, 'destroySubcategory'])->name('subcategories.destroy');

            Route::get('sla', [SlaPolicyController::class, 'index'])->name('sla.index');
            Route::put('sla/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla.update');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
    });
});
