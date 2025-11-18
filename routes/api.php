<?php

use Illuminate\Support\Facades\Route;
use Masum\AiTranslator\Http\Controllers\LanguageController;
use Masum\AiTranslator\Http\Controllers\SettingController;
use Masum\AiTranslator\Http\Controllers\TranslationController;

// Language Management Routes
Route::prefix('languages')->name('languages.')->group(function () {
    Route::get('/', [LanguageController::class, 'index'])->name('index');
    Route::post('/', [LanguageController::class, 'store'])->name('store');
    Route::get('/{code}', [LanguageController::class, 'show'])->name('show');
    Route::put('/{code}', [LanguageController::class, 'update'])->name('update');
    Route::delete('/{code}', [LanguageController::class, 'destroy'])->name('destroy');
    Route::post('/{code}/toggle', [LanguageController::class, 'toggle'])->name('toggle');
    Route::post('/{code}/default', [LanguageController::class, 'setDefault'])->name('set-default');
});

// Language to Country Conversion Routes
Route::get('/language-to-country/{code}', [LanguageController::class, 'countryInfo'])->name('language.country');
Route::get('/countries', [LanguageController::class, 'allCountries'])->name('countries');

// Translation Management Routes
Route::prefix('translations')->name('translations.')->group(function () {
    Route::get('/', [TranslationController::class, 'index'])->name('index');
    Route::post('/', [TranslationController::class, 'store'])->name('store');
    Route::get('/groups', [TranslationController::class, 'groups'])->name('groups');
    Route::post('/clear-cache', [TranslationController::class, 'clearCache'])->name('clear-cache');
    Route::get('/{id}', [TranslationController::class, 'show'])->name('show');
    Route::put('/{id}', [TranslationController::class, 'update'])->name('update');
    Route::delete('/{id}', [TranslationController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/history', [TranslationController::class, 'history'])->name('history');
});

// AI Translation Routes
Route::post('/auto-translate', [TranslationController::class, 'autoTranslate'])->name('auto-translate');
Route::post('/batch-translate', [TranslationController::class, 'batchTranslate'])->name('batch-translate');

// Settings Management Routes
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('index');
    Route::get('/{key}', [SettingController::class, 'show'])->name('show');
    Route::put('/{key}', [SettingController::class, 'update'])->name('update');
    Route::delete('/{key}', [SettingController::class, 'destroy'])->name('destroy');
});
