<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MeBookController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthController::class, 'token']);

Route::get('/books', [BookController::class, 'index']);
Route::get('/books/{id}', [BookController::class, 'show'])->whereNumber('id');

Route::middleware('auth')->prefix('me')->group(function () {
    Route::get('/books', [MeBookController::class, 'index']);
    Route::post('/books', [MeBookController::class, 'store']);
    Route::get('/books/{book}', [MeBookController::class, 'show'])->whereNumber('book');
    Route::patch('/books/{book}', [MeBookController::class, 'update'])->whereNumber('book');
    Route::delete('/books/{book}', [MeBookController::class, 'destroy'])->whereNumber('book');
});
