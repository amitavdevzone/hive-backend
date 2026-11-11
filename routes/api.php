<?php

use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\ReminderController;
use App\Http\Controllers\Api\TagController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', LoginController::class)->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('notes', NoteController::class);
    Route::apiResource('reminders', ReminderController::class);
    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
});
