<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('search.index');
});

Route::get('/search', [SearchController::class, 'index'])->name('search.index');

Route::get('/documents', [DocumentController::class, 'index'])->name('documents.index');
Route::post('/documents', [DocumentController::class, 'store'])->name('documents.store');
Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
