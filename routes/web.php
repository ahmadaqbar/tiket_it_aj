<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\QrCodeController;
use App\Http\Controllers\Admin\QuickReplyTemplateController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SlaPolicyController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\ProfileController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\It\AnalyticsController;
use App\Http\Controllers\It\DashboardController as ItDashboardController;
use App\Http\Controllers\It\ReportController;
use App\Http\Controllers\It\TicketAiController;
use App\Http\Controllers\It\TicketController as ItTicketController;
use App\Http\Controllers\KnowledgeBaseAiController;
use App\Http\Controllers\KnowledgeBaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\TicketActionController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use App\Http\Controllers\User\TicketController as UserTicketController;
use Illuminate\Support\Facades\Route;

/*
| Tidak ada registrasi publik. Seluruh akun dibuat oleh IT Admin.
*/

Route::get('/branding/logo', [BrandingController::class, 'logo'])->name('branding.logo');
Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');

// QR unit: publik, hanya mengidentifikasi unit lalu mengarahkan ke login / form laporan.
Route::get('/qr/{token}', [QrController::class, 'show'])->middleware('throttle:30,1')->name('qr.show');

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

        Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifikasi/{id}', [NotificationController::class, 'open'])->name('notifications.open');
        Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // Knowledge Base: baca untuk semua role (Policy membatasi), tulis untuk IT.
        Route::get('/kb', [KnowledgeBaseController::class, 'index'])->name('kb.index');
        Route::middleware('role:IT,IT_ADMIN')->group(function () {
            Route::get('/kb/tanya', [KnowledgeBaseAiController::class, 'page'])->name('kb.ask');
            Route::post('/kb/tanya', [KnowledgeBaseAiController::class, 'ask'])->middleware('throttle:20,1')->name('kb.ask.submit');
            Route::get('/kb/baru', [KnowledgeBaseController::class, 'create'])->name('kb.create');
            Route::post('/kb', [KnowledgeBaseController::class, 'store'])->name('kb.store');
            Route::get('/kb/{article}/ubah', [KnowledgeBaseController::class, 'edit'])->name('kb.edit');
            Route::put('/kb/{article}', [KnowledgeBaseController::class, 'update'])->name('kb.update');
            Route::post('/kb/{article}/{action}', [KnowledgeBaseController::class, 'transition'])->whereIn('action', ['publish', 'archive', 'draft'])->name('kb.transition');
        });
        Route::get('/kb/{article}', [KnowledgeBaseController::class, 'show'])->name('kb.show');

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

            Route::get('/analytics', AnalyticsController::class)->name('analytics');
            Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/laporan/unduh', [ReportController::class, 'download'])->middleware('throttle:10,1')->name('reports.download');

            Route::get('/tiket', [ItTicketController::class, 'index'])->name('tickets.index');
            Route::get('/tiket/export', [ItTicketController::class, 'export'])->middleware('throttle:6,1')->name('tickets.export');
            Route::get('/tiket/{ticket}', [ItTicketController::class, 'show'])->name('tickets.show');
            Route::put('/tiket/{ticket}/status', [TicketActionController::class, 'status'])->name('tickets.status');
            Route::put('/tiket/{ticket}/pic', [TicketActionController::class, 'assign'])->name('tickets.assign');
            Route::post('/tiket/{ticket}/ambil', [TicketActionController::class, 'take'])->name('tickets.take');
            Route::put('/tiket/{ticket}/klasifikasi', [TicketActionController::class, 'reclassify'])->name('tickets.reclassify');
            Route::post('/tiket/{ticket}/ai/{feature}', TicketAiController::class)
                ->whereIn('feature', ['classify', 'troubleshoot', 'summary', 'reply'])->middleware('throttle:20,1')->name('tickets.ai');
        });

        // ROLE: IT ADMIN
        Route::middleware('role:IT_ADMIN')->prefix('admin')->name('admin.')->group(function () {
            Route::resource('users', UserController::class)->except(['show', 'destroy']);
            Route::put('users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');

            Route::resource('units', UnitController::class)->except(['show', 'destroy']);

            Route::get('qr', [QrCodeController::class, 'index'])->name('qr.index');
            Route::get('qr/semua.pdf', [QrCodeController::class, 'pdfAll'])->name('qr.pdf-all');
            Route::get('qr/{unit}.png', [QrCodeController::class, 'png'])->name('qr.png');
            Route::get('qr/{unit}.pdf', [QrCodeController::class, 'pdf'])->name('qr.pdf');
            Route::get('qr/{unit}/cetak', [QrCodeController::class, 'print'])->name('qr.print');
            Route::post('qr/{unit}/regenerate', [QrCodeController::class, 'regenerate'])->name('qr.regenerate');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('categories/{category}/subcategories', [CategoryController::class, 'storeSubcategory'])->name('categories.subcategories.store');
            Route::put('subcategories/{subcategory}', [CategoryController::class, 'updateSubcategory'])->name('subcategories.update');
            Route::delete('subcategories/{subcategory}', [CategoryController::class, 'destroySubcategory'])->name('subcategories.destroy');

            Route::get('sla', [SlaPolicyController::class, 'index'])->name('sla.index');
            Route::put('sla/{slaPolicy}', [SlaPolicyController::class, 'update'])->name('sla.update');

            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/run-tasks', [SettingsController::class, 'runScheduledTasks'])->middleware('throttle:3,1')->name('settings.run-tasks');
            Route::post('settings/templates', [QuickReplyTemplateController::class, 'store'])->name('templates.store');
            Route::put('settings/templates/{template}', [QuickReplyTemplateController::class, 'update'])->name('templates.update');
            Route::delete('settings/templates/{template}', [QuickReplyTemplateController::class, 'destroy'])->name('templates.destroy');

            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        });
    });
});
