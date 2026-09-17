<?php

use App\Http\Controllers\Admin\DepotController;
use App\Http\Controllers\Admin\InstellingenController;
use App\Http\Controllers\Admin\KolomController;
use App\Http\Controllers\BeschikbaarheidController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RolController;
use Illuminate\Support\Facades\Route;

// Alles achter de CORE-login (middleware 'core' = cookie-relay naar Boels CORE)
Route::middleware('core')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/kies-rol', [RolController::class, 'kies'])->name('kies-rol');
    Route::post('/kies-rol', [RolController::class, 'opslaan'])->name('kies-rol.opslaan');
    Route::get('/geen-toegang', [RolController::class, 'geenToegang'])->name('geen-toegang');

    // Uploads (iedereen met een rol mag uploaden)
    Route::get('/uploads', [UploadController::class, 'index'])->name('uploads.index');
    Route::post('/uploads', [UploadController::class, 'opslaan'])->name('uploads.opslaan');
    Route::get('/uploads/{upload}', [UploadController::class, 'toon'])->name('uploads.toon');
    Route::delete('/uploads/{upload}', [UploadController::class, 'verwijder'])->name('uploads.verwijder');
    Route::get('/uploads/{upload}/beschikbaarheid', [BeschikbaarheidController::class, 'toon'])->name('beschikbaarheid');

    // Beheer (alleen actieve rol admin)
    Route::prefix('beheer')->name('admin.')->middleware('rol:admin')->group(function () {
        Route::get('/instellingen', [InstellingenController::class, 'index'])->name('instellingen');
        Route::post('/instellingen', [InstellingenController::class, 'opslaan'])->name('instellingen.opslaan');
        Route::get('/depots', [DepotController::class, 'index'])->name('depots');
        Route::post('/depots/sync', [DepotController::class, 'sync'])->name('depots.sync');
        Route::post('/depots', [DepotController::class, 'opslaan'])->name('depots.opslaan');
        Route::post('/depots/koppel', [DepotController::class, 'koppel'])->name('depots.koppel');
        Route::get('/kolommen', [KolomController::class, 'index'])->name('kolommen');
        Route::post('/kolommen', [KolomController::class, 'opslaan'])->name('kolommen.opslaan');
    });
});

Route::get('/uitloggen', [RolController::class, 'uitloggen'])->name('uitloggen');
